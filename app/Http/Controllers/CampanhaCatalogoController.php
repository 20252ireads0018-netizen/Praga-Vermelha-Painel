<?php

namespace App\Http\Controllers;

use App\Models\Campanha;
use App\Models\CampanhaCatalogo;
use App\Models\User;
use App\Support\AcessoMaldicoes;
use App\Support\ComparadorCatalogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/** Disputa de catálogo: o jogador descreve uma maldição restrita da qual só conhece o título. */
class CampanhaCatalogoController extends Controller
{
    public const ESPERA = 30; // segundos entre catálogos do mesmo jogador

    private function autorizar(Campanha $campanha): void
    {
        abort_unless($campanha->temMembro(auth()->id()), 403, 'Você não participa desta campanha.');
    }

    /**
     * Maldições restritas que o usuário ainda não pode ver, mais as marcadas como catalogáveis [{slug, nome}].
     * Quem já vê as restritas recebe lista vazia.
     */
    // $mestreDaCampanha: para testes, o mestre da campanha recebe todas as restritas mesmo já podendo vê-las
    public static function disponiveis(?User $user, bool $mestreDaCampanha = false): array
    {
        if (!$user || (!$mestreDaCampanha && AcessoMaldicoes::podeVerRestritas($user))) {
            return [];
        }
        $catalogo = (array) config('maldicoes', []);

        $itens = [];
        foreach ((array) config('maldicoes_lista', []) as $m) {
            $slug = $m['slug'] ?? null;
            if ((!empty($m['restrita']) || !empty($m['catalogavel'])) && $slug && isset($catalogo[$slug])) {
                $itens[] = ['slug' => $slug, 'nome' => $m['nome'] ?? $slug];
            }
        }

        return $itens;
    }

    private function segundosRestantes(Campanha $campanha, int $uid): int
    {
        $ultimo = CampanhaCatalogo::where('campanha_id', $campanha->id)->where('user_id', $uid)->latest('id')->first();
        if (!$ultimo || !$ultimo->created_at) {
            return 0;
        }

        return max(0, self::ESPERA - (int) $ultimo->created_at->diffInSeconds(now(), true));
    }

    private function formatar(CampanhaCatalogo $c, array $nomes, int $uid, bool $souMestre): array
    {
        $meu = (int) $c->user_id === $uid;

        return [
            'id'         => (int) $c->id,
            'jogador'    => $c->user->name ?? '—',
            'maldicao'   => $nomes[$c->maldicao_slug] ?? $c->maldicao_slug,
            'pontuacao'  => (int) $c->pontuacao,
            // o detalhamento da nota (critérios e penalidades) é só do mestre; jogadores veem apenas a porcentagem
            'detalhes'   => $souMestre ? ($c->detalhes ?: null) : null,
            'criado_em'  => optional($c->created_at)->toIso8601String(),
            'meu'        => $meu,
            'texto'      => ($meu || $souMestre) ? $c->texto : null,
            // uma dica por catálogo, só para quem o escreveu: depois de liberada, fica guardada nos detalhes
            'dica'       => $meu ? ($c->detalhes['dica'] ?? null) : null,
            'pode_dica'  => $meu && empty($c->detalhes['dica']),
        ];
    }

    private function nomes(): array
    {
        $n = [];
        foreach ((array) config('maldicoes_lista', []) as $m) {
            if (!empty($m['slug'])) {
                $n[$m['slug']] = $m['nome'] ?? $m['slug'];
            }
        }

        return $n;
    }

    public function index(Campanha $campanha): JsonResponse
    {
        $this->autorizar($campanha);
        $user = auth()->user();

        if (!Schema::hasTable('campanha_catalogos')) {
            return response()->json(['itens' => [], 'espera' => 0, 'disponiveis' => self::disponiveis($user, $campanha->ehMestre($user->id)), 'mestre' => $campanha->ehMestre($user->id)]);
        }

        $souMestre = $campanha->ehMestre($user->id);
        $nomes     = $this->nomes();
        // Placar: só a melhor proposta de cada jogador em cada maldição (empate: a mais antiga, quem chegou primeiro)
        $melhores = CampanhaCatalogo::where('campanha_id', $campanha->id)
            ->orderByDesc('pontuacao')->orderBy('id')
            ->get(['id', 'user_id', 'maldicao_slug'])
            ->unique(fn ($c) => $c->user_id . '|' . $c->maldicao_slug)
            ->pluck('id');
        $itens = CampanhaCatalogo::with('user')->whereIn('id', $melhores)
            ->orderByDesc('pontuacao')->orderBy('id')->limit(50)->get()
            ->map(fn ($c) => $this->formatar($c, $nomes, (int) $user->id, $souMestre))->values();

        return response()->json([
            'itens'       => $itens,
            'espera'      => $this->segundosRestantes($campanha, (int) $user->id),
            'disponiveis' => self::disponiveis($user, $souMestre),
            'mestre'      => $souMestre,
        ]);
    }

    /** Mestre: recalcula pontuação e detalhes de todos os catálogos da campanha com o comparador atual. */
    public function recalcular(Campanha $campanha): JsonResponse
    {
        $this->autorizar($campanha);
        if (!$campanha->ehMestre(auth()->id())) {
            return response()->json(['erro' => 'Só o mestre pode recalcular as notas.'], 403);
        }
        if (!Schema::hasTable('campanha_catalogos')) {
            return response()->json(['erro' => 'A disputa de catálogo ainda não está disponível.'], 503);
        }

        $total = 0;
        CampanhaCatalogo::where('campanha_id', $campanha->id)->orderBy('id')->chunkById(100, function ($lote) use (&$total) {
            foreach ($lote as $c) {
                $res = ComparadorCatalogo::comparar((string) $c->texto, (string) $c->maldicao_slug);
                $c->pontuacao = $res['pontuacao'];
                $c->detalhes  = $res['detalhes'] + ['penalidades' => $res['penalidades'] ?? []]
                    + (isset($c->detalhes['dica']) ? ['dica' => $c->detalhes['dica']] : []); // a dica já liberada continua
                $c->save();
                $total++;
            }
        });

        return response()->json(['ok' => true, 'total' => $total]);
    }

    /** Dono do catálogo: libera (uma vez) a dica do que mudar para subir a nota; pedir de novo devolve a mesma. */
    public function dica(Campanha $campanha, string $catalogo): JsonResponse
    {
        $this->autorizar($campanha);
        if (!Schema::hasTable('campanha_catalogos')) {
            return response()->json(['erro' => 'A disputa de catálogo ainda não está disponível.'], 503);
        }
        $registro = CampanhaCatalogo::where('campanha_id', $campanha->id)->where('id', (int) $catalogo)->first();
        if (!$registro || (int) $registro->user_id !== (int) auth()->id()) {
            return response()->json(['erro' => 'Só quem escreveu o catálogo pode ver a dica dele.'], 403);
        }
        $detalhes = (array) ($registro->detalhes ?: []);
        if (empty($detalhes['dica'])) {
            $detalhes['dica'] = ComparadorCatalogo::dica((string) $registro->texto, (string) $registro->maldicao_slug);
            $registro->detalhes = $detalhes;
            $registro->save();
        }

        return response()->json(['ok' => true, 'dica' => $detalhes['dica']]);
    }

    /** Mestre: apaga uma proposta de catálogo da campanha. */
    public function destroy(Campanha $campanha, string $catalogo): JsonResponse
    {
        $this->autorizar($campanha);
        if (!$campanha->ehMestre(auth()->id())) {
            return response()->json(['erro' => 'Só o mestre pode apagar propostas.'], 403);
        }
        if (!Schema::hasTable('campanha_catalogos')) {
            return response()->json(['erro' => 'A disputa de catálogo ainda não está disponível.'], 503);
        }

        $registro = CampanhaCatalogo::where('campanha_id', $campanha->id)->where('id', (int) $catalogo)->first();
        if (!$registro) {
            return response()->json(['erro' => 'Proposta não encontrada.'], 404);
        }
        $registro->delete();

        return response()->json(['ok' => true]);
    }

    public function store(Request $request, Campanha $campanha): JsonResponse
    {
        $this->autorizar($campanha);
        $user = auth()->user();

        if (!Schema::hasTable('campanha_catalogos')) {
            return response()->json(['erro' => 'A disputa de catálogo ainda não está disponível.'], 503);
        }

        $slugs = array_column(self::disponiveis($user, $campanha->ehMestre($user->id)), 'slug');
        // Validador manual: neste projeto as exceções só viram JSON em api/*, então $request->validate()
        // redirecionaria em vez de responder 422 para o JS da disputa.
        $validador = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'maldicao' => ['required', 'string', 'in:' . implode(',', $slugs ?: ['__nenhuma__'])],
            'texto'    => 'required|string|min:30|max:5000',
        ], [
            'maldicao.in'   => 'Escolha uma maldição da lista disponível.',
            'maldicao.required' => 'Escolha uma maldição da lista disponível.',
            'maldicao.string'   => 'Escolha uma maldição da lista disponível.',
            'texto.string'  => 'Escreva a descrição da maldição.',
            'texto.min'     => 'Descreva a maldição com pelo menos 30 caracteres.',
            'texto.max'     => 'O texto pode ter no máximo 5000 caracteres.',
            'texto.required' => 'Escreva a descrição da maldição.',
        ]);
        if ($validador->fails()) {
            return response()->json([
                'message' => $validador->errors()->first(),
                'errors'  => $validador->errors(),
            ], 422);
        }
        $dados = $validador->validated();

        $espera = $this->segundosRestantes($campanha, (int) $user->id);
        if ($espera > 0) {
            return response()->json(['erro' => 'Aguarde para enviar outro catálogo.', 'espera' => $espera], 429);
        }

        $res = ComparadorCatalogo::comparar($dados['texto'], $dados['maldicao']);

        $registro = CampanhaCatalogo::create([
            'campanha_id'   => $campanha->id,
            'user_id'       => $user->id,
            'maldicao_slug' => $dados['maldicao'],
            'texto'         => $dados['texto'],
            'pontuacao'     => $res['pontuacao'],
            // as penalidades vão junto dos detalhes para o placar poder explicar a nota
            'detalhes'      => $res['detalhes'] + ['penalidades' => $res['penalidades'] ?? []],
        ]);
        $registro->load('user');

        return response()->json([
            'item'   => $this->formatar($registro, $this->nomes(), (int) $user->id, $campanha->ehMestre($user->id)),
            'espera' => self::ESPERA,
        ], 201);
    }
}
