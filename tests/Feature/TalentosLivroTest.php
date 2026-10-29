<?php

namespace Tests\Feature;

use App\Models\Personagem;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Catálogo de talentos do livro (config/talentos_livro.php): completo, servido em JSON e gravável na ficha. */
class TalentosLivroTest extends TestCase
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

    private function talento(string $grupo, string $nome): array
    {
        $t = collect(config("talentos_livro.talentos.$grupo"))->firstWhere('nome', $nome);
        $this->assertNotNull($t, "$nome não está em $grupo");
        return $t;
    }

    public function test_catalogo_tem_todos_os_grupos_e_talentos_validos(): void
    {
        $talentos = config('talentos_livro.talentos');
        // um grupo para os mundanos e um para cada arte marcial da ficha
        $this->assertEqualsCanonicalizing(array_merge(['mundano'], array_keys(Personagem::ARTES_MARCIAIS)), array_keys($talentos));
        $this->assertEqualsCanonicalizing(array_keys($talentos), array_keys(config('talentos_livro.grupos')));

        foreach ($talentos as $grupo => $lista) {
            $this->assertNotEmpty($lista, $grupo);
            foreach ($lista as $t) {
                $this->assertNotSame('', trim($t['nome']), $grupo);
                $this->assertLessThanOrEqual(120, mb_strlen($t['nome']));
                $this->assertNotSame('', trim($t['descricao']), $t['nome']);
                $this->assertIsInt($t['pagina'], $t['nome']);
                $this->assertTrue($t['custo'] === null || is_int($t['custo']), $t['nome']);
            }
        }

        // alguns de cada parte do livro
        $this->assertSame(10, $this->talento('mundano', 'Golpe sujo')['custo']);
        $this->assertSame('10 ou mais em furtividade', $this->talento('mundano', 'Golpe sujo')['requisito']);
        $this->talento('hexomancia', 'Autofagia amaldiçoada');
        $this->talento('hexomancia', 'Círculo de Hexomante');
        $this->talento('fisiomancia', 'Fisiomancia vegetal eficiente');
        $this->talento('fisiomancia', 'Fisiomancia monetária eficiente');
        $this->talento('esgrima', 'Santoryu');
        $this->talento('boxing', 'Guarda');
        $this->talento('wrestling', 'Braços longos');
        $this->talento('kuro_stomper', 'Pontos de ódio');
    }

    public function test_rota_entrega_o_catalogo_em_json(): void
    {
        $this->getJson('/livro/talentos')
            ->assertOk()
            ->assertJsonPath('grupos.hexomancia', 'Hexomancia')
            ->assertJsonPath('talentos.esgrima.0.nome', config('talentos_livro.talentos.esgrima.0.nome'));
    }

    public function test_talento_do_livro_salvo_na_ficha_continua_la_ao_reabrir(): void
    {
        $this->actingAs(User::create(['name' => 'Ana', 'email' => 'ana@t.test', 'password' => 'x']));
        $golpe = $this->talento('mundano', 'Golpe sujo');
        $circulo = $this->talento('hexomancia', 'Círculo de Hexomante'); // o texto mais longo do catálogo
        $descCirculo = $circulo['descricao'] . ($circulo['requisito'] ? "\n\nRequisito: " . $circulo['requisito'] : '');

        $this->post('/fichafeita', [
            'nome' => 'Kuro', 'raca' => 'Humano', 'idade' => 20, 'profissao' => 'Lutador', 'nivel_mundano' => 4,
            'talentos' => json_encode([[
                'tipo' => 'mundano', 'titulo' => $golpe['nome'], 'custo' => $golpe['custo'],
                'descricao' => $golpe['descricao'] . "\n\nRequisito: " . $golpe['requisito'], 'ativo' => false, 'pasta' => '',
            ]]),
            'artes_marciais' => json_encode(['hexomancia' => ['nivel' => 1, 'xp' => 0, 'talentos' => [
                ['nome' => $circulo['nome'], 'custo' => 0, 'descricao' => $descCirculo, 'ativo' => false],
            ]]]),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $p = Personagem::firstOrFail()->fresh();
        $this->assertSame('Golpe sujo', $p->talentos[0]['titulo']);
        $this->assertStringContainsString('Requisito: 10 ou mais em furtividade', $p->talentos[0]['descricao']);
        $this->assertSame($descCirculo, $p->artes_marciais['hexomancia']['talentos'][0]['descricao']);
        $this->assertSame([], $p->artes_marciais['esgrima']['talentos']);
    }
}
