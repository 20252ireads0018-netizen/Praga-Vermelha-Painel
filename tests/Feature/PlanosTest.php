<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Plano;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Planos: teste de 45 dias, grátis com limites e anúncios, vitalício com a chave do livro do sistema; boas-vindas. */
class PlanosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'array', 'cache.default' => 'array', 'planos.lancamento' => '2026-10-29', 'livro.chave' => 'chave-do-livro']);
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->string('chave_maldicoes')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        (require database_path('migrations/2026_10_29_000015_planos_dos_usuarios.php'))->up();
        Schema::create('fichas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable();
            $t->string('nome')->nullable();
            $t->timestamps();
        });
        Schema::create('campanhas', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->text('descricao')->nullable();
            $t->foreignId('mestre_id');
            $t->boolean('sessao_ativa')->default(false);
            $t->timestamps();
        });
        Schema::create('campanha_jogadores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campanha_id');
            $t->foreignId('user_id');
            $t->string('status', 20)->default('pendente');
            $t->timestamps();
        });
        Plano::esquecerColunas();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Plano::esquecerColunas();
        parent::tearDown();
    }

    private function usuario(string $criadoEm, array $extra = []): User
    {
        $u = User::create(['name' => 'Ana', 'email' => uniqid() . '@t.test', 'password' => 'x'] + $extra);
        $u->forceFill(['created_at' => $criadoEm])->save();

        return $u->fresh();
    }

    public function test_teste_de_45_dias_e_depois_gratis(): void
    {
        Carbon::setTestNow('2026-11-10 12:00');
        $nova = $this->usuario('2026-11-10 12:00');
        $this->assertSame(Plano::TESTE, Plano::de($nova));
        $this->assertFalse(Plano::veAnuncios($nova));
        $this->assertNull(Plano::limite($nova, 'fichas'));
        $this->assertSame(45, Plano::diasDeTeste($nova));

        // conta antiga ganha o teste a partir do lançamento dos planos
        $antiga = $this->usuario('2025-01-01 00:00');
        $this->assertSame(Plano::TESTE, Plano::de($antiga));
        $this->assertSame('2026-12-13', Plano::testeAte($antiga)->toDateString());

        Carbon::setTestNow('2026-12-26 12:00');
        $this->assertSame(Plano::GRATIS, Plano::de($nova));
        $this->assertTrue(Plano::veAnuncios($nova));
        $this->assertSame(5, Plano::limite($nova, 'fichas'));
        $this->assertSame(1, Plano::limite($nova, 'campanhas'));
        $this->assertTrue(Plano::veAnuncios(null)); // visitante
    }

    public function test_plano_gratis_limita_fichas_e_campanhas(): void
    {
        Carbon::setTestNow('2027-06-01 12:00');
        $u = $this->usuario('2026-11-01 00:00', ['objetivo' => 'jogar']);
        $this->assertSame(Plano::GRATIS, Plano::de($u));

        for ($i = 0; $i < 4; $i++) {
            DB::table('fichas')->insert(['user_id' => $u->id, 'nome' => "F$i"]);
        }
        DB::table('fichas')->insert(['user_id' => $u->id + 99, 'nome' => 'de outra conta']);
        $this->actingAs($u)->get('/fichas')->assertOk(); // 4 de 5: ainda cabe uma
        DB::table('fichas')->insert(['user_id' => $u->id, 'nome' => 'F5']);
        $this->get('/fichas')->assertRedirect(route('planos.index'));
        $this->post('/fichafeita', ['nome' => 'Sexta'])->assertRedirect(route('planos.index'));

        $this->post('/campanhas', ['nome' => 'Primeira'])->assertRedirect();
        $this->assertSame(1, DB::table('campanhas')->where('mestre_id', $u->id)->count());
        $this->from('/campanhas')->post('/campanhas', ['nome' => 'Segunda'])->assertRedirect('/campanhas')->assertSessionHasErrors('nome');
        $this->assertSame(1, DB::table('campanhas')->where('mestre_id', $u->id)->count());

        // em teste não há limite
        Carbon::setTestNow('2026-11-02 12:00');
        $this->get('/fichas')->assertOk();
    }

    public function test_chave_do_livro_ativa_o_vitalicio_e_libera_as_historias(): void
    {
        Carbon::setTestNow('2027-06-01 12:00');
        $u = $this->usuario('2026-11-01 00:00', ['objetivo' => 'livros']);

        $this->actingAs($u)->get('/historias/o-cavaleiro-negro')->assertRedirect(route('planos.index'));
        $this->get('/historias/o-cavaleiro-negro/pdf')->assertForbidden();
        $this->post('/livro/acesso', ['chave' => 'errada'])->assertSessionHasErrors('chave');
        $this->assertSame(Plano::GRATIS, Plano::de($u->fresh()));

        $this->post('/livro/acesso', ['chave' => 'chave-do-livro'])->assertRedirect(route('planos.index'));
        $u = $u->fresh();
        $this->assertNotNull($u->livro_comprado_em);
        $this->assertSame(Plano::VITALICIO, Plano::de($u));
        $this->assertFalse(Plano::veAnuncios($u));
        $this->assertNull(Plano::limite($u, 'fichas'));

        // em outra sessão (sem a chave na sessão), a conta continua com tudo liberado
        $this->flushSession();
        $this->actingAs($u)->get('/historias/o-cavaleiro-negro')->assertOk();
        $this->get('/livro')->assertOk();
        $this->get('/planos')->assertOk()->assertSee('Vitalício');
    }

    public function test_iniciante_escolhe_entre_livros_e_jogar(): void
    {
        $this->post('/cadastro', [
            'name' => 'Novo', 'email' => 'novo@t.test', 'password' => 'senha-forte-1', 'password_confirmation' => 'senha-forte-1',
        ])->assertRedirect(route('boas-vindas'));

        $this->get('/')->assertRedirect(route('boas-vindas'));
        $this->get('/boas-vindas')->assertOk()->assertSee('Quero comprar os livros')->assertSee('Quero jogar o RPG');
        $this->post('/boas-vindas', ['objetivo' => 'outra'])->assertSessionHasErrors('objetivo');
        $this->post('/boas-vindas', ['objetivo' => 'livros'])->assertRedirect(route('planos.index'));
        $this->assertSame('livros', User::where('email', 'novo@t.test')->value('objetivo'));
        $this->get('/')->assertOk();

        // visitante vê a pergunta na página inicial e a página de planos
        auth()->logout();
        $this->get('/')->assertOk()->assertSee('Primeira vez aqui?');
        $this->get('/planos')->assertOk()->assertSee('Livros físicos');
    }
}
