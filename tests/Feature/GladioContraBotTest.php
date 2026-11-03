<?php

namespace Tests\Feature;

use App\Jogos\Gladio;
use App\Jogos\GladioBot;
use App\Models\JogoPartida;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Gladio contra o bot: a partida começa na hora com um deck sorteado entre os de todos e o bot joga a vez dele. */
class GladioContraBotTest extends TestCase
{
    private int $campanha;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'array', 'cache.default' => 'array', 'gladio.dono_cartas' => 'ninguem@t.test']);
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
        foreach (['2026_10_28_000011_create_jogos_da_campanha', '2026_10_28_000013_gladio_decks_e_palavras', '2026_10_29_000014_gladio_comandante',
                  '2026_10_29_000017_gladio_subtipos', '2026_10_29_000018_gladio_banlist'] as $m) {
            (require database_path("migrations/$m.php"))->up();
        }
    }

    private function preparar(): array
    {
        $mestre = User::create(['name' => 'Mestre', 'email' => 'mestre@t.test', 'password' => 'x']);
        $ana = User::create(['name' => 'Ana', 'email' => 'ana@t.test', 'password' => 'x']);
        $this->campanha = DB::table('campanhas')->insertGetId(['nome' => 'Mesa', 'mestre_id' => $mestre->id]);
        DB::table('campanha_jogadores')->insert(['campanha_id' => $this->campanha, 'user_id' => $ana->id, 'status' => 'ativo']);
        $ids = [];
        foreach ([['Gladiador', 'lenda', 1, 3, 2], ['Muralha', 'lenda', 2, 1, 6], ['Lucas', 'lenda', 3, 4, 5], ['Corredor', 'lenda', 1, 1, 1]] as [$nome, $tipo, $custo, $atk, $vida]) {
            $ids[] = DB::table('gladio_cartas')->insertGetId(['campanha_id' => $this->campanha, 'nome' => $nome, 'tipo' => $tipo, 'custo' => $custo,
                'ataque' => $atk, 'vida' => $vida, 'descricao' => '', 'created_at' => now(), 'updated_at' => now()]);
        }
        // deck de outro jogador, em outra campanha: o bot pode sortear qualquer um
        DB::table('gladio_decks')->insert(['campanha_id' => $this->campanha, 'user_id' => $mestre->id, 'nome' => 'Muralhas',
            'cartas' => json_encode([(string) $ids[0] => 4, (string) $ids[1] => 4, (string) $ids[2] => 4, (string) $ids[3] => 4]), 'created_at' => now(), 'updated_at' => now()]);

        return [$ana, $ids];
    }

    public function test_partida_contra_o_bot_comeca_e_o_bot_joga(): void
    {
        [$ana] = $this->preparar();
        $r = $this->actingAs($ana)->postJson("/campanhas/{$this->campanha}/jogos/bot")->assertOk();
        $p = JogoPartida::firstOrFail();
        $this->assertSame($r->json('url'), route('campanhas.jogos.partida', [$this->campanha, $p]));
        $this->assertSame(['andamento', 0], [$p->status, $p->aposta]);
        $this->assertSame($p->jogadores()[1] === GladioBot::ID ? null : $ana->id, $p->jogador2_id, 'o bot não vira jogador2_id (não é uma conta)');
        $this->assertContains(GladioBot::ID, $p->jogadores());
        $this->assertSame('Bot (Muralhas)', $p->config['bot']['nome']);

        $e = $p->estado;
        $bot = Gladio::LADOS[array_search(GladioBot::ID, $p->jogadores(), true)];
        $eu = $bot === 'a' ? 'b' : 'a';
        $this->assertTrue($e['mull'][$bot]['ok'], 'o bot já manteve a mão');
        $this->assertContains('Bot (Muralhas)', $e['nomes']);
        $this->assertCount(16 - 1, array_merge($e['lados'][$bot]['mao'], $e['lados'][$bot]['baralho']), 'deck sorteado (16 cartas, uma no comando)');

        $this->get("/campanhas/{$this->campanha}/jogos/partidas/{$p->id}")->assertOk()->assertSee('Bot (Muralhas)');
        $this->getJson("/campanhas/{$this->campanha}/jogos/partidas/{$p->id}/estado")->assertOk()->assertJsonFragment(['nome' => 'Bot (Muralhas)']);

        // a pessoa mantém a mão; o jogo segue até ser a vez dela de novo
        $this->postJson("/campanhas/{$this->campanha}/jogos/partidas/{$p->id}/acao", ['acao' => 'manter'])->assertOk();
        $e = $p->fresh()->estado;
        $this->assertSame($eu, Gladio::esperando($e), 'depois de manter, a mesa espera a pessoa (o bot já jogou se começava)');
        if ($bot === 'a') {
            $this->assertSame(2, $e['turno']);
        }

        // passa a vez: o bot joga o turno dele inteiro na mesma requisição
        $this->postJson("/campanhas/{$this->campanha}/jogos/partidas/{$p->id}/acao", ['acao' => 'passar'])->assertOk();
        $e = $p->fresh()->estado;
        $this->assertSame($eu, Gladio::esperando($e));
        $this->assertNotEmpty($e['lados'][$bot]['mesa'], 'o bot pôs Lendas na mesa');

        // desistir: o bot vence, sem conta vencedora (e sem aposta)
        $this->postJson("/campanhas/{$this->campanha}/jogos/partidas/{$p->id}/acao", ['acao' => 'desistir'])->assertOk();
        $p->refresh();
        $this->assertSame(['fim', null], [$p->status, $p->vencedor_id]);
        $this->getJson("/campanhas/{$this->campanha}/jogos/lobby")->assertOk()->assertSee('Bot (Muralhas)');
    }
}
