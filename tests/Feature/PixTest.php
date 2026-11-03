<?php

namespace Tests\Feature;

use App\Models\PixPedido;
use App\Models\User;
use App\Support\CompraLivro;
use App\Support\Pix;
use App\Support\Plano;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Compra do livro do sistema por Pix direto: QR Code / copia e cola, "Já paguei" e a aprovação do administrador. */
class PixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'array', 'cache.default' => 'array', 'planos.lancamento' => '2026-10-29', 'planos.preco_livro' => 6.99,
            'pix.chave' => 'dono@praga.test', 'pix.nome' => 'João Dono', 'pix.cidade' => 'São Paulo', 'pix.admins' => ['dono@praga.test']]);
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
        (require database_path('migrations/2026_11_03_000025_create_pix_pedidos.php'))->up();
        // a página de planos conta as fichas e campanhas da conta
        Schema::create('fichas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable();
            $t->timestamps();
        });
        Schema::create('campanhas', function (Blueprint $t) {
            $t->id();
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
        Plano::esquecerColunas();
        CompraLivro::esquecerColunas();
        Pix::esquecerTabela();
    }

    protected function tearDown(): void
    {
        Plano::esquecerColunas();
        CompraLivro::esquecerColunas();
        Pix::esquecerTabela();
        parent::tearDown();
    }

    private function conta(string $email): User
    {
        return User::create(['name' => 'Ana', 'email' => $email, 'password' => 'x']);
    }

    public function test_copia_e_cola_segue_o_padrao_do_banco_central(): void
    {
        $this->assertSame('29B1', Pix::crc16('123456789')); // valor de referência do CRC16-CCITT
        $pix = Pix::copiaECola(6.99, 'PVABC123');
        $this->assertStringStartsWith('000201', $pix);
        $this->assertStringContainsString('0014br.gov.bcb.pix0115dono@praga.test', $pix);
        $this->assertStringContainsString('54046.99', $pix);
        $this->assertStringContainsString('5909JOAO DONO6009SAO PAULO', $pix); // sem acento, maiúsculo
        $this->assertStringContainsString('62120508PVABC123', $pix);
        $this->assertSame(Pix::crc16(substr($pix, 0, -4)), substr($pix, -4));
    }

    public function test_pagar_avisar_e_o_admin_aprovar_ativa_o_vitalicio(): void
    {
        $u = $this->conta('ana@t.test');
        $this->actingAs($u)->get('/planos')->assertOk()->assertSee('Comprar com Pix');

        $this->get('/planos/livro/pix')->assertOk()->assertSee('Pix copia e cola')->assertSee('R$ 6,99');
        $this->get('/planos/livro/pix')->assertOk(); // recarregar reaproveita o mesmo pedido
        $this->assertSame(1, PixPedido::count());
        $pedido = PixPedido::first();
        $this->assertSame('aguardando', $pedido->status);

        $this->post('/planos/livro/pix/paguei', ['pagador' => 'Ana Souza'])->assertRedirect(route('livro.pix'));
        $pedido->refresh();
        $this->assertSame(['informado', 'Ana Souza'], [$pedido->status, $pedido->pagador]);
        $this->get('/planos/livro/pix')->assertOk()->assertSee('Aviso de pagamento recebido');

        // só o administrador vê e decide
        $this->get('/admin/pix')->assertForbidden();
        $this->post("/admin/pix/{$pedido->id}", ['acao' => 'aprovar'])->assertForbidden();
        $this->assertNull($u->fresh()->livro_comprado_em);

        $dono = $this->conta('dono@praga.test');
        $this->actingAs($dono)->get('/planos')->assertSee('1 para conferir');
        $this->get('/admin/pix')->assertOk()->assertSee($pedido->codigo)->assertSee('Ana Souza');
        $this->post("/admin/pix/{$pedido->id}", ['acao' => 'aprovar'])->assertRedirect(route('admin.pix'));
        $this->assertSame('aprovado', $pedido->fresh()->status);
        $this->assertNotNull($u->fresh()->livro_comprado_em);
        $this->assertSame(Plano::VITALICIO, Plano::de($u->fresh()));

        // decidir de novo não muda nada; quem comprou não vê mais o Pix
        $this->post("/admin/pix/{$pedido->id}", ['acao' => 'recusar'])->assertSessionHas('ok', "O pedido {$pedido->codigo} já estava aprovado.");
        $this->assertSame('aprovado', $pedido->fresh()->status);
        $this->actingAs($u->fresh())->get('/planos/livro/pix')->assertRedirect(route('planos.index'));
    }

    public function test_recusado_gera_novo_pedido_e_sem_chave_a_compra_fica_desligada(): void
    {
        $u = $this->conta('bia@t.test');
        $this->actingAs($u)->get('/planos/livro/pix')->assertOk();
        $primeiro = PixPedido::first();

        $this->actingAs($this->conta('dono@praga.test'))->post("/admin/pix/{$primeiro->id}", ['acao' => 'recusar'])->assertRedirect();
        $this->assertSame('recusado', $primeiro->fresh()->status);
        $this->assertNull($u->fresh()->livro_comprado_em);

        $this->actingAs($u)->get('/planos/livro/pix')->assertOk()->assertSee('Não encontramos o pagamento');
        $this->assertSame(2, PixPedido::count());
        $this->assertNotSame($primeiro->codigo, PixPedido::latest('id')->first()->codigo);

        config(['pix.chave' => '']);
        $this->get('/planos/livro/pix')->assertRedirect(route('planos.index'));
        $this->get('/planos')->assertOk()->assertDontSee('Comprar com Pix');
    }
}
