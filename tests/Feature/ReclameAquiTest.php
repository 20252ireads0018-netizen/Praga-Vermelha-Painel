<?php

namespace Tests\Feature;

use App\Models\Reclamacao;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Reclame aqui do painel: lista pública, registro com conta e resposta só dos administradores. */
class ReclameAquiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'array', 'cache.default' => 'array', 'reclamacoes.admins' => ['dono@t.test']]);
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->string('chave_maldicoes')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        (require database_path('migrations/2026_11_03_000027_create_reclamacoes.php'))->up();
    }

    public function test_registrar_ver_e_responder(): void
    {
        $this->get('/reclame-aqui')->assertOk()->assertSee('Reclame aqui')->assertSee('Entre na sua conta');
        $this->post('/reclame-aqui', ['categoria' => 'gladio', 'titulo' => 'Sem conta', 'texto' => 'não deveria entrar'])->assertRedirect(route('login'));

        $ana = User::create(['name' => 'Ana', 'email' => 'ana@t.test', 'password' => 'x']);
        $this->actingAs($ana)->post('/reclame-aqui', ['categoria' => 'invalida', 'titulo' => 'x', 'texto' => 'curto'])
            ->assertSessionHasErrors(['categoria', 'titulo', 'texto']);
        $this->post('/reclame-aqui', ['categoria' => 'gladio', 'titulo' => 'Fisiomancia animal não afeta as fichas',
            'texto' => 'Joguei a carta e a propriedade não fez nada nas três fichas.', 'pagina' => 'Fisiomancia animal'])
            ->assertRedirect(route('reclamacoes.index', ['minhas' => 1]));
        $r = Reclamacao::firstOrFail();
        $this->assertSame(['aberta', $ana->id], [$r->status, (int) $r->user_id]);
        $this->get('/reclame-aqui')->assertSee('Fisiomancia animal não afeta as fichas')->assertSee('Aguardando resposta')->assertDontSee('Responder</summary>', false);

        // só o administrador responde
        $this->post("/reclame-aqui/{$r->id}/responder", ['status' => 'resolvida', 'resposta' => 'Corrigido!'])->assertForbidden();
        $dono = User::create(['name' => 'Dono', 'email' => 'dono@t.test', 'password' => 'x']);
        $this->actingAs($dono)->get('/reclame-aqui')->assertSee('Responder</summary>', false);
        $this->post("/reclame-aqui/{$r->id}/responder", ['status' => 'resolvida', 'resposta' => 'Corrigido na carta e no motor.'])->assertRedirect();
        $r->refresh();
        $this->assertSame(['resolvida', 'Corrigido na carta e no motor.'], [$r->status, $r->resposta]);
        $this->assertNotNull($r->respondida_em);

        // a lista pública mostra a resposta e os números
        auth()->logout();
        $this->get('/reclame-aqui?status=resolvida')->assertOk()->assertSee('Corrigido na carta e no motor.')->assertSee('100%');
        $this->get('/reclame-aqui?status=aberta')->assertOk()->assertDontSee('Fisiomancia animal não afeta as fichas');
    }
}
