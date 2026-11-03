<?php

namespace Tests\Feature;

use App\Models\Mesa;
use App\Models\User;
use App\Support\Backup;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Backup do banco, dono do tabletop, limite por rota e cabeçalhos de proteção. */
class SegurancaBackupTest extends TestCase
{
    private User $dono;
    private User $outro;
    private string $pastaOriginal;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'array', 'cache.default' => 'array', 'pix.admins' => ['dono@praga.test']]);
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->rememberToken();
            $t->timestamps();
        });
        (require database_path('migrations/2026_10_23_000000_create_mesas_table.php'))->up();
        (require database_path('migrations/2026_10_26_000000_add_sala_to_mesas_table.php'))->up();
        Schema::table('mesas', fn (Blueprint $t) => $t->foreignId('user_id')->nullable());
        $this->dono = User::create(['name' => 'Dono', 'email' => 'dono@praga.test', 'password' => bcrypt('x')]);
        $this->outro = User::create(['name' => 'Outro', 'email' => 'outro@praga.test', 'password' => bcrypt('x')]);
        $this->app->useStoragePath(sys_get_temp_dir() . '/pv-teste-' . uniqid());
    }

    protected function tearDown(): void
    {
        foreach (glob(Backup::pasta() . '/{,.}*', GLOB_BRACE) ?: [] as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        parent::tearDown();
    }

    public function test_backup_gera_sql_restauravel_com_todas_as_linhas(): void
    {
        Mesa::create(['nome' => "Mesa 'com aspas' e acentuação", 'estado' => ['x' => 1]]);
        $nome = Backup::gerar();

        $this->assertMatchesRegularExpression('/^banco-\d{8}-\d{6}\.sql\.gz$/', $nome);
        $sql = gzdecode(file_get_contents(Backup::caminho($nome)));
        $this->assertStringContainsString('CREATE TABLE "mesas"', $sql);
        $this->assertStringContainsString("Mesa ''com aspas'' e acentuação", $sql);
        $this->assertStringContainsString('dono@praga.test', $sql);
        $this->assertFileExists(Backup::pasta() . '/.htaccess');

        // restaura num banco vazio e confere
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec($sql);
        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $this->assertSame("Mesa 'com aspas' e acentuação", $pdo->query('SELECT nome FROM mesas')->fetchColumn());
    }

    public function test_backup_recusa_nomes_fora_do_padrao(): void
    {
        $this->assertNull(Backup::caminho('../.env'));
        $this->assertNull(Backup::caminho('banco-20260101-000000.sql.gz')); // não existe
    }

    public function test_painel_de_backup_so_para_administrador(): void
    {
        $this->actingAs($this->outro)->get('/admin/backup')->assertNotFound();
        $this->actingAs($this->outro)->post('/admin/backup')->assertNotFound();

        $this->actingAs($this->dono)->post('/admin/backup')->assertRedirect('/admin/backup');
        $nome = Backup::lista()[0]['nome'];
        $this->actingAs($this->dono)->get('/admin/backup')->assertOk()->assertSee($nome);
        $this->actingAs($this->outro)->get("/admin/backup/{$nome}")->assertNotFound();
        $this->actingAs($this->dono)->get("/admin/backup/{$nome}")->assertOk()->assertDownload($nome);
    }

    public function test_mesa_nova_e_so_do_dono(): void
    {
        $this->actingAs($this->dono)->post('/tabletop', ['nome' => 'Covil Zeta'])->assertRedirect();
        $mesa = Mesa::firstOrFail();
        $this->assertSame($this->dono->id, (int) $mesa->user_id);

        $this->actingAs($this->outro)->get("/tabletop/{$mesa->id}")->assertNotFound();
        $this->actingAs($this->outro)->putJson("/tabletop/{$mesa->id}", ['estado' => '{}'])->assertNotFound();
        $this->actingAs($this->outro)->postJson("/tabletop/{$mesa->id}/sala")->assertNotFound();
        $this->actingAs($this->outro)->delete("/tabletop/{$mesa->id}")->assertNotFound();
        $this->actingAs($this->outro)->get('/tabletop')->assertOk()->assertDontSee('Covil Zeta');
        $this->assertNotNull($mesa->fresh());

        $this->actingAs($this->dono)->get('/tabletop')->assertOk()->assertSee('Covil Zeta');
        $this->actingAs($this->dono)->get("/tabletop/{$mesa->id}")->assertOk();
    }

    public function test_mesa_antiga_sem_dono_continua_aberta(): void
    {
        $mesa = Mesa::create(['nome' => 'Antiga', 'estado' => []]);
        $this->actingAs($this->outro)->get('/tabletop')->assertOk()->assertSee('Antiga');
        $this->actingAs($this->outro)->get("/tabletop/{$mesa->id}")->assertOk();
    }

    public function test_cada_rota_tem_o_proprio_contador_de_limite(): void
    {
        Route::middleware(['web', 'throttle:2,1'])->get('/_teste/a', fn () => 'a')->name('teste.a');
        Route::middleware(['web', 'throttle:2,1'])->get('/_teste/b', fn () => 'b')->name('teste.b');

        $this->actingAs($this->dono);
        $this->get('/_teste/a')->assertOk();
        $this->get('/_teste/a')->assertOk();
        $this->get('/_teste/a')->assertStatus(429);
        $this->get('/_teste/b')->assertOk(); // antes, a rota b já estaria bloqueada pelo contador de a
    }

    public function test_cabecalhos_de_protecao(): void
    {
        $this->get('/login')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
