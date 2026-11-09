<?php

namespace Tests\Feature;

use App\Models\Personagem;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Aprimoramentos guardados (usáveis em qualquer técnica), carreira na AMA (patente, salário, exércitos) e porte/idade sem resets. */
class FichaAprimoramentosCarreiraTest extends TestCase
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

    public function test_aprimoramentos_guardados_e_usos_nas_tecnicas(): void
    {
        $this->actingAs(User::create(['name' => 'Ana', 'email' => 'ana@t.test', 'password' => 'x']));
        $guardados = [
            ['id' => 'k1', 'nome' => 'Lâmina oculta', 'tipo' => 'fixo', 'custo' => 7, 'acumulativo' => true],
            ['id' => 'k2', 'nome' => 'Eco', 'tipo' => 'original', 'custo' => 3, 'acumulativo' => false],
        ];
        $tecnica = ['nome' => 'Corte', 'tipo' => 'hexomancia', 'custo' => 5, 'gerais' => ['dano' => ['qtd' => 1, 'gratis' => 0], 's_k1' => ['qtd' => 2, 'gratis' => 1]]];
        $this->post('/fichafeita', $this->ficha(['aprimoramentos' => json_encode($guardados), 'tecnicas' => json_encode([$tecnica])]))->assertRedirect();
        $p = Personagem::firstOrFail();
        $this->assertSame($guardados, $p->aprimoramentos);
        $this->assertSame(['qtd' => 2, 'gratis' => 1], $p->tecnicas[0]['gerais']['s_k1']);

        // id fora do padrão é recusado; gravar sem o campo não apaga
        $this->putJson("/personagens/{$p->id}", $this->ficha(['aprimoramentos' => json_encode([['id' => 'X Y', 'nome' => 'a']])]))->assertStatus(422);
        $this->putJson("/personagens/{$p->id}", $this->ficha())->assertOk();
        $this->assertCount(2, $p->fresh()->aprimoramentos);

        $this->get("/personagens/{$p->id}")->assertOk()
            ->assertSee('data-aba="aprimoramentos"', false)->assertSee('Lâmina oculta')->assertSee('botao-editar-tecnica', false);
    }

    public function test_carreira_na_ama_com_patente_salario_e_exercitos(): void
    {
        $this->actingAs(User::create(['name' => 'Ana', 'email' => 'ana@t.test', 'password' => 'x']));
        $carreira = ['patente' => 3, 'grau' => 2, 'salario' => 5000.5, 'exercitos' => [
            ['nome' => 'Legião Rubra', 'descricao' => 'Hexomantes do sul', 'nivel' => 4, 'aprimoramentos' => '+1 nível por doação'],
        ]];
        $this->post('/fichafeita', $this->ficha(['carreira_ama' => json_encode($carreira)]))->assertRedirect();
        $p = Personagem::firstOrFail();
        $this->assertEquals($carreira, $p->carreira_ama);

        $this->putJson("/personagens/{$p->id}", $this->ficha(['carreira_ama' => json_encode(['patente' => 11])]))->assertStatus(422);
        $this->get("/personagens/{$p->id}")->assertOk()
            ->assertSee('Patente e salário')->assertSee('Sargento')->assertSee('Rubra')->assertSee('Ganho de patente por nota');
    }

    public function test_idade_e_porte_nao_zeram_mais_a_ficha(): void
    {
        $this->actingAs(User::create(['name' => 'Ana', 'email' => 'ana@t.test', 'password' => 'x']));
        $this->post('/fichafeita', $this->ficha())->assertRedirect();
        $html = $this->get('/personagens/' . Personagem::firstOrFail()->id)->assertOk()->getContent();
        $this->assertStringNotContainsString('vamos redefinir sua distribuicao de atributos', $html);
        $this->assertStringNotContainsString('antes de aplicar sua idade', $html);
    }
}
