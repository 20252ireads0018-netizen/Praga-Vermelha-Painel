<?php

namespace Tests\Feature;

use App\Models\Personagem;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Valores extras das habilidades: livres, com a origem de cada um, e somados nas rolagens. */
class FichaHabilidadesExtrasTest extends TestCase
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
                $t->text($c)->nullable();
            }
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();
        });
    }

    private function ficha(array $extra = []): array
    {
        return $extra + ['nome' => 'Kuro', 'raca' => 'Humano', 'idade' => 20, 'profissao' => 'Lutador', 'nivel_mundano' => 4, 'luta' => 30];
    }

    public function test_extras_com_origem_sao_salvos_limpos_e_entram_na_rolagem(): void
    {
        $this->actingAs(User::create(['name' => 'Ana', 'email' => 'ana@t.test', 'password' => 'x']));
        $extras = [
            'luta' => [['origem' => 'Talento Punho de Ferro', 'valor' => 15], ['origem' => 'Anel do avô', 'valor' => 300], ['origem' => '', 'valor' => 0]],
            'labia' => [['origem' => 'Maldição', 'valor' => -5]],
            'inventada' => [['origem' => 'x', 'valor' => 9]],
        ];
        $this->post('/fichafeita', $this->ficha(['habilidades_extras' => json_encode($extras)]))->assertRedirect();
        $p = Personagem::firstOrFail();
        // sem limite de pontos; vazios e habilidades desconhecidas somem
        $this->assertSame([
            'luta'  => [['origem' => 'Talento Punho de Ferro', 'valor' => 15], ['origem' => 'Anel do avô', 'valor' => 300]],
            'labia' => [['origem' => 'Maldição', 'valor' => -5]],
        ], $p->habilidades_extras);
        $this->assertSame(315, $p->extraHabilidade('luta'));
        $this->assertSame(30, (int) $p->habilidades['luta']); // os pontos distribuídos não mudam
        $luta = collect($p->habilidadesParaRolagem())->firstWhere('chave', 'luta');
        $this->assertSame([30, 315], [$luta['valor'], $luta['extra']]);
        $this->assertSame(30 + 315 + $luta['mod'], $luta['total']);

        // gravação sem o campo (outra tela) não apaga; com o campo vazio, apaga
        $this->putJson("/personagens/{$p->id}", $this->ficha())->assertOk();
        $this->assertSame(315, $p->fresh()->extraHabilidade('luta'));
        $this->putJson("/personagens/{$p->id}", $this->ficha(['habilidades_extras' => '{}']))->assertOk();
        $this->assertSame([], $p->fresh()->habilidades_extras);

        // editar de novo troca a lista inteira (somar, tirar e apagar origens acontece na tela)
        $this->putJson("/personagens/{$p->id}", $this->ficha(['habilidades_extras' => json_encode(['luta' => [['origem' => 'Bênção', 'valor' => 7]]])]))->assertOk();
        $this->assertSame(['luta' => [['origem' => 'Bênção', 'valor' => 7]]], $p->fresh()->habilidades_extras);
    }
}
