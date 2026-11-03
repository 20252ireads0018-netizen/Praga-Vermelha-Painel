<?php

namespace App\Http\Controllers;

use App\Support\Esquema;
use App\Models\Campanha;
use App\Models\CampanhaIniciativa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/** Rastreador de iniciativa: a rolagem (d100 - bônus) é feita no servidor; menor resultado age primeiro. */
class CampanhaIniciativaController extends Controller
{
    private function autorizar(Campanha $campanha): void
    {
        abort_unless($campanha->temMembro(auth()->id()), 403, 'Você não participa desta campanha.');
    }

    private function semTabela(): JsonResponse
    {
        return response()->json(['erro' => 'O rastreador de iniciativa ainda não está disponível.'], 503);
    }

    /** Fichas rolláveis [{id, nome, bonus}] com o total da habilidade "iniciativa". */
    private function fichasDoUsuario(Campanha $campanha, int $uid, bool $souMestre): array
    {
        return array_map(function ($f) {
            $total = 0;
            foreach ($f['habilidades'] as $h) {
                if (($h['chave'] ?? null) === 'iniciativa') {
                    $total = (int) $h['total'];
                    break;
                }
            }

            return ['id' => (int) $f['id'], 'nome' => $f['nome'], 'bonus' => $total];
        }, CampanhaController::fichasRolaveis($campanha, $uid, $souMestre));
    }

    private function formatar(CampanhaIniciativa $i, int $posicao, int $uid, bool $souMestre): array
    {
        $meu = (int) $i->user_id === $uid;

        return [
            'id'          => (int) $i->id,
            'ficha_id'    => (int) $i->ficha_id,
            'nome'        => $i->nome,
            'jogador'     => $i->user->name ?? '—',
            'dado'        => (int) $i->dado,
            'bonus'       => (int) $i->bonus,
            'resultado'   => (int) $i->resultado,
            'posicao'     => $posicao,
            'meu'         => $meu,
            'pode_remover' => $souMestre || $meu,
        ];
    }

    public function index(Campanha $campanha): JsonResponse
    {
        $this->autorizar($campanha);
        $uid       = (int) auth()->id();
        $souMestre = $campanha->ehMestre($uid);

        if (!Esquema::tabela('campanha_iniciativas')) {
            return response()->json(['itens' => [], 'fichas' => [], 'mestre' => $souMestre]);
        }

        $itens = CampanhaIniciativa::with('user')->where('campanha_id', $campanha->id)
            ->orderBy('resultado')->orderByDesc('bonus')->orderBy('id')->get()
            ->values()
            ->map(fn ($i, $k) => $this->formatar($i, $k + 1, $uid, $souMestre))
            ->all();

        return response()->json([
            'itens'  => $itens,
            'fichas' => $this->fichasDoUsuario($campanha, $uid, $souMestre),
            'mestre' => $souMestre,
        ])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, Campanha $campanha): JsonResponse
    {
        $this->autorizar($campanha);
        $uid       = (int) auth()->id();
        $souMestre = $campanha->ehMestre($uid);

        if (!Esquema::tabela('campanha_iniciativas')) {
            return $this->semTabela();
        }

        // Validador manual: fora de api/* as exceções de validação não viram JSON.
        $validador = Validator::make($request->all(), [
            'ficha_id' => 'required|integer',
        ], [
            'ficha_id.required' => 'Escolha uma ficha.',
            'ficha_id.integer'  => 'Escolha uma ficha válida.',
        ]);
        if ($validador->fails()) {
            return response()->json([
                'message' => $validador->errors()->first(),
                'erro'    => $validador->errors()->first(),
                'errors'  => $validador->errors(),
            ], 422);
        }
        $fichaId = (int) $validador->validated()['ficha_id'];

        $ficha = collect($this->fichasDoUsuario($campanha, $uid, $souMestre))->firstWhere('id', $fichaId);
        if (!$ficha) {
            return response()->json(['erro' => 'Você não pode rolar iniciativa com esta ficha.'], 403);
        }

        $existente = CampanhaIniciativa::where('campanha_id', $campanha->id)->where('ficha_id', $fichaId)->first();
        if ($existente && !$souMestre) {
            return response()->json(['erro' => 'Esta ficha já rolou iniciativa nesta rodada.'], 422);
        }

        $dado  = random_int(1, 100);
        $bonus = (int) $ficha['bonus'];
        $dados = [
            'user_id'   => $uid,
            'nome'      => mb_substr((string) $ficha['nome'], 0, 120),
            'dado'      => $dado,
            'bonus'     => $bonus,
            'resultado' => $dado - $bonus,
        ];

        if ($existente) {
            $existente->update($dados); // mestre re-rola
            $registro = $existente;
        } else {
            $registro = CampanhaIniciativa::create($dados + ['campanha_id' => $campanha->id, 'ficha_id' => $fichaId]);
        }
        $registro->load('user');

        $posicao = CampanhaIniciativa::where('campanha_id', $campanha->id)
            ->where(function ($q) use ($registro) {
                $q->where('resultado', '<', $registro->resultado)
                  ->orWhere(fn ($q) => $q->where('resultado', $registro->resultado)->where('bonus', '>', $registro->bonus))
                  ->orWhere(fn ($q) => $q->where('resultado', $registro->resultado)->where('bonus', $registro->bonus)->where('id', '<', $registro->id));
            })->count() + 1;

        return response()->json(['item' => $this->formatar($registro, $posicao, $uid, $souMestre)], 201);
    }

    public function destroy(Campanha $campanha, int $iniciativa): JsonResponse
    {
        $this->autorizar($campanha);
        $uid = (int) auth()->id();

        // sem binding de model: sem a tabela o binding estouraria 500 antes de chegar aqui
        if (!Esquema::tabela('campanha_iniciativas')) {
            return $this->semTabela();
        }
        $iniciativa = CampanhaIniciativa::findOrFail($iniciativa);
        abort_unless((int) $iniciativa->campanha_id === (int) $campanha->id, 404);
        abort_unless($campanha->ehMestre($uid) || (int) $iniciativa->user_id === $uid, 403, 'Você não pode remover esta entrada.');

        $iniciativa->delete();

        return response()->json(['ok' => true]);
    }

    /** Nova rodada: só o mestre limpa tudo. */
    public function limpar(Campanha $campanha): JsonResponse
    {
        $this->autorizar($campanha);
        abort_unless($campanha->ehMestre(auth()->id()), 403, 'Apenas o mestre pode fazer isso.');

        if (!Esquema::tabela('campanha_iniciativas')) {
            return $this->semTabela();
        }

        CampanhaIniciativa::where('campanha_id', $campanha->id)->delete();

        return response()->json(['ok' => true]);
    }
}
