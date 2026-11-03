<?php

namespace App\Http\Controllers;

use App\Models\Campanha;
use App\Models\CampanhaFicha;
use App\Models\TimeSkip;
use App\Models\TimeSkipResposta;
use App\Support\TimeSkipCalc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Time skips da campanha. O mestre monta quantos quiser (do zero ou a partir de um modelo de config/timeskip.php) e
 * decide quando abrir; cada jogador preenche as escolhas de um personagem e vê na hora o que ganhou e quanto sobrou.
 */
class TimeSkipController extends Controller
{
    public function index(Campanha $campanha): View
    {
        $uid = (int) auth()->id();
        abort_unless($campanha->temMembro($uid), 403, 'Você não participa desta campanha.');
        $mestre = $campanha->ehMestre($uid);
        $tabela = Schema::hasTable('time_skips') && Schema::hasTable('time_skip_respostas');

        $skips = [];
        $respostas = [];
        if ($tabela) {
            $q = TimeSkip::where('campanha_id', $campanha->id)->orderByDesc('id');
            if (!$mestre) {
                $q->where('status', '!=', 'rascunho');
            }
            $skips = $q->get();
            $rq = TimeSkipResposta::whereIn('time_skip_id', $skips->pluck('id'))->with('user:id,name');
            if (!$mestre) {
                $rq->where('user_id', $uid);
            }
            $respostas = $rq->get()->map(fn (TimeSkipResposta $r) => $this->respostaParaTela($r))->all();
            $skips = $skips->map(fn (TimeSkip $t) => $t->paraTela())->all();
        }

        return view('campanhas.timeskips', [
            'campanha'  => $campanha,
            'souMestre' => $mestre,
            'tabela'    => $tabela,
            'skips'     => $skips,
            'respostas' => $respostas,
            'fichas'    => $this->fichas($campanha, $mestre ? null : $uid),
            'modelos'   => $mestre ? collect(config('timeskip.modelos'))->map(fn ($m, $id) => ['id' => $id, 'nome' => $m['nome']])->values()->all() : [],
        ]);
    }

    /** Cria ou edita um time skip (o mestre). Com "modelo", começa de um modelo pronto. */
    public function salvar(Request $request, Campanha $campanha): JsonResponse
    {
        $this->exigirMestre($campanha);
        abort_unless(Schema::hasTable('time_skips'), 422, 'As tabelas de time skip ainda não existem: rode o SQL da migração 2026_11_02_000023.');
        $d = $request->validate([
            'id'     => ['nullable', 'integer'],
            'nome'   => ['required', 'string', 'max:80'],
            'status' => ['nullable', 'in:' . implode(',', TimeSkip::STATUS)],
            'modelo' => ['nullable', 'string', 'max:40'],
            'config' => ['nullable', 'array'],
        ]);
        $ts = !empty($d['id']) ? TimeSkip::where('campanha_id', $campanha->id)->findOrFail((int) $d['id']) : new TimeSkip(['campanha_id' => $campanha->id, 'status' => 'rascunho']);
        if (!empty($d['modelo'])) {
            $modelo = config('timeskip.modelos.' . $d['modelo']);
            abort_unless(is_array($modelo), 422, 'Modelo desconhecido.');
            $config = $modelo['config'];
        } else {
            $config = $d['config'] ?? ($ts->config ?: []);
        }
        $ts->nome = trim($d['nome']);
        $ts->status = $d['status'] ?? $ts->status ?? 'rascunho';
        $ts->config = TimeSkipCalc::limparConfig($config);
        $ts->save();

        return response()->json(['ok' => true, 'skip' => $ts->paraTela()]);
    }

    public function excluir(Campanha $campanha, TimeSkip $timeSkip): JsonResponse
    {
        $this->exigirMestre($campanha);
        abort_unless((int) $timeSkip->campanha_id === (int) $campanha->id, 404);
        TimeSkipResposta::where('time_skip_id', $timeSkip->id)->delete();
        $timeSkip->delete();

        return response()->json(['ok' => true]);
    }

    /** O jogador grava as escolhas de um personagem (só com o time skip aberto e dentro das regras). */
    public function responder(Request $request, Campanha $campanha, TimeSkip $timeSkip): JsonResponse
    {
        $uid = (int) auth()->id();
        abort_unless((int) $timeSkip->campanha_id === (int) $campanha->id, 404);
        abort_unless($campanha->temMembro($uid), 403, 'Você não participa desta campanha.');
        if ($timeSkip->status !== 'aberto') {
            return response()->json(['ok' => false, 'erro' => 'Este time skip não está aberto para escolhas.'], 422);
        }
        $d = $request->validate([
            'ficha_id' => ['nullable', 'integer', 'min:0'],
            'dados'    => ['required', 'array'],
        ]);
        $ficha = (int) ($d['ficha_id'] ?? 0);
        if ($ficha > 0 && !CampanhaFicha::where('campanha_id', $campanha->id)->where('user_id', $uid)->where('ficha_id', $ficha)->exists()) {
            return response()->json(['ok' => false, 'erro' => 'Essa ficha não está vinculada a você nesta campanha.'], 422);
        }
        $cfg = $timeSkip->config ?: [];
        $dados = TimeSkipCalc::limparResposta($d['dados'], $cfg);
        $conta = TimeSkipCalc::calcular($cfg, $dados);
        if ($conta['erros']) {
            return response()->json(['ok' => false, 'erro' => implode(' ', $conta['erros'])], 422);
        }
        $r = TimeSkipResposta::firstOrNew(['time_skip_id' => $timeSkip->id, 'user_id' => $uid, 'ficha_id' => $ficha]);
        $r->dados = $dados;
        $r->save();

        return response()->json(['ok' => true, 'resposta' => $this->respostaParaTela($r->load('user:id,name'))]);
    }

    private function respostaParaTela(TimeSkipResposta $r): array
    {
        return [
            'id'       => (int) $r->id,
            'skip'     => (int) $r->time_skip_id,
            'user_id'  => (int) $r->user_id,
            'jogador'  => (string) ($r->user->name ?? ''),
            'ficha_id' => (int) $r->ficha_id,
            'dados'    => $r->dados ?: (object) [],
            'em'       => optional($r->updated_at)->format('d/m/Y H:i'),
        ];
    }

    /** Fichas vinculadas à campanha (do jogador, ou todas para o mestre). */
    private function fichas(Campanha $campanha, ?int $uid): array
    {
        $q = CampanhaFicha::where('campanha_id', $campanha->id)->with('ficha:id,nome');
        if ($uid !== null) {
            $q->where('user_id', $uid);
        }

        return $q->get()->filter(fn ($v) => $v->ficha)
            ->map(fn ($v) => ['id' => (int) $v->ficha_id, 'nome' => (string) $v->ficha->nome, 'user_id' => (int) $v->user_id])->values()->all();
    }

    private function exigirMestre(Campanha $campanha): void
    {
        abort_unless($campanha->ehMestre(auth()->id()), 403, 'Apenas o mestre pode fazer isso.');
    }
}
