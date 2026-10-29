<?php

namespace App\Http\Controllers;

use App\Support\Plano;
use Illuminate\Http\Request;

class PlanoController extends Controller
{
    // /planos: os planos, o plano da pessoa, a compra do livro do sistema e os links dos livros físicos
    public function index()
    {
        $user  = auth()->user();
        $plano = $user ? Plano::de($user) : null;

        return view('planos', [
            'plano'      => $plano,
            'diasTeste'  => $user ? Plano::diasDeTeste($user) : 0,
            'fichas'     => $user ? Plano::fichas($user) : 0,
            'campanhas'  => $user ? Plano::campanhas($user) : 0,
            'limites'    => (array) config('planos.limites_gratis'),
            'diasPlano'  => (int) config('planos.dias_teste', 45),
            'compraUrl'  => (string) config('planos.compra_livro_url'),
            'fisicos'    => (array) config('planos.livros_fisicos'),
            'compradoEm' => $user && Plano::comprouLivro($user) ? $user->livro_comprado_em : null,
        ]);
    }

    // Boas-vindas para quem acabou de chegar: veio comprar os livros ou jogar o RPG?
    public function boasVindas()
    {
        return view('boas-vindas', [
            'diasPlano' => (int) config('planos.dias_teste', 45),
            'diasTeste' => Plano::diasDeTeste(auth()->user()),
        ]);
    }

    public function objetivo(Request $request)
    {
        $dados = $request->validate(['objetivo' => 'required|in:livros,jogar']);

        if (Plano::temColunas()) {
            $request->user()->forceFill(['objetivo' => $dados['objetivo']])->save();
        }
        session(['objetivo_escolhido' => true]); // sem a coluna, não pergunta de novo nesta sessão

        return $dados['objetivo'] === 'livros'
            ? redirect()->route('planos.index')
            : redirect('/')->with('boas_vindas', 'jogar');
    }
}
