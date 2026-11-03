<?php

namespace App\Http\Controllers;

use App\Support\Esquema;
use App\Models\Campanha;
use App\Models\Kuro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Kuros da campanha. O mestre cria, edita, organiza e entrega Kuros a jogadores; o jogador vê os Kuros que recebeu e
 * controla a vida atual e as anotações deles. A ficha é calculada no navegador a partir de config/kuros.php.
 */
class KuroController extends Controller
{
    public function index(Campanha $campanha): View
    {
        $uid = (int) auth()->id();
        abort_unless($campanha->temMembro($uid), 403, 'Você não participa desta campanha.');
        $mestre = $campanha->ehMestre($uid);

        return view('campanhas.kuros', [
            'campanha' => $campanha,
            'souMestre' => $mestre,
            'tabela'   => Esquema::tabela('kuros'),
            'kuros'    => $this->lista($campanha, $uid, $mestre),
            'membros'  => $mestre ? $this->jogadores($campanha) : [],
        ]);
    }

    public function salvar(Request $request, Campanha $campanha): JsonResponse
    {
        $this->exigirMestre($campanha);
        abort_unless(Esquema::tabela('kuros'), 422, 'A tabela de Kuros ainda não existe: rode o SQL da migração 2026_11_02_000021.');
        $d = $this->validar($request, true);
        $kuro = !empty($d['id']) ? Kuro::where('campanha_id', $campanha->id)->findOrFail((int) $d['id']) : new Kuro(['campanha_id' => $campanha->id]);
        $kuro->fill(['nome' => trim($d['nome']), 'dados' => $d['dados'], 'notas' => $d['notas'] ?? $kuro->notas]);
        if (array_key_exists('pv_atual', $d)) {
            $kuro->pv_atual = $d['pv_atual'];
        }
        $kuro->save();

        return response()->json(['ok' => true, 'kuro' => $kuro->paraTela()]);
    }

    /** Vida atual e anotações: o mestre ou o jogador que recebeu o Kuro. */
    public function estado(Request $request, Campanha $campanha, Kuro $kuro): JsonResponse
    {
        $uid = (int) auth()->id();
        abort_unless((int) $kuro->campanha_id === (int) $campanha->id, 404);
        abort_unless($campanha->ehMestre($uid) || (int) $kuro->user_id === $uid, 403, 'Esse Kuro não é seu.');
        $d = $request->validate([
            'pv_atual' => ['nullable', 'integer', 'min:-999999', 'max:999999'],
            'notas'    => ['nullable', 'string', 'max:5000'],
        ]);
        if ($request->has('pv_atual')) {
            $kuro->pv_atual = $d['pv_atual'];
        }
        if ($request->has('notas')) {
            $kuro->notas = $d['notas'] ?? '';
        }
        $kuro->save();

        return response()->json(['ok' => true, 'kuro' => $kuro->paraTela($campanha->ehMestre($uid))]);
    }

    /** O mestre entrega o Kuro a um jogador da campanha (ou tira, com user_id vazio). */
    public function dar(Request $request, Campanha $campanha, Kuro $kuro): JsonResponse
    {
        $this->exigirMestre($campanha);
        abort_unless((int) $kuro->campanha_id === (int) $campanha->id, 404);
        $user = $request->validate(['user_id' => ['nullable', 'integer']])['user_id'] ?? null;
        if ($user !== null && !$campanha->temMembro((int) $user)) {
            return response()->json(['ok' => false, 'erro' => 'Essa pessoa não participa da campanha.'], 422);
        }
        $kuro->user_id = $user ? (int) $user : null;
        $kuro->save();

        return response()->json(['ok' => true, 'kuro' => $kuro->paraTela()]);
    }

    public function excluir(Campanha $campanha, Kuro $kuro): JsonResponse
    {
        $this->exigirMestre($campanha);
        abort_unless((int) $kuro->campanha_id === (int) $campanha->id, 404);
        $kuro->delete();

        return response()->json(['ok' => true]);
    }

    private function validar(Request $request, bool $tudo): array
    {
        $d = $request->validate([
            'id'       => ['nullable', 'integer'],
            'nome'     => ['required', 'string', 'max:80'],
            'dados'    => ['required', 'array'],
            'pv_atual' => ['nullable', 'integer', 'min:-999999', 'max:999999'],
            'notas'    => ['nullable', 'string', 'max:5000'],
        ]);
        // ficha do Kuro: só as chaves conhecidas, com tamanhos limitados
        $f = $d['dados'];
        $num = fn ($v, $min, $max) => max($min, min($max, (int) $v));
        $mapa = fn ($v, $chaves) => collect(is_array($v) ? $v : [])->only($chaves)->map(fn ($n) => $num($n, 0, 99))->filter()->all();
        $cfg = config('kuros');
        $texto = fn ($v, $max) => mb_substr(trim((string) ($v ?? '')), 0, $max);
        $d['dados'] = [
            'descricao'     => $texto($f['descricao'] ?? '', 3000),
            'comportamento' => $texto($f['comportamento'] ?? '', 2000),
            'surgimento'    => $texto($f['surgimento'] ?? '', 2000),
            'daemon'        => $texto($f['daemon'] ?? '', 40),
            'pasta'         => $texto($f['pasta'] ?? '', 60),
            'tipo'          => $num($f['tipo'] ?? 1, 1, 9),
            'circulos'      => $num($f['circulos'] ?? 0, 0, 999),
            'tamanho'       => $num($f['tamanho'] ?? 0, -3, 3),
            'aprim'         => $mapa($f['aprim'] ?? [], array_keys($cfg['aprimoramentos'])),
            'modo_pvs'      => ($f['modo_pvs'] ?? 'con') === 'tipo' ? 'tipo' : 'con',
            'modo_armadura' => ($f['modo_armadura'] ?? 'con') === 'tipo' ? 'tipo' : 'con',
            'atributos'     => $mapa($f['atributos'] ?? [], array_keys($cfg['atributos'])),
            'rolagens'      => $mapa($f['rolagens'] ?? [], array_keys($cfg['rolagens'])),
            'resistencias'  => array_values(array_intersect((array) ($f['resistencias'] ?? []), $cfg['tipos_dano'])),
            'fraquezas'     => array_values(array_intersect((array) ($f['fraquezas'] ?? []), $cfg['tipos_dano'])),
            'sentidos'      => array_values(array_slice(array_filter(array_map(fn ($s) => $texto($s, 40), (array) ($f['sentidos'] ?? []))), 0, 12)),
            'tipo_dano'     => in_array($f['tipo_dano'] ?? '', $cfg['tipos_dano'], true) ? $f['tipo_dano'] : 'cortante',
            'habilidades'   => $mapa($f['habilidades'] ?? [], array_keys($cfg['habilidades'])),
            'notas_mestre'  => $texto($f['notas_mestre'] ?? '', 3000),
        ];

        return $d;
    }

    private function lista(Campanha $campanha, int $uid, bool $mestre): array
    {
        if (!Esquema::tabela('kuros')) {
            return [];
        }
        $q = Kuro::where('campanha_id', $campanha->id)->orderBy('nome');
        if (!$mestre) {
            $q->where('user_id', $uid); // o jogador só vê os Kuros que recebeu
        }

        return $q->get()->map(fn (Kuro $k) => $k->paraTela($mestre))->all();
    }

    /** Jogadores ativos da campanha (para entregar Kuros). */
    private function jogadores(Campanha $campanha): array
    {
        $campanha->loadMissing('participantes.user');

        return $campanha->participantes->where('status', 'ativo')->filter(fn ($p) => $p->user)
            ->map(fn ($p) => ['id' => (int) $p->user->id, 'nome' => (string) $p->user->name])->values()->all();
    }

    private function exigirMestre(Campanha $campanha): void
    {
        abort_unless($campanha->ehMestre(auth()->id()), 403, 'Apenas o mestre pode fazer isso.');
    }
}
