<?php

namespace Tests\Feature;

use App\Models\Campanha;
use App\Models\CampanhaFicha;
use App\Models\TimeSkipResposta;
use App\Models\User;
use App\Support\TimeSkipCalc;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TimeSkipTest extends TestCase
{
    private User $mestre;
    private User $ana;
    private Campanha $camp;
    private int $fichaAna;
    private int $fichaMestre;

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
            $t->foreignId('user_id');
            $t->string('nome');
            $t->timestamps();
        });
        (require database_path('migrations/2026_10_26_000000_create_campanhas_tables.php'))->up();
        (require database_path('migrations/2026_11_02_000023_create_time_skips.php'))->up();
        $u = fn ($n) => User::create(['name' => $n, 'email' => strtolower($n) . '@t.test', 'password' => 'x']);
        $this->mestre = $u('Mestre');
        $this->ana = $u('Ana');
        $this->camp = Campanha::create(['nome' => 'Teste', 'mestre_id' => $this->mestre->id]);
        $this->camp->participantes()->create(['user_id' => $this->ana->id, 'status' => 'ativo']);
        $this->fichaAna = DB::table('fichas')->insertGetId(['user_id' => $this->ana->id, 'nome' => 'Lúcia']);
        $this->fichaMestre = DB::table('fichas')->insertGetId(['user_id' => $this->mestre->id, 'nome' => 'NPC']);
        CampanhaFicha::create(['campanha_id' => $this->camp->id, 'ficha_id' => $this->fichaAna, 'user_id' => $this->ana->id]);
    }

    private function url(string $resto = ''): string
    {
        return '/campanhas/' . $this->camp->id . '/timeskips' . $resto;
    }

    public function test_mestre_monta_do_modelo_e_jogador_escolhe_dentro_das_regras(): void
    {
        $this->actingAs($this->mestre)->get($this->url())->assertOk()->assertSee('Começar de um modelo');
        $r = $this->postJson($this->url(), ['nome' => 'Dez anos', 'modelo' => 'dez_anos'])->assertOk();
        $id = $r->json('skip.id');
        $this->assertSame('rascunho', $r->json('skip.status'));
        $this->assertSame(20, $r->json('skip.config.escolhas'));
        $this->assertCount(3, $r->json('skip.config.ramos'));
        $this->assertCount(10, $r->json('skip.config.propriedades'));

        // rascunho: a jogadora não vê nem escolhe
        $this->actingAs($this->ana)->get($this->url())->assertOk()->assertDontSee('Dez anos');
        $this->postJson($this->url("/$id/resposta"), ['ficha_id' => $this->fichaAna, 'dados' => []])->assertStatus(422);
        $this->postJson($this->url(), ['nome' => 'X'])->assertStatus(403);

        $this->actingAs($this->mestre)->postJson($this->url(), ['id' => $id, 'nome' => 'Dez anos', 'status' => 'aberto'])->assertOk();
        $this->assertSame(20, $this->postJson($this->url(), ['id' => $id, 'nome' => 'Dez anos'])->json('skip.config.escolhas')); // mudar só o status mantém as regras

        $dados = ['ramo' => 'politico', 'salario' => 3000, 'dinheiro_inicial' => 150000,
            'atividades' => ['influencia' => 8, 'empresa' => 4, 'trabalho' => 5, 'patente' => 4, 'convocar_reuniao' => 1, 'inventada' => 3],
            'propriedades' => ['casa' => 2]];
        $this->actingAs($this->ana);
        // ficha de outra pessoa, atividade de outro ramo e escolhas demais são recusadas
        $this->postJson($this->url("/$id/resposta"), ['ficha_id' => $this->fichaMestre, 'dados' => $dados])->assertStatus(422);
        $this->postJson($this->url("/$id/resposta"), ['ficha_id' => $this->fichaAna, 'dados' => ['ramo' => 'politico', 'atividades' => ['formar_exercito' => 1]]])
            ->assertStatus(422)->assertJsonFragment(['erro' => 'Formar exército exige Vida militar.']);
        $this->postJson($this->url("/$id/resposta"), ['ficha_id' => $this->fichaAna, 'dados' => ['atividades' => ['patente' => 21]]])->assertStatus(422);

        $r = $this->postJson($this->url("/$id/resposta"), ['ficha_id' => $this->fichaAna, 'dados' => $dados])->assertOk();
        $salvo = $r->json('resposta.dados');
        $this->assertArrayNotHasKey('inventada', $salvo['atividades']);
        $this->postJson($this->url("/$id/resposta"), ['ficha_id' => $this->fichaAna, 'dados' => $dados])->assertOk(); // regravar não duplica
        $this->assertSame(1, TimeSkipResposta::count());

        // contas: casa dá +4 escolhas (24 usadas de 24); empresa +4000 no salário; trabalho +15 salários inteiros
        $c = TimeSkipCalc::calcular(\App\Models\TimeSkip::find($id)->config, $salvo);
        $this->assertSame([24, 24, 60, 609000, 250000, 509000], [$c['escolhas'], $c['usadas'], $c['fator'], $c['renda'], $c['gasto'], $c['saldo']]);
        $this->assertSame([8.0, 2.0, 2.0, 3.0], [$c['totais']['Mãos erguidas em votação'], $c['totais']['Escalas de patente'], $c['totais']['Níveis mundanos'], $c['totais']['Ameaça de Maldição']]);
        $this->assertSame([], $c['erros']);

        // fechado: só leitura
        $this->actingAs($this->mestre)->postJson($this->url(), ['id' => $id, 'nome' => 'Dez anos', 'status' => 'fechado'])->assertOk();
        $this->actingAs($this->ana)->postJson($this->url("/$id/resposta"), ['ficha_id' => $this->fichaAna, 'dados' => $dados])->assertStatus(422);
        $this->get($this->url())->assertOk()->assertSee('Dez anos');

        $this->deleteJson($this->url("/$id"))->assertStatus(403);
        $this->actingAs($this->mestre)->deleteJson($this->url("/$id"))->assertOk();
        $this->assertSame(0, TimeSkipResposta::count());
    }

    public function test_mestre_cria_time_skip_proprio_e_regras_sao_limpas(): void
    {
        $r = $this->actingAs($this->mestre)->postJson($this->url(), ['nome' => 'Um ano no exílio', 'config' => [
            'escolhas' => 5, 'meses' => 12, 'fator' => 50, 'invasor' => 'x',
            'ramos' => [['id' => 'exilio', 'nome' => 'Exílio']],
            'atividades' => [
                ['nome' => 'Caçar', 'efeitos' => [['rotulo' => 'Comida', 'valor' => 2, 'a_cada' => 2], ['rotulo' => 'Ouro', 'valor' => 100, 'tipo' => 'dinheiro']]],
                ['nome' => 'Caçar', 'ramo' => 'nao_existe', 'custo' => 0],
                ['nome' => 'Mercenário', 'fator' => 90],
            ],
            'propriedades' => [['nome' => 'Cabana', 'preco' => 500, 'ramo' => 'exilio', 'efeitos' => [['rotulo' => '', 'valor' => 1, 'tipo' => 'escolhas']]]],
        ]])->assertOk();
        $cfg = $r->json('skip.config');
        $this->assertArrayNotHasKey('invasor', $cfg);
        $this->assertSame(['cacar', 'cacar_2', 'mercenario'], array_column($cfg['atividades'], 'id'));
        $this->assertSame(['', 1], [$cfg['atividades'][1]['ramo'], $cfg['atividades'][1]['custo']]);

        $c = TimeSkipCalc::calcular($cfg, ['ramo' => 'exilio', 'salario' => 1000, 'atividades' => ['cacar' => 3, 'mercenario' => 1], 'propriedades' => ['cabana' => 2]]);
        // 5 + 2 (cabana) escolhas; dinheiro: 1000 × 12 × 90% + 300 de ouro − 1000 da cabana
        $this->assertSame([7, 4, 90, 11100, 1000, 10100], [$c['escolhas'], $c['usadas'], $c['fator'], $c['renda'], $c['gasto'], $c['saldo']]);
        $this->assertSame(['Comida' => 2.0], $c['totais']);
        $this->assertSame(['Cabana exige Exílio.'], TimeSkipCalc::calcular($cfg, ['propriedades' => ['cabana' => 1], 'dinheiro_inicial' => 600])['erros']);
    }
}
