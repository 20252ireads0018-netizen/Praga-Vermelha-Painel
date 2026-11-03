<?php

namespace App\Http\Controllers;

use App\Support\Esquema;
use App\Models\Reclamacao;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Reclame aqui do painel: a lista é pública; quem tem conta registra um problema ou sugestão; os administradores
 * (config/reclamacoes.php) respondem e mudam a situação. O topo mostra os números, como uma reputação.
 */
class ReclamacaoController extends Controller
{
    public static function admin(?User $user): bool
    {
        return $user && in_array(mb_strtolower($user->email), array_map('mb_strtolower', (array) config('reclamacoes.admins')), true);
    }

    private function temTabela(): bool
    {
        try {
            return Esquema::tabela('reclamacoes');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function index(Request $request): View
    {
        $status = (array) config('reclamacoes.status');
        $filtro = array_key_exists((string) $request->query('status'), $status) ? (string) $request->query('status') : null;
        $minhas = $request->boolean('minhas') && auth()->check();
        $lista = collect();
        $numeros = ['total' => 0, 'respondidas' => 0, 'resolvidas' => 0, 'abertas' => 0];
        if ($this->temTabela()) {
            $q = Reclamacao::with('user:id,name')->latest('id');
            if ($filtro) {
                $q->where('status', $filtro);
            }
            if ($minhas) {
                $q->where('user_id', auth()->id());
            }
            $lista = $q->paginate(20)->withQueryString();
            $numeros = [
                'total'       => Reclamacao::count(),
                'respondidas' => Reclamacao::whereNotNull('resposta')->count(),
                'resolvidas'  => Reclamacao::where('status', 'resolvida')->count(),
                'abertas'     => Reclamacao::where('status', 'aberta')->count(),
            ];
        }

        return view('reclamacoes', [
            'lista'      => $lista,
            'numeros'    => $numeros,
            'filtro'     => $filtro,
            'minhas'     => $minhas,
            'status'     => $status,
            'categorias' => (array) config('reclamacoes.categorias'),
            'admin'      => self::admin($request->user()),
            'pronto'     => $this->temTabela(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->temTabela(), 503, 'O Reclame aqui ainda não está disponível.');
        $dados = $request->validate([
            'categoria' => ['required', Rule::in(array_keys((array) config('reclamacoes.categorias')))],
            'titulo'    => ['required', 'string', 'min:5', 'max:120'],
            'texto'     => ['required', 'string', 'min:10', 'max:4000'],
            'pagina'    => ['nullable', 'string', 'max:255'],
        ], [], ['titulo' => 'título', 'texto' => 'descrição', 'pagina' => 'página']);

        Reclamacao::create($dados + ['user_id' => $request->user()->id, 'status' => 'aberta']);

        return redirect()->route('reclamacoes.index', ['minhas' => 1])->with('ok', 'Reclamação registrada. Você vai ver a resposta aqui mesmo.');
    }

    /** Administrador: responde e muda a situação. */
    public function responder(Request $request, Reclamacao $reclamacao): RedirectResponse
    {
        abort_unless(self::admin($request->user()), 403);
        $dados = $request->validate([
            'status'   => ['required', Rule::in(array_keys((array) config('reclamacoes.status')))],
            'resposta' => ['nullable', 'string', 'max:4000'],
        ]);
        $resposta = trim((string) ($dados['resposta'] ?? ''));
        $reclamacao->forceFill([
            'status'        => $dados['status'],
            'resposta'      => $resposta !== '' ? $resposta : $reclamacao->resposta,
            'respondida_em' => $resposta !== '' && $resposta !== (string) $reclamacao->resposta ? now() : $reclamacao->respondida_em,
        ])->save();

        return back()->with('ok', 'Resposta salva.');
    }
}
