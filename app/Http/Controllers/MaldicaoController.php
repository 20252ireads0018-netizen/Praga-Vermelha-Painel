<?php

namespace App\Http\Controllers;

use App\Models\Campanha;
use App\Support\AcessoMaldicoes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MaldicaoController extends Controller
{
    // GET /maldicoes  -> lista o catálogo (view: maldicoes/index.blade.php)
    public function index()
    {
        $u = auth()->user();

        return view('maldicoes.index', [
            'maldicoes'        => config('maldicoes', []),
            'lista'            => AcessoMaldicoes::lista($u),
            'podeVerRestritas' => AcessoMaldicoes::podeVerRestritas($u),
            'temChave'         => AcessoMaldicoes::temChaveValida($u),
            'ehMestre'         => AcessoMaldicoes::ehMestre($u),
        ]);
    }

    // GET /maldicoes/{slug} -> página de uma maldição (view: maldicoes/show.blade.php)
    public function show(string $slug)
    {
        $catalogo = config('maldicoes', []);
        $maldicao = $catalogo[$slug] ?? null;

        abort_if($maldicao === null, 404);
        abort_unless(AcessoMaldicoes::slugPermitido($slug, auth()->user()), 404);

        return view('maldicoes.show', [
            'maldicao' => $maldicao,
            'slug'     => $slug,
        ]);
    }

    // POST /maldicoes/chave -> o usuário informa a chave dada pelo mestre
    public function salvarChave(Request $request)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $dados = $request->validate(['chave' => 'nullable|string|max:60']);
        $chave = trim((string) ($dados['chave'] ?? ''));

        if (!Schema::hasColumn('users', 'chave_maldicoes')) {
            return back()->with('chave_status', 'invalida');
        }

        if ($chave === '') {
            $user->chave_maldicoes = null;
            $user->save();
            AcessoMaldicoes::limpar();
            return back()->with('chave_status', 'removida');
        }

        $bate = Schema::hasColumn('campanhas', 'chave_maldicoes')
            && Campanha::whereNotNull('chave_maldicoes')->pluck('chave_maldicoes')
                ->contains(fn ($c) => mb_strtolower(trim((string) $c)) === mb_strtolower($chave));

        if (!$bate) {
            return back()->with('chave_status', 'invalida');
        }

        $user->chave_maldicoes = $chave;
        $user->save();
        AcessoMaldicoes::limpar();

        return back()->with('chave_status', AcessoMaldicoes::temChaveValida($user) ? 'aceita' : 'invalida');
    }
}
