<?php

namespace App\Http\Controllers;

use App\Support\Plano;
use Illuminate\Http\Request;

class LivroController extends Controller
{
    // Livro completo: chave aplicada nesta sessão, ou conta que comprou o livro (plano vitalício)
    private function liberado(): bool
    {
        return session('livro_completo') === true || Plano::comprouLivro(auth()->user());
    }

    // /livro: com chave abre o livro completo; sem chave vai para a tela de acesso
    public function index()
    {
        if (!$this->liberado()) {
            return redirect()->route('livro.acesso');
        }

        return view('livro', [
            'titulo'   => 'Livro do Sistema',
            'versao'   => 'completo',
            'chave'    => 'livro',   // mantém os marcadores já salvos
            'sumario'  => null,      // usa o SUMARIO padrão do leitor.js
            'completo' => true,
        ]);
    }

    public function acesso()
    {
        if ($this->liberado()) {
            return redirect()->route('livro.index');
        }

        return view('livro-acesso');
    }

    public function verificar(Request $request)
    {
        $request->validate(['chave' => 'nullable|string|max:100']);

        $esperada = (string) config('livro.chave');
        $enviada  = (string) $request->input('chave', '');

        // Chave correta → livro completo
        if ($esperada !== '' && hash_equals($esperada, $enviada)) {
            $request->session()->regenerate();
            session(['livro_completo' => true]);

            // A chave vem com a compra do info-produto: na conta logada, vira o plano vitalício
            $user = $request->user();
            if ($user && Plano::temColunas() && !Plano::comprouLivro($user)) {
                $user->forceFill(['livro_comprado_em' => now()])->save();
                return redirect()->route('planos.index')
                    ->with('ok', 'Livro do sistema ativado na sua conta: plano vitalício sem anúncios e todos os livros liberados.');
            }

            return redirect()->route('livro.index');
        }

        // Não aplicou chave → versão de jogador
        if ($enviada === '') {
            return redirect()->route('livro.players');
        }

        // Aplicou uma chave errada
        return back()->withErrors(['chave' => 'Chave incorreta.']);
    }

    public function players()
    {
        return view('livro', [
            'titulo'   => 'Livro do Sistema — Versão de Jogadores',
            'versao'   => 'players',
            'chave'    => 'livro-players',  // marcadores separados do livro completo
            'sumario'  => $this->sumarioPlayers(),
            'completo' => false,
        ]);
    }

    // Entrega o PDF. O completo só sai com a chave aplicada na sessão.
    public function pdf(Request $request, string $versao)
    {
        if ($versao === 'completo' && !$this->liberado()) {
            abort(403);
        }

        $arquivo = storage_path('app/livro/' . ($versao === 'completo' ? 'completo.pdf' : 'players.pdf'));
        abort_unless(is_file($arquivo), 404);

        $nome = $versao === 'completo' ? 'praga-vermelha.pdf' : 'praga-vermelha-players.pdf';

        if ($request->has('baixar')) {
            return response()->download($arquivo, $nome);
        }

        return response()->file($arquivo, ['Content-Type' => 'application/pdf']);
    }

    // Catálogo de talentos do livro (config/talentos_livro.php) em JSON, para a ficha e o leitor
    public function talentos()
    {
        return response()->json([
            'grupos'   => config('talentos_livro.grupos', []),
            'talentos' => config('talentos_livro.talentos', []),
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    public function sair(Request $request)
    {
        $request->session()->forget('livro_completo');
        return redirect()->route('livro.acesso');
    }

    // Sumário da versão de jogadores (baseado no índice do próprio PDF).
    // ATENÇÃO: confira os números com as páginas físicas do players.pdf.
    private function sumarioPlayers(): array
    {
        return [
            ['g' => 'Criação de personagem'],
            ['t' => 'Como criar personagem e atributos', 'a' => 1,   'b' => 8],
            ['t' => 'PV, Sanidade e Aura',               'a' => 5,   'b' => 8],
            ['t' => 'Habilidades',                       'a' => 9,   'b' => 18],
            ['t' => 'Profissões',                        'a' => 18,  'b' => 50],
            ['t' => 'Talentos',                          'a' => 53,  'b' => 69],

            ['g' => 'Regras'],
            ['t' => 'Regras de combate',                 'a' => 70,  'b' => 101],
            ['t' => 'Ficha',                             'a' => 101, 'b' => 104],
            ['t' => 'Ascensão',                          'a' => 105, 'b' => 107],

            ['g' => 'Sobrenatural'],
            ['t' => 'Maldições e Hexomancia',            'a' => 108, 'b' => 118],
            ['t' => 'Fisiomancia',                       'a' => 369, 'b' => 415],

            ['g' => 'Equipamento e cenário'],
            ['t' => 'Armas',                             'a' => 418, 'b' => 430],
            ['t' => 'Equipamentos',                      'a' => 430, 'b' => 436],
            ['t' => 'Modificações',                      'a' => 437, 'b' => 452],
            ['t' => 'AMA',                               'a' => 482, 'b' => 500],
        ];
    }
}