<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TrilhaTest extends TestCase
{
    private User $mestre;
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
        (require database_path('migrations/2026_10_26_000000_create_campanhas_tables.php'))->up();
        (require database_path('migrations/2026_10_28_000004_add_som_evento_to_campanhas.php'))->up();
        (require database_path('migrations/2026_11_02_000022_campanhas_trilha.php'))->up();
        $u = fn ($n) => User::create(['name' => $n, 'email' => strtolower($n) . '@t.test', 'password' => 'x']);
        $this->mestre = $u('Mestre');
        $this->ana = $u('Ana');
        $this->camp = Campanha::create(['nome' => 'Teste', 'mestre_id' => $this->mestre->id]);
        $this->camp->participantes()->create(['user_id' => $this->ana->id, 'status' => 'ativo']);
    }

    public function test_mestre_toca_pausa_e_jogador_recebe(): void
    {
        $url = '/campanhas/' . $this->camp->id;
        $this->actingAs($this->mestre)->postJson("$url/trilha", ['acao' => 'link', 'url' => 'nada'])->assertStatus(422);
        $r = $this->postJson("$url/trilha", ['acao' => 'link', 'url' => 'https://youtu.be/dQw4w9WgXcQ?t=3', 'titulo' => 'Tema'])->assertOk();
        $this->assertSame([['dQw4w9WgXcQ', 'Tema']], array_map(fn ($l) => [$l['yt'], $l['titulo']], $r->json('trilha.links')));
        $this->postJson("$url/trilha", ['acao' => 'tocar', 'tipo' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'titulo' => 'Tema'])->assertOk();
        $this->postJson("$url/trilha", ['acao' => 'pausar', 'pos' => 42])->assertOk();

        // o jogador não controla, mas recebe o estado no polling
        $this->actingAs($this->ana)->postJson("$url/trilha", ['acao' => 'continuar'])->assertStatus(403);
        $t = $this->getJson("$url/estado")->assertOk()->json('trilha');
        $this->assertSame(['youtube', 'dQw4w9WgXcQ', false, 42], [$t['tipo'], $t['yt'], $t['tocando'], (int) $t['pos']]);

        $this->actingAs($this->mestre)->postJson("$url/trilha", ['acao' => 'continuar'])->assertOk();
        $this->assertTrue($this->getJson("$url/estado")->json('trilha.tocando'));
        $this->postJson("$url/trilha", ['acao' => 'parar'])->assertOk();
        $this->assertNull($this->getJson("$url/estado")->json('trilha.tipo'));
    }
}
