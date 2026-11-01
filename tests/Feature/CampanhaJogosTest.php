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
        (require database_path('migrations/2026_10_29_000014_gladio_comandante.php'))->up();

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

    /** Gladio começa no mulligan: quem está na partida mantém a mão inicial. */
    private function manterTodos($p): void
    {
        foreach ([$this->mestre, $this->ana, $this->bia] as $u) {
            $this->actingAs($u)->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'manter']);
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
            'nome' => 'Raio', 'tipo' => 'feitico', 'custo' => 0, 'descricao' => 'Cause 50 de dano a um adversário',
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
        $this->manterTodos($p); // fase de mulligan: todos mantêm a mão
        $this->assertSame([60, 60], [$this->dinheiro($this->ana), $this->dinheiro($this->bia)]);

        // a Bia não vê a mão da Ana
        $estado = $this->getJson($this->url("/partidas/{$p->id}/estado"))->assertOk()->json('estado');
        $this->assertSame(array_fill(0, 5, ['oculta' => true]), $estado['lados']['a']['mao'], 'a mão da Ana vem virada');
        $this->assertIsArray($estado['lados']['b']['mao']);
        // fora da vez
        $this->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'passar'])->assertStatus(422);
        // a Ana joga o Raio (o texto dá 50 de dano a um adversário) no rosto da Bia (40 de vida) e vence
        // a Ana joga o Raio escolhendo 50 de dano (o feitiço não tem efeito próprio) no rosto da Bia (40 de vida) e vence
        $mao = $this->actingAs($this->ana)->getJson($this->url("/partidas/{$p->id}/estado"))->json('estado.lados.a.mao');
        $this->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'jogar', 'inst' => $mao[0]['i']])
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
        $this->manterTodos($p); // fase de mulligan: todos mantêm a mão
        $e = $p->fresh()->estado;
        $this->assertSame(10, count($e['lados']['a']['baralho']), 'deck de 16 menos 5 na mão e o comandante');
        $this->assertSame(14, count($e['lados']['b']['baralho']));

        // a Ana vê o próprio baralho (quantas cartas e quais), não o da Bia
        $this->actingAs($this->ana);
        $v = $this->getJson($this->url("/partidas/{$p->id}/estado"))->json('estado');
        $this->assertIsArray($v['lados']['a']['baralho']);
        $this->assertIsInt($v['lados']['b']['baralho']);
        // nada à mão: tudo o que muda cartas, vida, energia, mão ou baralho vem do efeito das cartas
        $antes = $p->fresh()->estado;
        foreach (['comprar', 'reordenar', 'embaralhar', 'buscar', 'descartar', 'recuperar', 'invocar', 'sacrificar', 'copiar', 'ficha',
                  'ajustar', 'palavra', 'energia', 'vida', 'shot', 'encerrar', 'comando', 'ativar', 'equipar'] as $manual) {
            $this->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => $manual, 'inst' => 1, 'quanto' => 5, 'energia' => 5, 'energia_max' => 5])
                ->assertStatus(422)->assertJsonFragment(['erro' => 'Isso só acontece pelo efeito das cartas.']);
        }
        $this->assertSame($antes['lados'], $p->fresh()->estado['lados']);
        // jogar sem pagar também não: o "grátis" é ignorado e a energia é cobrada
        $carta = $antes['lados']['a']['mao'][0];
        $this->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'jogar', 'inst' => $carta['i'], 'gratis' => 1])->assertOk();
        $custo = (int) $antes['cartas'][$carta['c']]['custo'];
        $this->assertSame($antes['lados']['a']['energia'] - $custo, $p->fresh()->estado['lados']['a']['energia']);
        $this->assertNotContains('(sem pagar energia)', array_map(fn ($l) => substr($l, -20), $p->fresh()->estado['log']));

        // a Bia não age no turno da Ana
        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'passar'])->assertStatus(422);
    }

    public function test_imagem_da_carta_enviada_como_arquivo(): void
    {
        $r = $this->actingAs($this->mestre)->post($this->url('/cartas'), [
            'nome' => 'Amir', 'tipo' => 'lenda', 'custo' => 3, 'ataque' => 2, 'vida' => 2,
            'imagem_arquivo' => UploadedFile::fake()->image('arte.jpg', 480, 308),
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertStringStartsWith('data:image/jpeg;base64,', $r->json('carta.imagem'));
        $this->assertSame($r->json('carta.imagem'), GladioCarta::findOrFail($r->json('carta.id'))->imagem);
        // arquivo que não é imagem é recusado
        $this->post($this->url('/cartas'), ['nome' => 'X', 'tipo' => 'lenda', 'custo' => 1, 'vida' => 1,
            'imagem_arquivo' => UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_mesa_de_tres_entrar_sair_comecar_e_vencedor_leva_o_pote(): void
    {
        GladioCarta::create(['campanha_id' => $this->camp->id, 'nome' => 'G', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1]);
        foreach ([$this->mestre, $this->ana, $this->bia] as $u) {
            JogoCarteira::create(['campanha_id' => $this->camp->id, 'user_id' => $u->id, 'dinheiro' => 10]);
        }
        $this->actingAs($this->ana)->postJson($this->url('/partidas'), ['jogo' => 'gladio', 'aposta' => 10, 'vagas' => 3])->assertOk();
        $p = JogoPartida::firstOrFail();

        // a Bia entra e sai; a mesa continua aberta e ninguém pagou nada ainda
        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/aceitar"))->assertOk()->assertJsonPath('comecou', false);
        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/cancelar"))->assertOk();
        $this->assertSame([$this->ana->id], $p->fresh()->config['jogadores']);
        $this->assertSame('aberta', $p->fresh()->status);
        // só quem criou começa, e precisa de 2
        $this->actingAs($this->ana)->postJson($this->url("/partidas/{$p->id}/comecar"))->assertStatus(422);

        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/aceitar"))->assertOk()->assertJsonPath('comecou', false);
        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/comecar"))->assertStatus(422);
        $this->actingAs($this->mestre)->postJson($this->url("/partidas/{$p->id}/aceitar"))->assertOk()->assertJsonPath('comecou', true);
        $this->manterTodos($p); // fase de mulligan: todos mantêm a mão
        $p->refresh();
        $this->assertSame('andamento', $p->status);
        $this->assertSame(['a', 'b', 'c'], $p->estado['ordem']);
        $this->assertSame(['a' => 'Ana', 'b' => 'Bia', 'c' => 'Mestre'], $p->estado['nomes']);
        $this->assertSame([0, 0, 0], [$this->dinheiro($this->ana), $this->dinheiro($this->bia), $this->dinheiro($this->mestre)]);
        $this->assertSame('c', $p->ladoDe($this->mestre->id));

        // shots só pelas cartas; a Bia e o mestre desistem: a Ana leva as 3 apostas
        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'shot', 'quanto' => 7])->assertStatus(422);
        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'desistir'])->assertOk();
        $this->actingAs($this->mestre)->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'desistir'])
            ->assertOk()->assertJsonPath('status', 'fim');
        $this->assertSame(30, $this->dinheiro($this->ana));
        $this->assertSame($this->ana->id, (int) $p->fresh()->vencedor_id);
    }

    public function test_texto_da_carta_pede_escolha_pelas_rotas(): void
    {
        GladioCarta::create(['campanha_id' => $this->camp->id, 'nome' => 'Brinde', 'tipo' => 'feitico', 'custo' => 0, 'ataque' => 0, 'vida' => 0,
                             'descricao' => 'O jogador alvo vira um shot e compra uma carta']);
        $this->actingAs($this->ana)->postJson($this->url('/partidas'), ['jogo' => 'gladio'])->assertOk();
        $p = JogoPartida::firstOrFail();
        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/aceitar"))->assertOk();
        $this->manterTodos($p); // fase de mulligan: todos mantêm a mão
        $p->refresh();
        $this->assertStringContainsString('toma 1 shot', $p->estado['cartas'][array_key_first($p->estado['cartas'])]['auto']);

        $brinde = $p->estado['lados']['a']['mao'][0]['i']; // deck sorteado: só há o Brinde
        $this->actingAs($this->ana)->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'jogar', 'inst' => $brinde])
            ->assertOk()->assertJsonPath('estado.escolha.tipo', 'jogador');
        // só quem conjurou escolhe; a Bia vê a espera
        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'escolher', 'alvo' => 'j:b'])->assertStatus(422);
        $this->actingAs($this->ana)->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'escolher', 'alvo' => 'j:b'])
            ->assertOk()->assertJsonPath('estado.lados.b.shots', 1)->assertJsonPath('estado.escolha', null);
        $this->actingAs($this->ana)->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'passar'])
            ->assertOk()->assertJsonPath('estado.vez', 'b')->assertJsonPath('estado.fase', 'principal');
    }

    public function test_carta_nova_do_mestre_entra_com_efeito_automatico_na_proxima_partida(): void
    {
        // o mestre lança uma carta nova pelo criador de cartas
        $this->actingAs($this->mestre)->postJson($this->url('/cartas'), ['nome' => 'Recruta', 'tipo' => 'lenda', 'custo' => 0,
            'ataque' => 1, 'vida' => 1, 'descricao' => 'Quando invocado, compre duas cartas'])->assertOk();
        $this->actingAs($this->ana)->postJson($this->url('/partidas'), ['jogo' => 'gladio'])->assertOk();
        $p = JogoPartida::firstOrFail();
        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/aceitar"))->assertOk();
        $this->manterTodos($p); // fase de mulligan: todos mantêm a mão
        $p->refresh();
        $carta = array_values($p->estado['cartas'])[0];
        $this->assertSame('Ao entrar: você compra 2 cartas.', $carta['auto']);

        // jogada, compra duas sozinha
        $e = $p->estado;
        $mao = count($e['lados']['a']['mao']);
        $this->actingAs($this->ana)->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'jogar', 'inst' => $e['lados']['a']['mao'][0]['i']])
            ->assertOk()->assertJsonCount($mao + 1, 'estado.lados.a.mao');
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

    public function test_gladio_aposta_o_dinheiro_de_um_personagem_vinculado(): void
    {
        Schema::create('fichas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('usuario_id');
            $t->string('nome');
            $t->unsignedBigInteger('dinheiro')->default(0);
            $t->timestamps();
        });
        Schema::create('campanha_fichas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campanha_id');
            $t->foreignId('ficha_id');
            $t->foreignId('user_id');
            $t->string('foto_path')->nullable();
            $t->timestamps();
        });
        $ficha = \Illuminate\Support\Facades\DB::table('fichas')->insertGetId(['usuario_id' => $this->ana->id, 'nome' => 'Lia', 'dinheiro' => 100]);
        $outra = \Illuminate\Support\Facades\DB::table('fichas')->insertGetId(['usuario_id' => $this->ana->id, 'nome' => 'Sem campanha', 'dinheiro' => 999]);
        \App\Models\CampanhaFicha::create(['campanha_id' => $this->camp->id, 'ficha_id' => $ficha, 'user_id' => $this->ana->id]);
        GladioCarta::create(['campanha_id' => $this->camp->id, 'nome' => 'Soldado', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1]);
        JogoCarteira::create(['campanha_id' => $this->camp->id, 'user_id' => $this->bia->id, 'dinheiro' => 50]);

        // só personagem dela, vinculado à campanha, e com Dinheiro bastante
        $this->actingAs($this->ana)->postJson($this->url('/partidas'), ['jogo' => 'gladio', 'aposta' => 30, 'ficha_id' => $outra])->assertStatus(422);
        $this->actingAs($this->ana)->postJson($this->url('/partidas'), ['jogo' => 'gladio', 'aposta' => 300, 'ficha_id' => $ficha])->assertStatus(422);
        $r = $this->actingAs($this->ana)->postJson($this->url('/partidas'), ['jogo' => 'gladio', 'aposta' => 30, 'ficha_id' => $ficha])->assertOk();
        $this->assertSame([['id' => $ficha, 'nome' => 'Lia', 'dinheiro' => 100]], $r->json('fichas'));
        $p = JogoPartida::firstOrFail();
        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/aceitar"))->assertOk(); // Bia: carteira
        $this->assertSame([70, 20], [(int) \Illuminate\Support\Facades\DB::table('fichas')->where('id', $ficha)->value('dinheiro'), $this->dinheiro($this->bia)]);
        $this->assertStringStartsWith('Lia (Ana)', $p->fresh()->estado['nomes']['a']);
        $this->manterTodos($p);
        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'desistir'])->assertOk();
        $this->assertSame(130, (int) \Illuminate\Support\Facades\DB::table('fichas')->where('id', $ficha)->value('dinheiro'), 'o pote volta para a ficha');
        $this->assertSame(0, $this->dinheiro($this->ana), 'a carteira da campanha não mudou');
    }

    public function test_limpar_partidas_terminadas(): void
    {
        $nova = fn ($status) => JogoPartida::create(['campanha_id' => $this->camp->id, 'jogo' => 'gladio', 'jogador1_id' => $this->ana->id,
            'jogador2_id' => $this->bia->id, 'aposta' => 0, 'status' => $status, 'config' => []]);
        $fim = $nova('fim');
        $cancelada = $nova('cancelada');
        $aberta = $nova('aberta');
        // jogador: some só do histórico dele
        $r = $this->actingAs($this->ana)->postJson($this->url('/limpar-partidas'), ['jogo' => 'gladio'])->assertOk();
        $this->assertSame([$aberta->id], array_column($r->json('partidas'), 'id'));
        $this->assertSame(3, JogoPartida::count());
        $r = $this->actingAs($this->bia)->getJson($this->url('/lobby'))->assertOk();
        $this->assertCount(3, $r->json('partidas'), 'para a Bia continuam lá');
        // mestre: apaga para todos (a aberta fica)
        $this->actingAs($this->mestre)->postJson($this->url('/limpar-partidas'), ['jogo' => 'gladio'])->assertOk();
        $this->assertSame([$aberta->id], JogoPartida::pluck('id')->all());
        $this->assertNull(JogoPartida::find($fim->id) ?? JogoPartida::find($cancelada->id));
    }

    public function test_playmat_do_jogador_enviar_ver_e_tirar(): void
    {
        Storage::fake('local');
        $this->actingAs($this->ana)->post($this->url('/playmat'), ['playmat' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
            ['Accept' => 'application/json'])->assertStatus(422);
        $r = $this->actingAs($this->ana)->post($this->url('/playmat'), ['playmat' => UploadedFile::fake()->image('mesa.jpg', 800, 400)],
            ['Accept' => 'application/json'])->assertOk();
        $this->assertStringContainsString("/playmat/{$this->ana->id}?v=", $r->json('playmat'));
        Storage::disk('local')->assertExists("playmats/{$this->ana->id}.jpg");
        $this->actingAs($this->bia)->get($this->url("/playmat/{$this->ana->id}"))->assertOk(); // quem é da campanha vê
        $this->actingAs($this->bia)->get($this->url("/playmat/{$this->bia->id}"))->assertNotFound();
        $this->actingAs($this->ana)->post($this->url('/playmat'), ['remover' => 1], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('playmat', null);
        Storage::disk('local')->assertMissing("playmats/{$this->ana->id}.jpg");
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

    public function test_comandante_do_deck_e_apagar_do_historico(): void
    {
        $this->actingAs($this->mestre);
        $lenda = GladioCarta::create(['campanha_id' => $this->camp->id, 'nome' => 'Rei', 'tipo' => 'lenda', 'custo' => 2, 'ataque' => 3, 'vida' => 3]);
        $outra = GladioCarta::create(['campanha_id' => $this->camp->id, 'nome' => 'Soldado', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1]);
        $magia = GladioCarta::create(['campanha_id' => $this->camp->id, 'nome' => 'Raio', 'tipo' => 'feitico', 'custo' => 1, 'ataque' => 0, 'vida' => 0]);

        // feitiço não serve de comandante; a Lenda do deck, sim
        $cartas = json_encode([$lenda->id => 4, $outra->id => 4, $magia->id => 4, (string) GladioCarta::create(['campanha_id' => $this->camp->id, 'nome' => 'X', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1])->id => 4]);
        $r = $this->actingAs($this->ana)->postJson($this->url('/decks'), ['nome' => 'Real', 'cartas' => $cartas, 'comandante_id' => $magia->id])->assertOk();
        $this->assertNull($r->json('deck.comandante_id'));
        $r = $this->postJson($this->url('/decks'), ['id' => $r->json('deck.id'), 'nome' => 'Real', 'cartas' => $cartas, 'comandante_id' => $lenda->id])->assertOk();
        $this->assertSame($lenda->id, $r->json('deck.comandante_id'));

        $this->postJson($this->url('/partidas'), ['jogo' => 'gladio', 'deck_id' => $r->json('deck.id')])->assertOk();
        $p = JogoPartida::firstOrFail();
        $this->actingAs($this->bia)->postJson($this->url("/partidas/{$p->id}/aceitar"))->assertOk();
        $this->manterTodos($p); // fase de mulligan: todos mantêm a mão
        $e = $p->fresh()->estado;
        $this->assertSame([(string) $lenda->id], array_column($e['lados']['a']['comando'], 'c'));
        $this->assertSame('lenda', $e['cartas'][$e['lados']['b']['comando'][0]['c']]['tipo'], 'Bia sem deck: Lenda sorteada');

        // energia não se ajusta à mão
        $this->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'energia', 'energia' => 2, 'energia_max' => 2])->assertStatus(422);
        $this->assertSame(0, $p->fresh()->estado['lados']['b']['energiaMax']);

        // em andamento não apaga; terminada, a Bia tira do histórico dela e o mestre apaga de vez
        $this->deleteJson($this->url("/partidas/{$p->id}"))->assertStatus(422);
        $this->postJson($this->url("/partidas/{$p->id}/acao"), ['acao' => 'desistir'])->assertOk();
        $this->deleteJson($this->url("/partidas/{$p->id}"))->assertOk()->assertJsonCount(0, 'partidas');
        $this->actingAs($this->ana)->getJson($this->url('/lobby'))->assertJsonCount(1, 'partidas');
        $this->actingAs($this->mestre)->deleteJson($this->url("/partidas/{$p->id}"))->assertOk()->assertJsonCount(0, 'partidas');
        $this->assertNull(JogoPartida::find($p->id));
    }
}
