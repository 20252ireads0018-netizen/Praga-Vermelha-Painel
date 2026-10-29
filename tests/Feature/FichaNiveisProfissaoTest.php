<?php

namespace Tests\Feature;

use App\Models\Personagem;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** A distribuição dos níveis mundanos entre as profissões é salva do jeito que o jogador escolheu. */
class FichaNiveisProfissaoTest extends TestCase
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
        // a ficha tem dezenas de colunas: aqui só as que o formulário grava, todas como texto
        Schema::create('fichas', function (Blueprint $t) {
            $t->id();
            foreach ((new Personagem())->getFillable() as $c) {
                $t->text($c)->nullable();
            }
            $t->unsignedBigInteger('user_id')->nullable(); // dono, preenchido pelo model
            $t->timestamps();
        });
    }

    private function ficha(array $extra = []): array
    {
        return $extra + ['nome' => 'Kuro', 'raca' => 'Humano', 'idade' => 20, 'profissao' => 'Lutador', 'nivel_mundano' => 4];
    }

    public function test_salva_e_mantem_a_distribuicao_escolhida(): void
    {
        $this->actingAs(User::create(['name' => 'Ana', 'email' => 'ana@t.test', 'password' => 'x']));
        $niveis = ['Lutador', 'Criminoso', 'Criminoso', 'Construtor'];
        $this->post('/fichafeita', $this->ficha(['niveis_profissao' => json_encode($niveis)]))->assertRedirect();
        $p = Personagem::firstOrFail();
        $this->assertSame($niveis, $p->niveis_profissao);

        // editar a ficha grava a nova distribuição
        $outra = ['Intelectual', 'Criminoso', 'Comunicador', 'Construtor'];
        $this->putJson("/personagens/{$p->id}", $this->ficha(['niveis_profissao' => json_encode($outra)]))->assertOk();
        $this->assertSame($outra, $p->fresh()->niveis_profissao);

        // gravação que não manda o campo (outra tela) não apaga o que está salvo
        $this->putJson("/personagens/{$p->id}", $this->ficha())->assertOk();
        $this->assertSame($outra, $p->fresh()->niveis_profissao);

        // profissão que não existe é recusada
        $this->putJson("/personagens/{$p->id}", $this->ficha(['niveis_profissao' => json_encode(['Pirata'])]))->assertStatus(422);
    }
}
