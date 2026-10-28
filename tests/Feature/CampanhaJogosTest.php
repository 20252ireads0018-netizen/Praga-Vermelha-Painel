<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\GladioCarta;
use App\Models\JogoCarteira;
use App\Models\JogoPartida;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fluxo dos jogos da campanha pelas rotas de verdade, num SQLite em memória
 * (só as tabelas necessárias: as outras migrações do projeto são feitas para MySQL).
 */
class CampanhaJogosTest extends TestCase
{
    private User $mestre;
    private User $ana;
    private User $bia;
    private User $estranho;
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
            $t->string('chave_maldicoes')->nullable();
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
        (require database_path('migrations/2026_10_28_000011_create_jogos_da_campanha.php'))->up();

        $u = fn ($n) => User::create(['name' => $n, 'email' => strtolower($n) . '@t.test', 'password' => 'x']);
        $this->mestre = $u('Mestre');
        $this->ana = $u('Ana');
        $this->bia = $u('Bia');
        $this->estranho = $u('Estranho');
        $this->camp = Campanha::create(['nome' => 'Teste', 'mestre_id' => $this->mestre->id]);
        foreach ([$this->ana, $this->bia] as $j) {
            $this->camp->participantes()->create(['user_id' => $j->id, 'status' => 'ativo']);
        }
    }

    private function url(string $resto = ''): string
    {
        return '/campanhas/' . $this->camp->id . '/jogos' . $resto;
    }

    private function dinheiro(User $u): int
    {
        return (int) JogoCarteira::where('campanha_id', $this->camp->id)->where('user_id', $u->id)->value('dinheiro');
    }

    public function test_paginas_e_permissoes(): void
    {
        $this->actingAs($this->ana)->get($this->url())->assertOk()->assertSee('Gladio');
        $this->actingAs($this->estranho)->get($this->url())->assertForbidden();
        // só o mestre cria cartas e mexe no Dinheiro
        $this->actingAs($this->ana)->postJson($this->url('/cartas'), ['nome' => 'X', 'tipo' => 'lenda', 'custo' => 1, 'vida' => 1])->assertForbidden();
        $this->actingAs($this->ana)->postJson($this->url('/dinheiro'), ['user_id' => $this->ana->id, 'dinheiro' => 999])->assertForbidden();
    }

    public function test_gladio_aposta_sai_no_aceite_e_vencedor_leva_tudo(): void
    {
        $this->actingAs($this->mestre)->postJson($this->url('/cartas'), [
            'nome' => 'Raio', 'tipo' => 'feitico', 'custo' => 0, 'efeito' => 'dano', 'valor' => 50,
        ])->assertOk();
        foreach ([$this->ana, $this->bia] as $j) {
            $this->postJson($this->url('/dinheiro'), ['user_id' => $j->id, 'dinheiro' => 100])->assertOk();
        }

        // aposta maior que o saldo é recusada
        $this->actingAs($this->ana)->postJson($this->url('/partidas'), ['jogo' => 'gladio', 'aposta' => 150])->assertStatus(422);
        $this->postJson($this->url('/partidas'), ['jogo' => 'gladio', 'aposta' => 40, 'convidado_id' => $this->bia->id])->assertOk();
        $p = JogoPartida::firstOrFail();

        // o mestre não é o convidado; a própria Ana não aceita
        $this->actingAs($this->mestre)->postJson($this->url("/partidas/{$p->id}/aceitar"))->assertStatus(422);
        $this->actingAs($this->ana)->postJson($this->url("/partidas/{$p->id}/aceitar"))->assertStatus(422);
        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/aceitar"))->assertOk();
        $this->assertSame([60, 60], [$this->dinheiro($this->ana), $this->dinheiro($this->bia)]);

        // a Bia não vê a mão da Ana
        $estado = $this->getJson($this->url("/partidas/{$p->id}/estado"))->assertOk()->json('estado');
        $this->assertIsInt($estado['lados']['a']['mao']);
        $this->assertIsArray($estado['lados']['b']['mao']);
        // fora da vez
        $this->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'passar'])->assertStatus(422);

        // a Ana joga o Raio (50) no rosto da Bia (40 de vida) e vence
        $mao = $this->actingAs($this->ana)->getJson($this->url("/partidas/{$p->id}/estado"))->json('estado.lados.a.mao');
        $this->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'jogar', 'inst' => $mao[0]['i'], 'alvo' => 'inimigo'])
            ->assertOk()->assertJsonPath('status', 'fim')->assertJsonPath('vencedor', 'j1');
        $this->assertSame([140, 60], [$this->dinheiro($this->ana), $this->dinheiro($this->bia)]);
        $this->assertSame($this->ana->id, (int) $p->fresh()->vencedor_id);
    }

    public function test_mestre_cancela_partida_em_andamento_e_devolve_apostas(): void
    {
        GladioCarta::create(['campanha_id' => $this->camp->id, 'nome' => 'G', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1]);
        JogoCarteira::create(['campanha_id' => $this->camp->id, 'user_id' => $this->ana->id, 'dinheiro' => 10]);
        JogoCarteira::create(['campanha_id' => $this->camp->id, 'user_id' => $this->bia->id, 'dinheiro' => 10]);
        $this->actingAs($this->ana)->postJson($this->url('/partidas'), ['jogo' => 'gladio', 'aposta' => 10])->assertOk();
        $p = JogoPartida::firstOrFail();
        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/aceitar"))->assertOk();
        $this->assertSame(0, $this->dinheiro($this->ana));
        $this->postJson($this->url("/partidas/{$p->id}/cancelar"))->assertStatus(422); // jogador não cancela
        $this->actingAs($this->mestre)->postJson($this->url("/partidas/{$p->id}/cancelar"))->assertOk();
        $this->assertSame([10, 10], [$this->dinheiro($this->ana), $this->dinheiro($this->bia)]);
    }

    public function test_pela_dama_com_peca_customizada_e_mate(): void
    {
        // a Bia cria uma peça e a coloca no lugar do cavalo b8
        $this->actingAs($this->bia)->postJson($this->url('/pecas'), [
            'nome' => 'Saltador', 'simbolo' => 'S', 'regras' => json_encode([['dx' => 0, 'dy' => 2], ['dx' => 1, 'dy' => 1, 'd' => true, 'alc' => 2]]),
        ])->assertOk();
        $tipo = 'c1'; // primeira peça criada
        $exBia = ['torre', $tipo, 'bispo', 'dama', 'rei', 'bispo', 'cavalo', 'torre', 'peao', 'peao', 'peao', 'peao', 'peao', 'peao', 'peao', 'peao'];
        // a Ana não pode escalar a peça da Bia
        $this->actingAs($this->ana)->postJson($this->url('/partidas'), ['jogo' => 'pela_dama', 'exercito' => json_encode($exBia)])->assertStatus(422);
        $this->postJson($this->url('/partidas'), ['jogo' => 'pela_dama'])->assertOk();
        $p = JogoPartida::firstOrFail();
        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/aceitar"), ['exercito' => json_encode($exBia)])->assertOk();
        $e = $p->fresh()->estado;
        $this->assertSame('c1', $e['tab'][7][1]['t']);
        $this->assertSame('p', $e['tab'][7][1]['c']);

        // mate do pastor (a peça da Bia em b8 anda 2 para a frente: b8-b6)
        $lances = [
            [$this->ana, [4, 1, 4, 3]], [$this->bia, [4, 6, 4, 4]],
            [$this->ana, [5, 0, 2, 3]], [$this->bia, [1, 7, 1, 5]],
            [$this->ana, [3, 0, 7, 4]], [$this->bia, [6, 7, 5, 5]],
            [$this->ana, [7, 4, 5, 6]],
        ];
        foreach ($lances as [$quem, [$x, $y, $tx, $ty]]) {
            $this->actingAs($quem)->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'mover', 'x' => $x, 'y' => $y, 'tx' => $tx, 'ty' => $ty])->assertOk();
        }
        $p->refresh();
        $this->assertSame('fim', $p->status);
        $this->assertSame($this->ana->id, (int) $p->vencedor_id);
        // quem assiste não joga
        $this->actingAs($this->mestre)->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'desistir'])->assertStatus(422);
    }
}
