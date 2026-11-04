<?php

namespace Tests\Feature;

use App\Models\Personagem;
use App\Models\User;
use App\Support\Esquema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Inspiração: contador gastável da ficha, que o jogador aumenta ou diminui pelos botões − e +. */
class FichaInspiracaoTest extends TestCase
{
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
        Schema::create('fichas', function (Blueprint $t) {
            $t->id();
            foreach ((new Personagem())->getFillable() as $c) {
                if ($c !== 'inspiracao') {
                    $t->text($c)->nullable();
                }
            }
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();
        });
        (require database_path('migrations/2026_11_04_000030_fichas_inspiracao.php'))->up();
        // a tela da ficha consulta as campanhas do jogador (acesso às maldições)
        Schema::create('campanhas', fn (Blueprint $t) => $t->id() && $t->foreignId('mestre_id') && $t->timestamps());
        Schema::create('campanha_jogadores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campanha_id');
            $t->foreignId('user_id');
            $t->string('status', 20)->default('pendente');
            $t->timestamps();
        });
    }

    private function ficha(array $extra = []): array
    {
        return $extra + ['nome' => 'Kuro', 'raca' => 'Humano', 'idade' => 20, 'profissao' => 'Lutador', 'nivel_mundano' => 4];
    }

    public function test_inspiracao_e_salva_nao_fica_negativa_e_aparece_na_ficha(): void
    {
        $this->actingAs(User::create(['name' => 'Ana', 'email' => 'ana@t.test', 'password' => 'x']));
        $this->post('/fichafeita', $this->ficha(['inspiracao' => 3]))->assertRedirect();
        $p = Personagem::firstOrFail();
        $this->assertSame(3, $p->inspiracao);

        // gastar uma
        $this->putJson("/personagens/{$p->id}", $this->ficha(['inspiracao' => 2]))->assertOk();
        $this->assertSame(2, $p->fresh()->inspiracao);
        // abaixo de zero é recusado
        $this->putJson("/personagens/{$p->id}", $this->ficha(['inspiracao' => -1]))->assertStatus(422);
        // gravação sem o campo (outra tela) não mexe
        $this->putJson("/personagens/{$p->id}", $this->ficha())->assertOk();
        $this->assertSame(2, $p->fresh()->inspiracao);

        $this->get("/personagens/{$p->id}")->assertOk()
            ->assertSee('Inspiração')->assertSee('id="inspiracao-valor"', false)->assertSee('name="inspiracao" value="2"', false);
    }
}
