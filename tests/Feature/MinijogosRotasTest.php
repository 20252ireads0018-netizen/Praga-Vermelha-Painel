<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\CampanhaFicha;
use App\Models\JogoCarteira;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MinijogosRotasTest extends TestCase
{
    private User $ana;
    private User $fora;
    private Campanha $camp;
    private int $ficha;

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
        Schema::create('fichas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('usuario_id');
            $t->string('nome');
            $t->integer('dinheiro')->default(0);
            $t->timestamps();
        });
        (require database_path('migrations/2026_10_26_000000_create_campanhas_tables.php'))->up();
        (require database_path('migrations/2026_10_28_000011_create_jogos_da_campanha.php'))->up();
        $u = fn ($n) => User::create(['name' => $n, 'email' => strtolower($n) . '@t.test', 'password' => 'x']);
        $mestre = $u('Mestre');
        $this->ana = $u('Ana');
        $this->fora = $u('Fora');
        $this->camp = Campanha::create(['nome' => 'Teste', 'mestre_id' => $mestre->id]);
        $this->camp->participantes()->create(['user_id' => $this->ana->id, 'status' => 'ativo']);
        $this->ficha = DB::table('fichas')->insertGetId(['usuario_id' => $this->ana->id, 'nome' => 'Lúcia', 'dinheiro' => 1000]);
        CampanhaFicha::create(['campanha_id' => $this->camp->id, 'ficha_id' => $this->ficha, 'user_id' => $this->ana->id]);
    }

    private function url(string $resto = ''): string
    {
        return '/campanhas/' . $this->camp->id . '/minijogos' . $resto;
    }

    private function dinheiro(): int
    {
        return (int) DB::table('fichas')->where('id', $this->ficha)->value('dinheiro');
    }

    private function rodada(): array
    {
        return Cache::get("minijogo:{$this->camp->id}:{$this->ana->id}");
    }

    public function test_mines_ganha_sacando_e_perde_na_mina(): void
    {
        $this->actingAs($this->fora)->get($this->url())->assertForbidden();
        $this->actingAs($this->ana)->get($this->url())->assertOk()->assertSee('Lúcia');

        $r = $this->postJson($this->url('/mines/iniciar'), ['aposta' => 100, 'ficha' => $this->ficha, 'minas' => 3])->assertOk();
        $this->assertArrayNotHasKey('bombas', $r->json('rodada')); // a tela nunca sabe onde estão as minas
        $this->assertSame(900, $this->dinheiro());
        $this->postJson($this->url('/slot/girar'), ['aposta' => 10, 'ficha' => $this->ficha])->assertStatus(422); // uma rodada por vez
        $this->postJson($this->url('/mines/sacar'))->assertStatus(422); // precisa abrir uma casa

        $segura = collect(range(0, 24))->diff($this->rodada()['bombas'])->first();
        $this->postJson($this->url('/mines/abrir'), ['casa' => $segura])->assertOk()->assertJsonPath('rodada.mult', 1.1);
        $this->postJson($this->url('/mines/sacar'))->assertOk()->assertJsonPath('fim.premio', 110);
        $this->assertSame(1010, $this->dinheiro());

        $this->postJson($this->url('/mines/iniciar'), ['aposta' => 50, 'ficha' => $this->ficha, 'minas' => 5])->assertOk();
        $r = $this->postJson($this->url('/mines/abrir'), ['casa' => $this->rodada()['bombas'][0]])->assertOk();
        $this->assertFalse($r->json('fim.ganhou'));
        $this->assertCount(5, $r->json('fim.bombas'));
        $this->assertSame(960, $this->dinheiro());
        $this->postJson($this->url('/mines/sacar'))->assertStatus(422);

        // sem Dinheiro suficiente, e personagem de outra pessoa
        $this->postJson($this->url('/mines/iniciar'), ['aposta' => 5000, 'ficha' => $this->ficha, 'minas' => 3])->assertStatus(422);
        $outra = DB::table('fichas')->insertGetId(['usuario_id' => $this->fora->id, 'nome' => 'Alheia', 'dinheiro' => 1000]);
        $this->postJson($this->url('/mines/iniciar'), ['aposta' => 10, 'ficha' => $outra, 'minas' => 3])->assertStatus(422);
        $this->assertSame(960, $this->dinheiro());
    }

    public function test_aviator_saca_antes_da_queda_ou_cai(): void
    {
        $this->actingAs($this->ana);
        JogoCarteira::create(['campanha_id' => $this->camp->id, 'user_id' => $this->ana->id, 'dinheiro' => 500]);
        $chave = "minijogo:{$this->camp->id}:{$this->ana->id}";

        // voo que cai em 3×: com 5 s de voo (≈1,49×) o saque paga
        $this->postJson($this->url('/aviator/iniciar'), ['aposta' => 100, 'ficha' => 0])->assertOk();
        $this->assertSame(400, (int) JogoCarteira::first()->dinheiro);
        Cache::put($chave, ['queda' => 3.0, 'inicio' => microtime(true) - 5.2] + $this->rodada());
        $this->postJson($this->url('/aviator/estado'))->assertOk()->assertJsonPath('rodada.jogo', 'aviator');
        $r = $this->postJson($this->url('/aviator/sacar'))->assertOk();
        $this->assertTrue($r->json('fim.ganhou'));
        $this->assertSame(149, $r->json('fim.premio'));
        $this->assertSame(549, (int) JogoCarteira::first()->dinheiro);

        // voo que já passou da queda: perde
        $this->postJson($this->url('/aviator/iniciar'), ['aposta' => 100, 'ficha' => 0])->assertOk();
        Cache::put($chave, ['queda' => 1.2, 'inicio' => microtime(true) - 10] + $this->rodada());
        $this->postJson($this->url('/aviator/estado'))->assertOk()->assertJsonPath('fim.ganhou', false)->assertJsonPath('fim.queda', 1.2);
        $this->assertSame(449, (int) JogoCarteira::first()->dinheiro);

        // saque automático em 1,5× com queda em 4×: paga 1,5× mesmo sem clicar
        $this->postJson($this->url('/aviator/iniciar'), ['aposta' => 100, 'ficha' => 0, 'auto' => 1.5])->assertOk();
        Cache::put($chave, ['queda' => 4.0, 'inicio' => microtime(true) - 20] + $this->rodada());
        $this->postJson($this->url('/aviator/estado'))->assertOk()->assertJsonPath('fim.premio', 150);
        $this->assertSame(499, (int) JogoCarteira::first()->dinheiro);
    }

    public function test_caca_niquel_tira_a_aposta_e_paga_o_premio(): void
    {
        $this->actingAs($this->ana);
        $antes = $this->dinheiro();
        for ($i = 0; $i < 20; $i++) {
            $r = $this->postJson($this->url('/slot/girar'), ['aposta' => 10, 'ficha' => $this->ficha])->assertOk();
            $antes += $r->json('fim.premio') - 10;
            $this->assertCount(3, $r->json('fim.rolos'));
            $this->assertSame($antes, $this->dinheiro());
        }
        $this->postJson($this->url('/roleta/girar'), [])->assertNotFound();
    }
}
