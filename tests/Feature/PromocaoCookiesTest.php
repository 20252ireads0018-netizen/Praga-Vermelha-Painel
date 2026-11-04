<?php

namespace Tests\Feature;

use App\Models\PixPedido;
use App\Models\User;
use App\Support\CompraLivro;
use App\Support\Pix;
use App\Support\Plano;
use App\Support\Promocao;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Promoção relâmpago do livro, aviso de cookies, página de privacidade, AdSense e a página de apresentação. */
class PromocaoCookiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'array', 'cache.default' => 'array', 'planos.lancamento' => '2026-10-29', 'planos.preco_livro' => 6.99,
            'pix.chave' => 'dono@praga.test', 'pix.nome' => 'João Dono', 'pix.cidade' => 'São Paulo', 'pix.admins' => ['dono@praga.test']]);
        $this->app->useStoragePath(sys_get_temp_dir() . '/pv-promo-' . uniqid());
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
        Schema::create('fichas', fn (Blueprint $t) => $t->id() && $t->foreignId('user_id')->nullable() && $t->timestamps());
        Schema::create('campanhas', fn (Blueprint $t) => $t->id() && $t->foreignId('mestre_id') && $t->timestamps());
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
        @unlink(Promocao::arquivo());
        Plano::esquecerColunas();
        CompraLivro::esquecerColunas();
        Pix::esquecerTabela();
        parent::tearDown();
    }

    private function conta(string $email): User
    {
        return User::create(['name' => 'Ana', 'email' => $email, 'password' => 'x']);
    }

    public function test_promocao_baixa_o_preco_e_volta_sozinha(): void
    {
        $this->assertSame(6.99, CompraLivro::preco());
        Promocao::ligar(0.5, 10);
        $this->assertSame(3.5, CompraLivro::preco());
        $this->assertSame(1.75, Promocao::aplicar(6.99, 0.25));
        $this->assertSame(6.99, Promocao::preco(6.99, time() + 11 * 60), 'depois do prazo, preço cheio');
        Promocao::encerrar();
        $this->assertNull(Promocao::atual());
        $this->assertSame(6.99, CompraLivro::preco());
    }

    public function test_pix_aberto_segue_a_promocao_e_quem_gerou_nela_tem_tolerancia(): void
    {
        $ana = $this->conta('ana@praga.test');
        $this->actingAs($ana)->get('/planos/livro/pix')->assertOk()->assertSee('R$ 6,99');

        Promocao::ligar(0.25, 10);
        $this->actingAs($ana)->get('/planos/livro/pix')->assertOk()->assertSee('R$ 1,75');
        $this->assertSame('1.75', number_format((float) PixPedido::first()->valor, 2, '.', ''));

        // a promoção acabou há pouco: quem gerou o Pix nela ainda paga o preço dela
        Promocao::encerrar();
        $this->actingAs($ana)->get('/planos/livro/pix')->assertOk()->assertSee('R$ 1,75');
        // passada a tolerância, volta ao preço cheio
        $this->assertSame(6.99, Promocao::precoDoPedido(6.99, time() - 60, time() + Promocao::TOLERANCIA + 5));
    }

    public function test_painel_da_promocao_so_para_administrador(): void
    {
        $dono = $this->conta('dono@praga.test');
        $outro = $this->conta('outro@praga.test');
        $this->actingAs($outro)->get('/admin/promocao')->assertNotFound();
        $this->actingAs($outro)->post('/admin/promocao', ['fator' => '0.5', 'minutos' => 10])->assertNotFound();
        $this->assertNull(Promocao::atual());

        $this->actingAs($dono)->get('/admin/promocao')->assertOk()->assertSee('Ligar a promoção');
        $this->actingAs($dono)->post('/admin/promocao', ['fator' => '0.3', 'minutos' => 10])->assertSessionHasErrors('fator');
        $this->actingAs($dono)->post('/admin/promocao', ['fator' => '0.5', 'minutos' => 15])->assertRedirect('/admin/promocao');
        $this->assertSame(3.5, CompraLivro::preco());

        // a faixa com a contagem aparece para quem ainda não comprou
        $this->actingAs($outro)->get('/planos')->assertOk()->assertSee('Promoção relâmpago')->assertSee('data-promo-fim', false)->assertSee('R$ 3,50');
        $this->actingAs($dono)->post('/admin/promocao/encerrar')->assertRedirect('/admin/promocao');
        $this->actingAs($outro)->get('/planos')->assertOk()->assertDontSee('data-promo-faixa', false);
    }

    public function test_cookies_privacidade_adsense_e_apresentacao(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('id="pv-cookies"', false)
            ->assertSee('<meta name="google-adsense-account" content="ca-pub-4781960143966285">', false)
            ->assertSee('Privacidade e cookies');
        $this->get('/privacidade')->assertOk()->assertSee('Lei Geral de Proteção de Dados')->assertSee('Mudar minha escolha');
        $this->get('/apresentacao')->assertOk()->assertSee('Praga Vermelha')->assertSee('45 dias')->assertSee('R$ 6,99');
        $this->assertStringContainsString('pub-4781960143966285, DIRECT', file_get_contents(public_path('ads.txt')));
    }
}
