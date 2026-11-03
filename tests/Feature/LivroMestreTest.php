<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LivroMestre;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Livro do mestre sem chave: "Sou mestre" abre na hora, ou pede permissão aos mestres das campanhas em que a pessoa joga. */
class LivroMestreTest extends TestCase
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
        (require database_path('migrations/2026_11_03_000026_create_livro_mestre_pedidos.php'))->up();
        LivroMestre::esquecerTabela();
    }

    protected function tearDown(): void
    {
        LivroMestre::esquecerTabela();
        parent::tearDown();
    }

    private function conta(string $nome): User
    {
        return User::create(['name' => $nome, 'email' => strtolower($nome) . '@t.test', 'password' => 'x']);
    }

    private function joga(User $jogador, User $mestre, string $status = 'ativo'): void
    {
        $id = DB::table('campanhas')->insertGetId(['nome' => 'Mesa de ' . $mestre->name, 'mestre_id' => $mestre->id]);
        DB::table('campanha_jogadores')->insert(['campanha_id' => $id, 'user_id' => $jogador->id, 'status' => $status]);
    }

    public function test_sem_campanha_sou_mestre_abre_na_hora_e_visitante_precisa_entrar(): void
    {
        $this->post('/livro/mestre')->assertRedirect(route('login'));
        $this->get('/livro')->assertRedirect(route('livro.acesso'));
        $this->get('/livro/acesso')->assertOk()->assertSee('Sou mestre')->assertDontSee('Chave de acesso');

        $ana = $this->conta('Ana');
        $this->joga($ana, $this->conta('Rui'), 'convidado'); // convite ainda não aceito não conta
        $this->actingAs($ana)->get('/livro')->assertRedirect(route('livro.acesso')); // ainda não clicou
        $this->post('/livro/mestre')->assertRedirect(route('livro.index'));
        $this->assertSame(0, DB::table('livro_mestre_pedidos')->count());
        $this->get('/livro/pdf/completo')->assertSuccessful(); // liberado
    }

    public function test_jogador_de_campanha_pede_e_cada_mestre_aprova(): void
    {
        $bia = $this->conta('Bia');
        $rui = $this->conta('Rui');
        $leo = $this->conta('Leo');
        $this->joga($bia, $rui);
        $this->joga($bia, $leo);

        $this->actingAs($bia)->post('/livro/mestre')->assertRedirect(route('livro.acesso'));
        $this->assertSame(2, DB::table('livro_mestre_pedidos')->where('user_id', $bia->id)->where('status', 'pendente')->count());
        $this->get('/livro')->assertRedirect(route('livro.acesso'));
        $this->get('/livro/pdf/completo')->assertForbidden();
        $this->get('/livro/acesso')->assertSee('Rui')->assertSee('aguardando');

        // só o próprio mestre decide o pedido feito a ele
        $pedidoRui = DB::table('livro_mestre_pedidos')->where('mestre_id', $rui->id)->value('id');
        $pedidoLeo = DB::table('livro_mestre_pedidos')->where('mestre_id', $leo->id)->value('id');
        $this->actingAs($leo)->post("/livro/mestre/pedidos/{$pedidoRui}", ['acao' => 'aprovar'])->assertNotFound();
        $this->assertSame([$pedidoRui], LivroMestre::pendentesPara($rui)->pluck('id')->all());

        $this->actingAs($rui)->post("/livro/mestre/pedidos/{$pedidoRui}", ['acao' => 'aprovar'])->assertRedirect();
        $this->assertSame(LivroMestre::PENDENTE, LivroMestre::estado($bia)); // falta o Leo

        $this->actingAs($leo)->post("/livro/mestre/pedidos/{$pedidoLeo}", ['acao' => 'recusar'])->assertRedirect();
        $this->assertSame(LivroMestre::RECUSADO, LivroMestre::estado($bia));
        $this->actingAs($bia)->get('/livro/acesso')->assertSee('recusou');

        // pedir de novo reabre só o recusado
        $this->post('/livro/mestre')->assertRedirect(route('livro.acesso'));
        $this->assertSame('pendente', DB::table('livro_mestre_pedidos')->where('id', $pedidoLeo)->value('status'));
        $this->assertSame('aprovado', DB::table('livro_mestre_pedidos')->where('id', $pedidoRui)->value('status'));

        $this->actingAs($leo)->post("/livro/mestre/pedidos/{$pedidoLeo}", ['acao' => 'aprovar'])->assertRedirect();
        $this->assertSame(LivroMestre::LIVRE, LivroMestre::estado($bia));
        $this->actingAs($bia)->get('/livro/pdf/completo')->assertSuccessful(); // liberado
    }
}
