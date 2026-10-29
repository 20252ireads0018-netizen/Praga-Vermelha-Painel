<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\CampanhaCatalogo;
use App\Models\User;
use App\Support\ComparadorCatalogo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Disputa de catálogos: uma dica por catálogo, só para quem escreveu, que aponta o que mais tira pontos. */
class CatalogoDicaTest extends TestCase
{
    public function test_dica_aponta_o_que_tirou_pontos(): void
    {
        $slug = 'visao-astral';
        $real = ComparadorCatalogo::textoReal(config('maldicoes')[$slug]);
        $this->assertStringContainsString('perfeito', ComparadorCatalogo::dica("Visão astral, pessoal, olhos&ouvidos&mãos, intrometido. Impossível.\n" . $real, $slug));
        // texto sem os metadados: pede o que falta, sem inventar problema
        $this->assertStringContainsString('raridade', ComparadorCatalogo::dica($real, $slug));
        // texto de outra maldição: aponta a ideia central que faltou, com uma palavra do original
        $dica = ComparadorCatalogo::dica('Você sofre o dobro de dano de todos os inimigos enquanto estiver em combate.', $slug);
        $this->assertMatchesRegularExpression('/ideia central: algo ligado a "\p{L}{5,}"/u', $dica);
    }

    public function test_uma_dica_por_catalogo_so_para_o_dono(): void
    {
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
        (require database_path('migrations/2026_10_28_000007_create_campanha_catalogos.php'))->up();

        $mestre = User::create(['name' => 'Mestre', 'email' => 'm@t.test', 'password' => 'x']);
        $ana = User::create(['name' => 'Ana', 'email' => 'a@t.test', 'password' => 'x']);
        $bia = User::create(['name' => 'Bia', 'email' => 'b@t.test', 'password' => 'x']);
        $camp = Campanha::create(['nome' => 'T', 'mestre_id' => $mestre->id]);
        foreach ([$ana, $bia] as $j) {
            $camp->participantes()->create(['user_id' => $j->id, 'status' => 'ativo']);
        }
        $c = CampanhaCatalogo::create(['campanha_id' => $camp->id, 'user_id' => $ana->id, 'maldicao_slug' => 'visao-astral',
            'texto' => 'Você sofre o dobro de dano de todos os inimigos enquanto estiver em combate.', 'pontuacao' => 3, 'detalhes' => ['significado' => 3]]);
        $url = "/campanhas/{$camp->id}/catalogos";

        $this->actingAs($ana)->getJson($url)->assertJsonPath('itens.0.pode_dica', true)->assertJsonPath('itens.0.dica', null);
        // outra pessoa (nem o mestre) não abre a dica de quem escreveu
        $this->actingAs($bia)->postJson("$url/{$c->id}/dica")->assertForbidden();
        $this->actingAs($bia)->getJson($url)->assertJsonPath('itens.0.pode_dica', false);
        $this->actingAs($mestre)->postJson("$url/{$c->id}/dica")->assertForbidden();

        $dica = $this->actingAs($ana)->postJson("$url/{$c->id}/dica")->assertOk()->json('dica');
        $this->assertNotEmpty($dica);
        // a mesma dica volta: não dá para tirar outra do mesmo catálogo, nem depois de recalcular
        $this->postJson("$url/{$c->id}/dica")->assertOk()->assertJsonPath('dica', $dica);
        $this->actingAs($mestre)->postJson("$url/recalcular")->assertOk();
        $this->actingAs($ana)->getJson($url)->assertJsonPath('itens.0.dica', $dica)->assertJsonPath('itens.0.pode_dica', false);
    }
}
