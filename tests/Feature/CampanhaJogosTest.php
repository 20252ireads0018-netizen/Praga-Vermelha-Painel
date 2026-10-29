<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\GladioCarta;
use App\Models\GladioImagem;
use App\Models\JogoCarteira;
use App\Models\JogoPartida;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
        (require database_path('migrations/2026_10_28_000012_create_gladio_imagens.php'))->up();
        (require database_path('migrations/2026_10_28_000013_gladio_decks_e_palavras.php'))->up();

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

        // a Ana joga o Raio escolhendo 50 de dano (o feitiço não tem efeito próprio) no rosto da Bia (40 de vida) e vence
        $mao = $this->actingAs($this->ana)->getJson($this->url("/partidas/{$p->id}/estado"))->json('estado.lados.a.mao');
        $this->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'jogar', 'inst' => $mao[0]['i'], 'alvo' => 'inimigo', 'efeito' => 'dano', 'valor' => 50])
            ->assertOk()->assertJsonPath('status', 'fim')->assertJsonPath('vencedor', 'j1');
        $this->assertSame([140, 60], [$this->dinheiro($this->ana), $this->dinheiro($this->bia)]);
        $this->assertSame($this->ana->id, (int) $p->fresh()->vencedor_id);
    }

    public function test_decks_palavras_e_combate_pelas_rotas(): void
    {
        $this->actingAs($this->mestre);
        $lucas = $this->postJson($this->url('/cartas'), ['nome' => 'Lucas', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 4, 'vida' => 5, 'palavras' => ['sobrepujar']])
            ->assertOk()->assertJsonPath('carta.palavras', 'sobrepujar')->json('carta.id');
        $isaque = $this->postJson($this->url('/cartas'), ['nome' => 'Isaque', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 2, 'vida' => 1])->json('carta.id');

        // deck: de 15 a 60 cartas, até 4 cópias (o excesso é cortado)
        $this->actingAs($this->ana);
        $this->postJson($this->url('/decks'), ['nome' => 'Pequeno', 'cartas' => json_encode([$lucas => 4])])->assertStatus(422);
        $this->postJson($this->url('/decks'), ['nome' => 'Lixo', 'cartas' => json_encode([$lucas => 9, 999 => 4])])->assertStatus(422);
        $deckAna = $this->postJson($this->url('/decks'), ['nome' => 'Fúria', 'cartas' => json_encode([$lucas => 4, $isaque => 4])])->assertStatus(422);
        // só 8 cartas: precisa de mais uma carta no catálogo
        $this->actingAs($this->mestre);
        $extras = [];
        foreach (['A', 'B'] as $n) {
            $extras[] = $this->postJson($this->url('/cartas'), ['nome' => $n, 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 1, 'vida' => 1])->json('carta.id');
        }
        $this->actingAs($this->ana);
        $deckAna = $this->postJson($this->url('/decks'), ['nome' => 'Fúria', 'cartas' => json_encode([$lucas => 4, $isaque => 4, $extras[0] => 4, $extras[1] => 4])])
            ->assertOk()->json('deck.id');
        // ninguém usa o deck dos outros
        $this->actingAs($this->bia)->postJson($this->url('/partidas'), ['jogo' => 'gladio', 'deck_id' => $deckAna])->assertStatus(422);

        $this->actingAs($this->ana)->postJson($this->url('/partidas'), ['jogo' => 'gladio', 'deck_id' => $deckAna])->assertOk();
        $p = JogoPartida::firstOrFail();
        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/aceitar"))->assertOk(); // Bia: 20 sorteadas
        $e = $p->fresh()->estado;
        $this->assertSame(11, count($e['lados']['a']['baralho']), 'deck de 16 menos 5 na mão');
        $this->assertSame(15, count($e['lados']['b']['baralho']));

        // Ana arruma o baralho, compra e coloca uma Lenda; vê o próprio baralho, não o da Bia
        $this->actingAs($this->ana);
        $v = $this->getJson($this->url("/partidas/{$p->id}/estado"))->json('estado');
        $this->assertIsArray($v['lados']['a']['baralho']);
        $this->assertIsInt($v['lados']['b']['baralho']);
        $ordem = array_reverse(array_column($v['lados']['a']['baralho'], 'i'));
        $this->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'reordenar', 'ordem' => $ordem])->assertOk();
        $this->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'comprar'])->assertOk();
        $mao = $p->fresh()->estado['lados']['a']['mao'];
        $this->assertSame($ordem[0], end($mao)['i'], 'comprou o novo topo');
        $this->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'ficha', 'nome' => 'Olhinho', 'atk' => 1, 'vida' => 1])->assertOk();
        $ficha = $p->fresh()->estado['lados']['a']['mesa'][0]['i'];
        $this->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'atacar', 'insts' => [$ficha]])->assertStatus(422); // acabou de entrar

        // a Bia não mexe no turno da Ana
        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'comprar'])->assertStatus(422);
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

    public function test_galeria_de_imagens_das_cartas(): void
    {
        Storage::fake('local');
        $arquivos = [UploadedFile::fake()->image('Kuro.jpg', 300, 170), UploadedFile::fake()->image('outro.png', 50, 50)];

        // jogador não envia; o mestre envia várias de uma vez, numa pasta
        $this->actingAs($this->ana)->post($this->url('/imagens'), ['imagens' => $arquivos], ['Accept' => 'application/json'])->assertForbidden();
        $r = $this->actingAs($this->mestre)->post($this->url('/imagens'), [
            'imagens' => $arquivos, 'nomes' => ['Kuro sombrio', ''], 'pasta' => 'Kuros',
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(['Kuro sombrio', 'outro'], array_column($r->json('imagens'), 'nome'));
        $img = GladioImagem::firstOrFail();
        $this->assertSame('Kuros', $img->pasta);
        Storage::disk('local')->assertExists($img->caminho);
        $this->assertStringStartsWith("campanhas/{$this->camp->id}/galeria/", $img->caminho);

        // arquivo que não é imagem é recusado
        $this->post($this->url('/imagens'), ['imagens' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json'])->assertStatus(422);

        // membros veem a imagem; quem é de fora não
        $this->actingAs($this->bia)->get($this->url('/imagens/' . $img->id))->assertOk();
        $this->actingAs($this->estranho)->get($this->url('/imagens/' . $img->id))->assertForbidden();
        // a galeria vai para a página do lobby
        $this->actingAs($this->mestre)->get($this->url())->assertOk()->assertSee('Kuro sombrio');

        // só o mestre exclui, e o arquivo some junto
        $this->actingAs($this->ana)->deleteJson($this->url('/imagens/' . $img->id))->assertForbidden();
        $this->actingAs($this->mestre)->deleteJson($this->url('/imagens/' . $img->id))->assertOk();
        Storage::disk('local')->assertMissing($img->caminho);
        $this->assertSame(1, GladioImagem::count());

        // a mesma imagem em duas pastas divide o arquivo: excluir uma não apaga o arquivo da outra
        $resto = GladioImagem::firstOrFail();
        $copia = GladioImagem::create(['campanha_id' => $this->camp->id, 'pasta' => 'Outra', 'nome' => 'cópia', 'caminho' => $resto->caminho]);
        $this->deleteJson($this->url('/imagens/' . $copia->id))->assertOk();
        Storage::disk('local')->assertExists($resto->caminho);
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
