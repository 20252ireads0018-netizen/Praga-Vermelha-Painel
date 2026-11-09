<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Pote de dados: rolar habilidade com vantagem (fica o menor d100) ou desvantagem (fica o maior). */
class PoteVantagemTest extends TestCase
{
    private User $ana;
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
        Schema::create('campanha_rolagens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campanha_id');
            $t->foreignId('user_id');
            $t->string('rotulo', 60)->nullable();
            $t->unsignedSmallInteger('qtd');
            $t->unsignedSmallInteger('lados');
            $t->string('extras', 60)->nullable();
            $t->integer('bonus')->default(0);
            $t->text('resultados');
            $t->integer('total');
            $t->timestamps();
        });
        $mestre = User::create(['name' => 'Mestre', 'email' => 'm@t.test', 'password' => 'x']);
        $this->ana = User::create(['name' => 'Ana', 'email' => 'ana@t.test', 'password' => 'x']);
        $this->camp = Campanha::create(['nome' => 'Teste', 'mestre_id' => $mestre->id]);
        $this->camp->participantes()->create(['user_id' => $this->ana->id, 'status' => 'ativo']);
    }

    private function rolar(array $corpo)
    {
        return $this->actingAs($this->ana)->postJson('/campanhas/' . $this->camp->id . '/rolagens', $corpo + ['lados' => 100, 'extras' => '-40']);
    }

    public function test_vantagem_fica_com_o_menor_e_desvantagem_com_o_maior(): void
    {
        $rotulo = str_repeat('Resiliência — Dante ', 3); // 60 caracteres: o prefixo do modo não pode estourar a coluna
        $v = $this->rolar(['qtd' => 3, 'modo' => 'vantagem', 'rotulo' => mb_substr($rotulo, 0, 60)])->assertOk()->json('rolagem');
        $this->assertSame('vantagem', $v['modo']);
        $this->assertCount(3, $v['resultados']);
        $this->assertSame(min($v['resultados']) - 40, $v['total']);
        $this->assertStringStartsWith('Resiliência', $v['rotulo']);

        $d = $this->rolar(['qtd' => 2, 'modo' => 'desvantagem'])->assertOk()->json('rolagem');
        $this->assertSame(['desvantagem', max($d['resultados']) - 40, null], [$d['modo'], $d['total'], $d['rotulo']]);

        // normal continua somando; vantagem com 1 dado é recusada
        $n = $this->rolar(['qtd' => 2])->assertOk()->json('rolagem');
        $this->assertSame([null, array_sum($n['resultados']) - 40], [$n['modo'], $n['total']]);
        $this->rolar(['qtd' => 1, 'modo' => 'vantagem'])->assertStatus(422);

        // quem consulta o pote também recebe o modo
        $lista = $this->actingAs($this->ana)->getJson('/campanhas/' . $this->camp->id . '/rolagens')->assertOk()->json('rolagens');
        $this->assertSame(['vantagem', 'desvantagem', null], array_column($lista, 'modo'));
    }
}
