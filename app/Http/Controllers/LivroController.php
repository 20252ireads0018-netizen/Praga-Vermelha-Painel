<?php

namespace App\Http\Controllers;

use App\Support\LivroMestre;
use App\Support\Plano;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LivroController extends Controller
{
    // Livro do mestre (completo): chave antiga nesta sessão, conta que comprou o livro, ou quem clicou em "Sou mestre"
    // (ou já pediu) e não joga em campanha nenhuma ou tem a permissão de todos os mestres delas
    private function liberado(): bool
    {
        $user = auth()->user();
        if (session('livro_completo') === true || Plano::comprouLivro($user)) {
            return true;
        }
        if (!$user || !(session('sou_mestre') === true || LivroMestre::pedidos($user))) {
            return false;
        }

        return LivroMestre::estado($user) === LivroMestre::LIVRE;
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

        $user = auth()->user();
        $mestres = $user ? LivroMestre::mestres($user) : collect();
        $pedidos = $user ? LivroMestre::pedidos($user) : [];

        return view('livro-acesso', [
            'mestres' => $mestres->map(fn ($m) => ['nome' => $m->name, 'status' => $pedidos[$m->id] ?? null]),
            'pediu'   => (bool) $pedidos,
        ]);
    }

    /** "Sou mestre": sem campanhas como jogador, abre na hora; senão, pede permissão a cada mestre. */
    public function souMestre(Request $request)
    {
        $user = $request->user();
        session(['sou_mestre' => true]);
        if (LivroMestre::estado($user) === LivroMestre::LIVRE) {
            return redirect()->route('livro.index');
        }
        if (!LivroMestre::temTabela()) {
            return redirect()->route('livro.acesso')->with('aviso', 'Os pedidos aos mestres ainda não estão disponíveis. Tente novamente mais tarde.');
        }
        LivroMestre::pedir($user);

        return redirect()->route('livro.acesso')->with('aviso', 'Você joga em campanhas de outros mestres: enviamos um pedido de permissão a eles. O livro do mestre abre assim que todos aprovarem.');
    }

    /** O mestre aprova ou recusa o pedido de um jogador (na tela de campanhas). */
    public function decidirPedido(Request $request, int $id)
    {
        $acao = $request->validate(['acao' => 'required|in:aprovar,recusar'])['acao'];
        abort_unless(LivroMestre::temTabela(), 404);
        $pedido = DB::table('livro_mestre_pedidos')->where('id', $id)->where('mestre_id', $request->user()->id)->first();
        abort_unless($pedido, 404);
        DB::table('livro_mestre_pedidos')->where('id', $id)->update(['status' => $acao === 'aprovar' ? 'aprovado' : 'recusado', 'updated_at' => now()]);

        return back()->with('ok', $acao === 'aprovar' ? 'Pedido aprovado: o jogador pode abrir o livro do mestre.' : 'Pedido recusado.');
    }

    public function verificar(Request $request)
    {
        $request->validate(['chave' => 'nullable|string|max:100']);

        $esperada = (string) config('livro.chave');
        $enviada  = (string) $request->input('chave', '');

        // Chave correta → livro completo
        if ($esperada !== '' && hash_equals($esperada, $enviada)) {
            $request->session()->regenerate();
            // Só abre o livro nesta sessão: o plano vitalício vem apenas do pagamento (CompraLivroController)
            session(['livro_completo' => true]);

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
        $request->session()->forget(['livro_completo', 'sou_mestre']);
        return redirect()->route('livro.acesso');
    }

    // Sumário da versão de jogadores: páginas físicas do players.pdf (176 páginas), conferidas no próprio PDF.
    private function sumarioPlayers(): array
    {
        return [
            ['g' => 'Regras do sistema'],
            ['t' => 'Introdução ao sistema, testes e acertos',              'a' => 1,   'b' => 2],
            ['t' => 'Atributos básicos, experiência e progressão mundana',  'a' => 2,   'b' => 5],
            ['t' => 'Pontos de vida, sanidade, fobias e aura',              'a' => 5,   'b' => 9],
            ['t' => 'Habilidades: distribuição, limites e usos',            'a' => 9,   'b' => 19],
            ['t' => 'Profissões, ramos e talentos de profissão',            'a' => 19,  'b' => 52],
            ['t' => 'Dados de Praga, níveis mundanos e talentos gerais',    'a' => 52,  'b' => 69],
            ['t' => 'Experiência, treinamento, negócios e regras complementares', 'a' => 70, 'b' => 87],
            ['t' => 'Condições especiais',                                  'a' => 87,  'b' => 96],
            ['t' => 'Regras rápidas de combate e portes físicos',           'a' => 96,  'b' => 101],
            ['t' => 'Criação de personagem e ficha',                        'a' => 102, 'b' => 106],

            ['g' => 'Sobrenatural'],
            ['t' => 'Manifestações sobrenaturais e maldições',              'a' => 107, 'b' => 111],
            ['t' => 'Hexomancia e talentos de Hexomancia',                  'a' => 112, 'b' => 121],
            ['t' => 'Fisiomancia e propriedades naturais',                  'a' => 121, 'b' => 131],

            ['g' => 'Itens e equipamentos'],
            ['t' => 'Sistema de armazenamento e tamanhos de item',          'a' => 132, 'b' => 133],
            ['t' => 'Armas de longo alcance e de arremesso',                'a' => 134, 'b' => 139],
            ['t' => 'Armas corpo a corpo',                                  'a' => 139, 'b' => 146],
            ['t' => 'Proteções e compartimentos',                           'a' => 146, 'b' => 150],
            ['t' => 'Outros itens',                                         'a' => 150, 'b' => 152],
            ['t' => 'Modificações de equipamentos',                         'a' => 153, 'b' => 158],
            ['t' => 'Pontos de vida de estruturas e objetos',               'a' => 159, 'b' => 161],

            ['g' => 'Cenário e AMA'],
            ['t' => 'AMA e legislação sobrenatural',                        'a' => 162, 'b' => 164],
            ['t' => 'Lojas, compras e preços',                              'a' => 164, 'b' => 170],
            ['t' => 'Patentes e crescimento na AMA',                        'a' => 170, 'b' => 176],
        ];
    }
}