<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\Kuro;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KurosTest extends TestCase
{

    private User $mestre;
    private User $ana;
    private User $bia;
    private Campanha $camp;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'array', 'cache.default' => 'array']);
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('campanhas', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->text('descricao')->nullable();
            $t->foreignId('mestre_id');
            $t->timestamps();
        });
        Schema::create('campanha_jogadores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campanha_id');
            $t->foreignId('user_id');
            $t->string('status', 20)->default('pendente');
            $t->timestamps();
        });
        (require database_path('migrations/2026_11_02_000021_create_kuros.php'))->up();

        $u = fn ($n) => User::create(['name' => $n, 'email' => strtolower($n) . '@t.test', 'password' => 'x']);
        $this->mestre = $u('Mestre');
        $this->ana = $u('Ana');
        $this->bia = $u('Bia');
        $this->camp = Campanha::create(['nome' => 'Teste', 'mestre_id' => $this->mestre->id]);
        foreach ([$this->ana, $this->bia] as $j) {
            $this->camp->participantes()->create(['user_id' => $j->id, 'status' => 'ativo']);
        }
    }

    private function url(string $resto = ''): string
    {
        return '/campanhas/' . $this->camp->id . '/kuros' . $resto;
    }

    public function test_mestre_cria_entrega_e_jogador_controla_o_kuro(): void
    {
        $this->actingAs($this->mestre)->get($this->url())->assertOk()->assertSee('Kuro pronto do livro');
        // a ficha é limpa no servidor: só chaves conhecidas, números dentro dos limites
        $r = $this->postJson($this->url(), ['nome' => 'Bicho do Teste', 'dados' => [
            'tipo' => 1, 'aprim' => ['dano' => 1, 'inventado' => 5], 'habilidades' => ['carniceiro' => 1, 'nao_existe' => 2],
            'resistencias' => ['tóxico', 'fogo'], 'daemon' => 'Belial', 'notas_mestre' => 'Segredo do mestre',
        ]])->assertOk();
        $id = $r->json('kuro.id');
        $this->assertSame(['dano' => 1], $r->json('kuro.dados.aprim'));
        $this->assertSame(['carniceiro' => 1], $r->json('kuro.dados.habilidades'));
        $this->assertSame(['tóxico'], $r->json('kuro.dados.resistencias'));

        // jogador não cria nem vê Kuros que não recebeu
        $this->actingAs($this->ana)->postJson($this->url(), ['nome' => 'X', 'dados' => ['tipo' => 1]])->assertStatus(403);
        $this->actingAs($this->ana)->get($this->url())->assertOk()->assertDontSee('Bicho do Teste');

        // o mestre entrega à Ana: ela vê e controla vida e anotações, mas não edita a ficha
        $this->actingAs($this->mestre)->postJson($this->url("/$id/dar"), ['user_id' => $this->ana->id])->assertOk();
        $this->actingAs($this->ana)->get($this->url())->assertOk()->assertSee('Bicho do Teste')->assertDontSee('Segredo do mestre'); // as notas do mestre não vão para o jogador
        $this->postJson($this->url("/$id/estado"), ['pv_atual' => 4, 'notas' => 'ferido'])->assertOk();
        $this->assertSame([4, 'ferido'], [Kuro::find($id)->pv_atual, Kuro::find($id)->notas]);
        $this->postJson($this->url(), ['id' => $id, 'nome' => 'Hack', 'dados' => ['tipo' => 9]])->assertStatus(403);
        $this->actingAs($this->bia)->postJson($this->url("/$id/estado"), ['pv_atual' => 1])->assertStatus(403);

        // entregar a quem não é da campanha é recusado; excluir só o mestre
        $fora = User::create(['name' => 'Fora', 'email' => 'fora@t.test', 'password' => 'x']);
        $this->actingAs($this->mestre)->postJson($this->url("/$id/dar"), ['user_id' => $fora->id])->assertStatus(422);
        $this->actingAs($this->ana)->deleteJson($this->url("/$id"))->assertStatus(403);
        $this->actingAs($this->mestre)->deleteJson($this->url("/$id"))->assertOk();
        $this->assertSame(0, Kuro::count());
    }
}
