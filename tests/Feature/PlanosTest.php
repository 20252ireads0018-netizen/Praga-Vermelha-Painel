<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CompraLivro;
use App\Support\Plano;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Planos: teste de 45 dias, grátis com limites e anúncios, vitalício com a chave do livro do sistema; boas-vindas. */
class PlanosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'array', 'cache.default' => 'array', 'planos.lancamento' => '2026-10-29', 'livro.chave' => 'chave-do-livro']);
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->string('chave_maldicoes')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        (require database_path('migrations/2026_10_29_000015_planos_dos_usuarios.php'))->up();
        Schema::create('fichas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable();
            $t->string('nome')->nullable();
            $t->timestamps();
        });
        Schema::create('campanhas', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->text('descricao')->nullable();
            $t->foreignId('mestre_id');
            $t->boolean('sessao_ativa')->default(false);
            $t->timestamps();
        });
        Schema::create('campanha_jogadores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campanha_id');
            $t->foreignId('user_id');
            $t->string('status', 20)->default('pendente');
            $t->timestamps();
        });
        Plano::esquecerColunas();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Plano::esquecerColunas();
        CompraLivro::esquecerColunas();
        parent::tearDown();
    }

    private function usuario(string $criadoEm, array $extra = []): User
    {
        $u = User::create(['name' => 'Ana', 'email' => uniqid() . '@t.test', 'password' => 'x'] + $extra);
        $u->forceFill(['created_at' => $criadoEm])->save();

        return $u->fresh();
    }

    public function test_teste_de_45_dias_e_depois_gratis(): void
    {
        Carbon::setTestNow('2026-11-10 12:00');
        $nova = $this->usuario('2026-11-10 12:00');
        $this->assertSame(Plano::TESTE, Plano::de($nova));
        $this->assertFalse(Plano::veAnuncios($nova));
        $this->assertNull(Plano::limite($nova, 'fichas'));
        $this->assertSame(45, Plano::diasDeTeste($nova));

        // conta antiga ganha o teste a partir do lançamento dos planos
        $antiga = $this->usuario('2025-01-01 00:00');
        $this->assertSame(Plano::TESTE, Plano::de($antiga));
        $this->assertSame('2026-12-13', Plano::testeAte($antiga)->toDateString());

        Carbon::setTestNow('2026-12-26 12:00');
        $this->assertSame(Plano::GRATIS, Plano::de($nova));
        $this->assertTrue(Plano::veAnuncios($nova));
        $this->assertSame(5, Plano::limite($nova, 'fichas'));
        $this->assertSame(1, Plano::limite($nova, 'campanhas'));
        $this->assertTrue(Plano::veAnuncios(null)); // visitante
    }

    public function test_plano_gratis_limita_fichas_e_campanhas(): void
    {
        Carbon::setTestNow('2027-06-01 12:00');
        $u = $this->usuario('2026-11-01 00:00', ['objetivo' => 'jogar']);
        $this->assertSame(Plano::GRATIS, Plano::de($u));

        for ($i = 0; $i < 4; $i++) {
            DB::table('fichas')->insert(['user_id' => $u->id, 'nome' => "F$i"]);
        }
        DB::table('fichas')->insert(['user_id' => $u->id + 99, 'nome' => 'de outra conta']);
        $this->actingAs($u)->get('/fichas')->assertOk(); // 4 de 5: ainda cabe uma
        DB::table('fichas')->insert(['user_id' => $u->id, 'nome' => 'F5']);
        $this->get('/fichas')->assertRedirect(route('planos.index'));
        $this->post('/fichafeita', ['nome' => 'Sexta'])->assertRedirect(route('planos.index'));

        $this->post('/campanhas', ['nome' => 'Primeira'])->assertRedirect();
        $this->assertSame(1, DB::table('campanhas')->where('mestre_id', $u->id)->count());
        $this->from('/campanhas')->post('/campanhas', ['nome' => 'Segunda'])->assertRedirect('/campanhas')->assertSessionHasErrors('nome');
        $this->assertSame(1, DB::table('campanhas')->where('mestre_id', $u->id)->count());

        // em teste não há limite
        Carbon::setTestNow('2026-11-02 12:00');
        $this->get('/fichas')->assertOk();
    }

    private function prepararCompra(): void
    {
        (require database_path('migrations/2026_10_29_000016_compra_do_livro.php'))->up();
        CompraLivro::esquecerColunas();
        config(['asaas.api_key' => 'chave-falsa', 'asaas.environment' => 'sandbox', 'asaas.webhook_token' => 'token-webhook']);
    }

    public function test_chave_do_livro_nao_da_o_vitalicio(): void
    {
        Carbon::setTestNow('2027-06-01 12:00');
        $u = $this->usuario('2026-11-01 00:00', ['objetivo' => 'livros']);

        $this->actingAs($u)->post('/livro/acesso', ['chave' => 'chave-do-livro'])->assertRedirect(route('livro.index'));
        $this->assertNull($u->fresh()->livro_comprado_em);
        $this->assertSame(Plano::GRATIS, Plano::de($u->fresh()));
    }

    public function test_pagamento_no_asaas_ativa_o_vitalicio_para_sempre(): void
    {
        $this->prepararCompra();
        Carbon::setTestNow('2027-06-01 12:00');
        $u = $this->usuario('2026-11-01 00:00', ['objetivo' => 'livros']);
        $pagina = 'https://sandbox.asaas.com/i/abc123';
        $status = 'PENDING';
        Http::fake([
            '*/customers?*'   => Http::response(['data' => []]),
            '*/customers'     => Http::response(['id' => 'cus_1']),
            '*/payments'      => Http::response(['id' => 'pay_1', 'status' => 'PENDING', 'value' => 6.99, 'invoiceUrl' => $pagina, 'externalReference' => "livro:user:{$u->id}"]),
            '*/payments/pay_1' => function () use (&$status, $pagina, $u) {
                return Http::response(['id' => 'pay_1', 'status' => $status, 'value' => 6.99, 'invoiceUrl' => $pagina, 'externalReference' => "livro:user:{$u->id}"]);
            },
        ]);

        $this->actingAs($u)->get('/historias/o-cavaleiro-negro')->assertRedirect(route('planos.index'));
        $this->get('/historias/o-cavaleiro-negro/pdf')->assertForbidden();
        $this->get('/planos')->assertOk()->assertSee('Comprar por R$ 6,99');

        // sem CPF: pede os dados; CPF inválido não passa
        $this->post('/planos/livro/comprar')->assertRedirect(route('livro.compra.dados'));
        $this->post('/planos/livro/dados', ['cpf_cnpj' => '111.111.111-11'])->assertSessionHasErrors('cpf_cnpj');
        $this->post('/planos/livro/dados', ['cpf_cnpj' => '529.982.247-25'])->assertRedirect($pagina);
        $this->assertSame('52998224725', $u->fresh()->cpf_cnpj);
        $this->assertSame('pay_1', $u->fresh()->asaas_cobranca_id);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payments') && $r['value'] == 6.99 && $r['externalReference'] === "livro:user:{$u->id}");

        // clicar de novo reaproveita a mesma fatura em aberto
        $this->post('/planos/livro/comprar')->assertRedirect($pagina);
        Http::assertSentCount(4); // busca cliente, cria cliente, cria cobrança, consulta a cobrança aberta
        $this->assertSame(Plano::GRATIS, Plano::de($u->fresh()));

        // webhook com token errado é recusado; com o certo libera
        $this->postJson('/webhooks/asaas', ['event' => 'PAYMENT_RECEIVED', 'payment' => ['id' => 'pay_1', 'externalReference' => "livro:user:{$u->id}"]],
            ['asaas-access-token' => 'errado'])->assertStatus(401);
        $this->assertSame(Plano::GRATIS, Plano::de($u->fresh()));
        $this->postJson('/webhooks/asaas', ['event' => 'PAYMENT_RECEIVED', 'payment' => ['id' => 'pay_1', 'status' => 'RECEIVED', 'externalReference' => "livro:user:{$u->id}"]],
            ['asaas-access-token' => 'token-webhook'])->assertOk();
        $u = $u->fresh();
        $this->assertNotNull($u->livro_comprado_em);
        $this->assertSame(Plano::VITALICIO, Plano::de($u));

        // estorno depois não tira o plano
        $this->postJson('/webhooks/asaas', ['event' => 'PAYMENT_REFUNDED', 'payment' => ['id' => 'pay_1', 'status' => 'REFUNDED', 'externalReference' => "livro:user:{$u->id}"]],
            ['asaas-access-token' => 'token-webhook'])->assertOk();
        $this->assertSame(Plano::VITALICIO, Plano::de($u->fresh()));
        $this->assertFalse(Plano::veAnuncios($u));
        $this->assertNull(Plano::limite($u, 'fichas'));

        // em outra sessão (sem a chave na sessão), a conta continua com tudo liberado
        $this->flushSession();
        $this->actingAs($u)->get('/historias/o-cavaleiro-negro')->assertOk();
        $this->get('/livro')->assertOk();
        $this->get('/planos')->assertOk()->assertSee('Vitalício');
    }

    public function test_volta_da_fatura_confere_o_pagamento_sem_webhook(): void
    {
        $this->prepararCompra();
        Carbon::setTestNow('2027-06-01 12:00');
        $u = $this->usuario('2026-11-01 00:00', ['objetivo' => 'livros', 'cpf_cnpj' => '52998224725']);
        $u->forceFill(['asaas_cobranca_id' => 'pay_9'])->save();
        Http::fake(['*/payments/pay_9' => Http::response(['id' => 'pay_9', 'status' => 'CONFIRMED', 'externalReference' => "livro:user:{$u->id}"])]);

        $this->actingAs($u)->get('/planos')->assertOk()->assertSee('Pagamento confirmado');
        $this->assertSame(Plano::VITALICIO, Plano::de($u->fresh()));
        $this->get('/fichas')->assertOk();
    }

    public function test_iniciante_escolhe_entre_livros_e_jogar(): void
    {
        $this->post('/cadastro', [
            'name' => 'Novo', 'email' => 'novo@t.test', 'password' => 'senha-forte-1', 'password_confirmation' => 'senha-forte-1',
        ])->assertRedirect(route('boas-vindas'));

        $this->get('/')->assertRedirect(route('boas-vindas'));
        $this->get('/boas-vindas')->assertOk()->assertSee('Quero comprar os livros')->assertSee('Quero jogar o RPG');
        $this->post('/boas-vindas', ['objetivo' => 'outra'])->assertSessionHasErrors('objetivo');
        $this->post('/boas-vindas', ['objetivo' => 'livros'])->assertRedirect(route('planos.index'));
        $this->assertSame('livros', User::where('email', 'novo@t.test')->value('objetivo'));
        $this->get('/')->assertOk();

        // visitante vê a pergunta na página inicial e a página de planos
        auth()->logout();
        $this->get('/')->assertOk()->assertSee('Primeira vez aqui?');
        $this->get('/planos')->assertOk()->assertSee('Livros físicos');
    }
}
