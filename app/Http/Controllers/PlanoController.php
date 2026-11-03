<?php

namespace App\Http\Controllers;

use App\Models\PixPedido;
use App\Support\AsaasCliente;
use App\Support\Pix;
use App\Support\CompraLivro;
use App\Support\Plano;
use Illuminate\Http\Request;

class PlanoController extends Controller
{
    // /planos: os planos, o plano da pessoa, a compra do livro do sistema e os links dos livros físicos
    public function index(AsaasCliente $asaas)
    {
        $user = auth()->user();
        // de volta da fatura: confere o pagamento no Asaas sem esperar o webhook
        if ($user && CompraLivro::conferir($user, $asaas)) {
            session()->now('ok', 'Pagamento confirmado: plano vitalício ativado. Obrigado por comprar o livro!');
        }
        $plano = $user ? Plano::de($user) : null;

        return view('planos', [
            'plano'      => $plano,
            'diasTeste'  => $user ? Plano::diasDeTeste($user) : 0,
            'fichas'     => $user ? Plano::fichas($user) : 0,
            'campanhas'  => $user ? Plano::campanhas($user) : 0,
            'limites'    => (array) config('planos.limites_gratis'),
            'diasPlano'  => (int) config('planos.dias_teste', 45),
            'preco'            => CompraLivro::precoTexto(),
            'compraDisponivel' => CompraLivro::disponivel(),
            // Pix direto na chave do dono tem preferência sobre o Asaas
            'pixDisponivel'    => Pix::disponivel(),
            'pixAdmin'         => Pix::admin($user) && Pix::disponivel(),
            'pixParaConferir'  => Pix::admin($user) && Pix::disponivel() ? PixPedido::where('status', 'informado')->count() : 0,
            'fisicos'    => (array) config('planos.livros_fisicos'),
            'compradoEm' => $user && Plano::comprouLivro($user) ? $user->livro_comprado_em : null,
        ]);
    }

    // Boas-vindas para quem acabou de chegar: veio ler os livros, jogar o RPG ou os dois?
    public function boasVindas()
    {
        return view('boas-vindas', [
            'diasPlano' => (int) config('planos.dias_teste', 45),
            'diasTeste' => Plano::diasDeTeste(auth()->user()),
            'preco'     => CompraLivro::precoTexto(),
        ]);
    }

    public function objetivo(Request $request)
    {
        $dados = $request->validate(['objetivo' => 'required|in:livros,jogar,ambos']);

        if (Plano::temColunas()) {
            $request->user()->forceFill(['objetivo' => $dados['objetivo']])->save();
        }
        session(['objetivo_escolhido' => true]); // sem a coluna, não pergunta de novo nesta sessão

        return $dados['objetivo'] === 'livros'
            ? redirect()->route('planos.index')
            : redirect('/')->with('boas_vindas', $dados['objetivo']);
    }
}
