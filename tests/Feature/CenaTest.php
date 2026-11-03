<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CenaTest extends TestCase
{
    public function test_mestre_dispara_cena_para_todos_ou_para_um_jogador(): void
    {
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
        $u = fn ($n) => User::create(['name' => $n, 'email' => strtolower($n) . '@t.test', 'password' => 'x']);
        [$mestre, $ana, $bia, $fora] = [$u('Mestre'), $u('Ana'), $u('Bia'), $u('Fora')];
        $camp = Campanha::create(['nome' => 'Teste', 'mestre_id' => $mestre->id]);
        foreach ([$ana, $bia] as $j) {
            $camp->participantes()->create(['user_id' => $j->id, 'status' => 'ativo']);
        }
        $url = '/campanhas/' . $camp->id;

        $this->actingAs($ana)->getJson("$url/estado")->assertOk()->assertJsonPath('cena', null);
        $this->postJson("$url/cena", ['tipo' => 'descobriu'])->assertStatus(403); // só o mestre dispara

        $this->actingAs($mestre)->postJson("$url/cena", ['tipo' => 'voar'])->assertStatus(422);
        $this->postJson("$url/cena", ['tipo' => 'matar', 'alvo' => $fora->id])->assertStatus(422);
        $this->postJson("$url/cena", ['tipo' => 'descobriu', 'texto' => 'o selo no porão'])->assertOk()->assertJsonPath('seq', 1);

        $c = $this->actingAs($ana)->getJson("$url/estado")->json('cena');
        $this->assertSame([1, 'descobriu', 'Você descobriu…', 'o selo no porão'], [$c['seq'], $c['tipo'], $c['titulo'], $c['texto']]);
        $this->assertArrayNotHasKey('alvo', $c);

        // para um jogador só: a outra não recebe
        $this->actingAs($mestre)->postJson("$url/cena", ['tipo' => 'matar', 'texto' => 'o Carniçal', 'titulo' => 'Acabe com ele', 'alvo' => $bia->id])->assertOk();
        $this->assertNull($this->actingAs($ana)->getJson("$url/estado")->json('cena'));
        $c = $this->actingAs($bia)->getJson("$url/estado")->json('cena');
        $this->assertSame([2, 'matar', 'Acabe com ele'], [$c['seq'], $c['tipo'], $c['titulo']]);
    }
}
