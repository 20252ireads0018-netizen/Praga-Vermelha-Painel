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

    public function test_item_guarda_as_caracteristicas_e_os_efeitos(): void
    {
        $this->actingAs(User::create(['name' => 'Ana', 'email' => 'ana@t.test', 'password' => 'x']));
        $item = ['nome' => 'Machado', 'descricao' => 'Pesado.', 'categoria' => 'Arma', 'qtd_dados' => 1, 'lados_dado' => 10, 'valor_fixo' => 2,
            'critico' => '19–20, ×2', 'tipo_dano' => 'cortante', 'condicao_especial' => 'sangramento', 'tamanho' => 'grande', 'acao_uso' => 'ação padrão',
            'alcance_armadura' => '1–40', 'incremento_defesa' => '+2', 'penalidade_destreza' => '−5', 'peso' => '3 kg',
            'efeitos_mundanos' => '+5 em Luta', 'efeitos_amaldicoados' => 'Drena 5 de Aura por golpe'];
        $this->post('/fichafeita', $this->ficha(['itens' => json_encode([$item])]))->assertRedirect();
        $p = Personagem::firstOrFail();
        $this->assertEqualsCanonicalizing($item, $p->itens[0]);

        $this->get("/personagens/{$p->id}")->assertOk()
            ->assertSee('id="item-efeitos-amaldicoados"', false)->assertSee('Alcance da Armadura')->assertSee('Drena 5 de Aura por golpe');
        // texto longo demais é recusado
        $this->putJson("/personagens/{$p->id}", $this->ficha(['itens' => json_encode([['nome' => 'X', 'peso' => str_repeat('a', 101)]])]))->assertStatus(422);
    }
}
