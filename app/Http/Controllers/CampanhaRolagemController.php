<?php

/*
 * ROTAS — cole no routes/web.php, dentro do mesmo grupo (auth) das campanhas:
 *
 *   use App\Http\Controllers\CampanhaRolagemController;   // no topo do web.php
 *
 *   Route::get('campanhas/{campanha}/rolagens', [CampanhaRolagemController::class, 'index'])
 *       ->name('campanhas.rolagens.index');
 *   Route::post('campanhas/{campanha}/rolagens', [CampanhaRolagemController::class, 'store'])
 *       ->middleware('throttle:40,1')
 *       ->name('campanhas.rolagens.store');
 */

namespace App\Http\Controllers;

use App\Support\Esquema;
use App\Models\Campanha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CampanhaRolagemController extends Controller
{
    private const MANTER = 100;        // rolagens guardadas por campanha (teto geral)
    private const LIMPAR_A_CADA = 20;  // a cada 20 rolagens o histórico do pote recomeça
    // Vantagem/desvantagem (rolar habilidade com mais de um d100): o modo vai na frente do rótulo, sem coluna nova.
    // Nos testes do sistema (d100 − habilidade) o menor é o melhor: vantagem fica com o menor dado, desvantagem com o maior.
    private const MODOS = ['vantagem' => 'Vantagem', 'desvantagem' => 'Desvantagem'];

    /** Consulta base das rolagens de um pote (null = público). */
    private function doPote(Campanha $campanha, ?int $pote)
    {
        $q = DB::table('campanha_rolagens')->where('campanha_id', $campanha->id);
        if (Esquema::coluna('campanha_rolagens', 'pote_id')) {
            $pote ? $q->where('pote_id', $pote) : $q->whereNull('pote_id');
        }
        return $q;
    }

    /** Mesma regra do CampanhaController: só quem participa da campanha. */
    private function autorizar(Campanha $campanha): void
    {
        abort_unless($campanha->temMembro(auth()->id()), 403, 'Você não participa desta campanha.');
    }

    private function consulta(int $campanhaId)
    {
        return DB::table('campanha_rolagens as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.campanha_id', $campanhaId)
            ->select('r.*', 'u.name as jogador');
    }

    private function formatar(object $r): array
    {
        $rotulo = $r->rotulo;
        $modo = null;
        if ($rotulo && preg_match('/^\[(vantagem|desvantagem)\] ?(.*)$/su', $rotulo, $m)) {
            $modo = $m[1];
            $rotulo = $m[2] !== '' ? $m[2] : null;
        }

        return [
            'id'         => (int) $r->id,
            'user_id'    => (int) $r->user_id,
            'jogador'    => $r->jogador ?? '—',
            'rotulo'     => $rotulo,
            'modo'       => $modo,
            'qtd'        => (int) $r->qtd,
            'lados'      => (int) $r->lados,
            'extras'     => $r->extras,
            'bonus'      => (int) $r->bonus,
            'resultados' => json_decode($r->resultados, true) ?: [],
            'total'      => (int) $r->total,
            'pote_id'    => isset($r->pote_id) && $r->pote_id ? (int) $r->pote_id : null,
        ];
    }

    /** Lista as rolagens. Com ?apos=ID devolve só as novas (ordem crescente). */
    public function index(Request $request, Campanha $campanha): JsonResponse
    {
        $this->autorizar($campanha);

        $apos = (int) $request->query('apos', 0);
        $pote = (int) $request->query('pote', 0) ?: null; // null = pote público
        $this->autorizarPote($campanha, $pote);

        $q = $this->consulta($campanha->id);
        if (Esquema::coluna('campanha_rolagens', 'pote_id')) {
            $pote ? $q->where('r.pote_id', $pote) : $q->whereNull('r.pote_id');
        }

        // total e menor id do pote: o front usa para descartar o que foi limpo (manual ou a cada 20)
        $total = (clone $this->doPote($campanha, $pote))->count();
        $desde = (int) ((clone $this->doPote($campanha, $pote))->min('id') ?? 0);

        if ($apos > 0) {
            $linhas = $q->where('r.id', '>', $apos)->orderBy('r.id')->limit(50)->get();
        } else {
            $linhas = $q->orderByDesc('r.id')->limit(30)->get()->reverse()->values();
        }

        return response()->json([
            'rolagens' => $linhas->map(fn ($r) => $this->formatar($r))->values(),
            'total'    => $total,
            'desde'    => $desde,
        ]);
    }

    /** Rola no servidor (todos veem o mesmo resultado, sem trapaça no navegador). */
    public function store(Request $request, Campanha $campanha): JsonResponse
    {
        $this->autorizar($campanha);

        $dados = $request->validate([
            'qtd'    => 'required|integer|min:1|max:30',
            'lados'  => 'required|integer|min:2|max:1000',
            'extras' => 'nullable|string|max:60',
            'rotulo' => 'nullable|string|max:60',
            'pote_id' => 'nullable|integer',
            'modo'    => 'nullable|in:normal,vantagem,desvantagem',
        ]);
        $modo = isset(self::MODOS[$dados['modo'] ?? '']) ? $dados['modo'] : null;
        if ($modo && $dados['qtd'] < 2) {
            return response()->json(['erro' => 'Vantagem e desvantagem precisam de pelo menos 2 dados.'], 422);
        }

        $pote = (int) ($dados['pote_id'] ?? 0) ?: null;
        $this->autorizarPote($campanha, $pote);

        // Extras: aceita "+2", "-1", "+2+3-1", "+2 +3 -1" ou "2, 3"
        $bonus  = 0;
        $extras = null;
        $bruto  = preg_replace('/[\s,;]+/', '', (string) ($dados['extras'] ?? ''));

        if ($bruto !== '') {
            if (!preg_match('/^[+-]?\d{1,4}([+-]\d{1,4}){0,9}$/', $bruto)) {
                return response()->json(['erro' => 'Extras inválidos. Use algo como +2 ou +3-1.'], 422);
            }
            preg_match_all('/[+-]?\d+/', $bruto, $m);
            $bonus  = array_sum(array_map('intval', $m[0]));
            $extras = $bruto;
        }

        $resultados = [];
        for ($i = 0; $i < $dados['qtd']; $i++) {
            $resultados[] = random_int(1, (int) $dados['lados']);
        }

        $rotulo = ($dados['rotulo'] ?? null) ?: null;
        $soma = array_sum($resultados);
        if ($modo) {
            $soma = $modo === 'vantagem' ? min($resultados) : max($resultados);
            $prefixo = '[' . $modo . '] ';
            $rotulo = $prefixo . mb_substr($rotulo ?? '', 0, 60 - mb_strlen($prefixo)); // a coluna tem 60 caracteres
        }

        $agora = now();
        $linha = [
            'campanha_id' => $campanha->id,
            'user_id'     => auth()->id(),
            'rotulo'      => $rotulo,
            'qtd'         => $dados['qtd'],
            'lados'       => $dados['lados'],
            'extras'      => $extras,
            'bonus'       => $bonus,
            'resultados'  => json_encode($resultados),
            'total'       => $soma + $bonus,
            'created_at'  => $agora,
            'updated_at'  => $agora,
        ];
        if (Esquema::coluna('campanha_rolagens', 'pote_id')) {
            $linha['pote_id'] = $pote;
        }
        $id = DB::table('campanha_rolagens')->insertGetId($linha);

        // mantém só as últimas MANTER rolagens
        $corte = DB::table('campanha_rolagens')
            ->where('campanha_id', $campanha->id)
            ->orderByDesc('id')->offset(self::MANTER)->value('id');
        if ($corte) {
            DB::table('campanha_rolagens')
                ->where('campanha_id', $campanha->id)->where('id', '<=', $corte)->delete();
        }

        // histórico curto: ao passar de LIMPAR_A_CADA rolagens, o pote recomeça só com a rolagem nova
        if ((clone $this->doPote($campanha, $pote))->count() > self::LIMPAR_A_CADA) {
            (clone $this->doPote($campanha, $pote))->where('id', '<', $id)->delete();
        }

        $rolagem = $this->consulta($campanha->id)->where('r.id', $id)->first();

        return response()->json(['rolagem' => $this->formatar($rolagem)]);
    }

    /** Apaga o histórico do pote: no público só o mestre; num pote secreto, o mestre ou quem o criou. */
    public function limpar(Request $request, Campanha $campanha): JsonResponse
    {
        $this->autorizar($campanha);

        $pote = (int) $request->query('pote', 0) ?: null;
        $this->autorizarPote($campanha, $pote);

        $uid  = auth()->id();
        $pode = $campanha->ehMestre($uid);
        if (!$pode && $pote) {
            $pode = DB::table('campanha_potes')->where('id', $pote)->where('user_id', $uid)->exists();
        }
        abort_unless($pode, 403, 'Só o mestre (ou quem criou o pote) pode limpar o histórico.');

        $this->doPote($campanha, $pote)->delete();

        return response()->json(['ok' => true]);
    }

    // ------------------------------------------------------------------
    // Potes secretos
    // ------------------------------------------------------------------

    /** Potes que a pessoa pode ver: o mestre vê todos; o jogador, os que criou ou de que participa. */
    public static function potesVisiveis(Campanha $campanha, int $uid): array
    {
        if (!Esquema::tabela('campanha_potes')) {
            return [];
        }

        $q = DB::table('campanha_potes as p')->where('p.campanha_id', $campanha->id);
        if (!$campanha->ehMestre($uid)) {
            $q->where(function ($w) use ($uid) {
                $w->where('p.user_id', $uid)
                  ->orWhereExists(fn ($s) => $s->selectRaw('1')->from('campanha_pote_membros as m')
                      ->whereColumn('m.pote_id', 'p.id')->where('m.user_id', $uid));
            });
        }
        $potes = $q->orderBy('p.id')->get();
        if ($potes->isEmpty()) {
            return [];
        }

        $membros = DB::table('campanha_pote_membros as m')->join('users as u', 'u.id', '=', 'm.user_id')
            ->whereIn('m.pote_id', $potes->pluck('id'))->select('m.pote_id', 'u.id', 'u.name')->get()->groupBy('pote_id');
        $criadores = User::whereIn('id', $potes->pluck('user_id'))->pluck('name', 'id');

        return $potes->map(fn ($p) => [
            'id'         => (int) $p->id,
            'nome'       => $p->nome,
            'criador_id' => (int) $p->user_id,
            'criador'    => $criadores[$p->user_id] ?? '—',
            'membros'    => ($membros[$p->id] ?? collect())->map(fn ($m) => ['id' => (int) $m->id, 'nome' => $m->name])->values()->all(),
        ])->values()->all();
    }

    /** Pote público (null) é de todos os membros; pote secreto só do criador, dos incluídos e do mestre. */
    private function autorizarPote(Campanha $campanha, ?int $poteId): void
    {
        if (!$poteId) {
            return;
        }
        abort_unless(Esquema::tabela('campanha_potes'), 404);

        $uid = auth()->id();
        $q = DB::table('campanha_potes')->where('id', $poteId)->where('campanha_id', $campanha->id);
        if (!$campanha->ehMestre($uid)) {
            $q->where(fn ($w) => $w->where('user_id', $uid)
                ->orWhereExists(fn ($s) => $s->selectRaw('1')->from('campanha_pote_membros')
                    ->whereColumn('campanha_pote_membros.pote_id', 'campanha_potes.id')->where('campanha_pote_membros.user_id', $uid)));
        }
        abort_unless($q->exists(), 403, 'Você não participa deste pote.');
    }

    /** Cria um pote secreto: quem cria sempre vê; o mestre vê todos; os demais só se forem incluídos. */
    public function criarPote(Request $request, Campanha $campanha)
    {
        $this->autorizar($campanha);

        if (!Esquema::tabela('campanha_potes')) {
            return back()->withErrors(['pote' => 'As tabelas dos potes secretos ainda não existem no banco: rode a migration 2026_10_28_000003.']);
        }

        $dados = $request->validate([
            'nome'      => 'required|string|max:60',
            'membros'   => 'nullable|array|max:50',
            'membros.*' => 'integer',
        ]);

        $uid = (int) auth()->id();
        // só participantes ativos da campanha (ou o mestre) podem ser incluídos
        $validos = DB::table('campanha_jogadores')->where('campanha_id', $campanha->id)->where('status', 'ativo')
            ->pluck('user_id')->push($campanha->mestre_id)->map(fn ($v) => (int) $v)->unique();
        $membros = collect($dados['membros'] ?? [])->map(fn ($v) => (int) $v)->unique()
            ->filter(fn ($id) => $id !== $uid && $validos->contains($id))->values();

        $agora  = now();
        $poteId = DB::table('campanha_potes')->insertGetId([
            'campanha_id' => $campanha->id,
            'user_id'     => $uid,
            'nome'        => $dados['nome'],
            'created_at'  => $agora,
            'updated_at'  => $agora,
        ]);
        if ($membros->isNotEmpty()) {
            DB::table('campanha_pote_membros')->insert($membros->map(fn ($id) => [
                'pote_id' => $poteId, 'user_id' => $id, 'created_at' => $agora, 'updated_at' => $agora,
            ])->all());
        }

        return back()->with('ok', "Pote secreto “{$dados['nome']}” criado.")->with('pote_ativo', $poteId);
    }

    /** Quem criou (ou o mestre) apaga o pote e as rolagens feitas nele. */
    public function excluirPote(Campanha $campanha, int $pote)
    {
        $this->autorizar($campanha);
        abort_unless(Esquema::tabela('campanha_potes'), 404);

        $uid   = auth()->id();
        $linha = DB::table('campanha_potes')->where('id', $pote)->where('campanha_id', $campanha->id)->first();
        abort_unless($linha, 404);
        abort_unless($campanha->ehMestre($uid) || (int) $linha->user_id === (int) $uid, 403, 'Só quem criou o pote (ou o mestre) pode excluí-lo.');

        DB::transaction(function () use ($pote) {
            if (Esquema::coluna('campanha_rolagens', 'pote_id')) {
                DB::table('campanha_rolagens')->where('pote_id', $pote)->delete();
            }
            DB::table('campanha_pote_membros')->where('pote_id', $pote)->delete();
            DB::table('campanha_potes')->where('id', $pote)->delete();
        });

        return back()->with('ok', 'Pote secreto excluído.');
    }
}