<?php

namespace App\Http\Controllers;

use App\Jogos\Minijogos;
use App\Models\Campanha;
use App\Models\CampanhaFicha;
use App\Models\JogoCarteira;
use App\Models\Personagem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Minijogos da campanha (Mines, Aviator e caça-níquel): apostas fictícias com o Dinheiro de um personagem vinculado
 * (ou da carteira da campanha). A aposta sai no começo da rodada e o prêmio volta no fim. A rodada em andamento
 * (onde estão as minas, quando o voo cai) fica só no servidor, no cache; a tela recebe o que já pode ser mostrado.
 */
class MinijogoController extends Controller
{
    private const GRACA_AVIAO = 0.2; // segundos descontados no "sacar" do Aviator (atraso da rede)

    public function index(Campanha $campanha): View|\Illuminate\Http\RedirectResponse
    {
        $uid = $this->exigirMembro($campanha);
        if (!$campanha->cassinoLiberado($uid)) {
            return redirect()->route('campanhas.show', $campanha)->withErrors(['cassino' => 'O mestre fechou o cassino desta campanha para você.']);
        }

        return view('campanhas.minijogos', [
            'campanha' => $campanha,
            'dados'    => [
                'saldos'   => $this->saldos($campanha, $uid),
                'rodada'   => $this->publica($this->rodada($campanha, $uid)),
                'simbolos' => collect(Minijogos::SIMBOLOS)->map(fn ($s, $id) => ['id' => $id, 'nome' => $s[0], 'tres' => $s[2], 'dois' => $s[3]])->values(),
                'velocidade' => Minijogos::VELOCIDADE,
            ],
        ]);
    }

    /** Ações: mines (iniciar, abrir, sacar), aviator (iniciar, estado, sacar), slot (girar). */
    public function acao(Request $request, Campanha $campanha, string $jogo, string $acao): JsonResponse
    {
        $uid = $this->exigirMembro($campanha);
        $metodo = $jogo . ucfirst($acao);
        abort_unless(in_array($metodo, ['minesIniciar', 'minesAbrir', 'minesSacar', 'aviatorIniciar', 'aviatorEstado', 'aviatorSacar', 'slotGirar'], true), 404);
        // cassino fechado pelo mestre: não começa rodada nova (a que já está em jogo ainda termina e paga)
        if (in_array($metodo, ['minesIniciar', 'aviatorIniciar', 'slotGirar'], true) && !$campanha->cassinoLiberado($uid)) {
            return response()->json(['ok' => false, 'erro' => 'O mestre fechou o cassino desta campanha para você.'], 403);
        }

        try {
            $r = Cache::lock("minijogo-trava:{$campanha->id}:$uid", 10)->block(5, fn () => $this->$metodo($request, $campanha, $uid));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'saldos' => $this->saldos($campanha, $uid)] + $r);
    }

    /* ---------- Mines ---------- */

    private function minesIniciar(Request $request, Campanha $campanha, int $uid): array
    {
        $d = $request->validate(['minas' => ['required', 'integer', 'min:1', 'max:24']] + $this->regrasAposta());
        $this->semRodada($campanha, $uid);
        [$ficha, $casa] = $this->apostar($campanha, $uid, $d);
        $r = ['jogo' => 'mines', 'aposta' => (int) $d['aposta'], 'ficha' => $ficha, 'minas' => (int) $d['minas'],
            'bombas' => Minijogos::sortearMinas((int) $d['minas']), 'abertas' => [], 'casa' => $casa];
        $this->guardar($campanha, $uid, $r);

        return ['rodada' => $this->publica($r)];
    }

    private function minesAbrir(Request $request, Campanha $campanha, int $uid): array
    {
        $casa = (int) $request->validate(['casa' => ['required', 'integer', 'min:0', 'max:' . (Minijogos::CASAS - 1)]])['casa'];
        $r = $this->exigirRodada($campanha, $uid, 'mines');
        if (in_array($casa, $r['abertas'], true)) {
            return ['rodada' => $this->publica($r)];
        }
        $eraMina = in_array($casa, $r['bombas'], true);
        $mina = Minijogos::minaDaCasa($eraMina, count($r['abertas']), $r['minas'], $r['aposta'], $r['casa'] ?? ['premio_max' => PHP_INT_MAX, 'isca' => false, 'lucro' => 0]);
        if ($eraMina !== $mina) { // a Casa troca uma mina de lugar (a contagem de minas não muda)
            $livres = array_values(array_diff(range(0, Minijogos::CASAS - 1), $r['bombas'], $r['abertas'], [$casa]));
            if ($mina) {
                $r['bombas'] = array_values(array_diff($r['bombas'], [$r['bombas'][array_rand($r['bombas'])]]));
                $r['bombas'][] = $casa;
            } elseif ($livres) {
                $r['bombas'] = array_values(array_diff($r['bombas'], [$casa]));
                $r['bombas'][] = $livres[array_rand($livres)];
            } else {
                $mina = true;
            }
            sort($r['bombas']);
        }
        if ($mina) { // mina: perde a aposta
            $this->encerrar($campanha, $uid);
            $faltou = Minijogos::multMines($r['minas'], count($r['abertas']) + 1);

            return ['fim' => ['ganhou' => false, 'premio' => 0, 'casa' => $casa, 'bombas' => $r['bombas'], 'abertas' => $r['abertas'], 'faltou' => $faltou]];
        }
        $r['abertas'][] = $casa;
        if (count($r['abertas']) === Minijogos::CASAS - $r['minas']) { // abriu todas as seguras: saca sozinho
            return $this->minesPagar($campanha, $uid, $r);
        }
        $this->guardar($campanha, $uid, $r);

        return ['rodada' => $this->publica($r)];
    }

    private function minesSacar(Request $request, Campanha $campanha, int $uid): array
    {
        $r = $this->exigirRodada($campanha, $uid, 'mines');
        if (!$r['abertas']) {
            throw new \InvalidArgumentException('Abra pelo menos uma casa antes de sacar.');
        }

        return $this->minesPagar($campanha, $uid, $r);
    }

    private function minesPagar(Campanha $campanha, int $uid, array $r): array
    {
        $mult = Minijogos::multMines($r['minas'], count($r['abertas']));
        $premio = (int) floor($r['aposta'] * $mult);
        $this->pagar($campanha, $uid, $r['ficha'], $premio);
        $this->encerrar($campanha, $uid);

        return ['fim' => ['ganhou' => true, 'premio' => $premio, 'mult' => $mult, 'bombas' => $r['bombas'], 'abertas' => $r['abertas']]];
    }

    /* ---------- Aviator ---------- */

    private function aviatorIniciar(Request $request, Campanha $campanha, int $uid): array
    {
        $d = $request->validate(['auto' => ['nullable', 'numeric', 'min:1.01', 'max:' . Minijogos::TETO_AVIAO]] + $this->regrasAposta());
        $this->semRodada($campanha, $uid);
        [$ficha, $casa] = $this->apostar($campanha, $uid, $d);
        $r = ['jogo' => 'aviator', 'aposta' => (int) $d['aposta'], 'ficha' => $ficha, 'queda' => Minijogos::quedaDaCasa(Minijogos::sortearQueda(), $casa, (int) $d['aposta']),
            'inicio' => microtime(true), 'auto' => isset($d['auto']) ? round((float) $d['auto'], 2) : null];
        $this->guardar($campanha, $uid, $r);

        return ['rodada' => $this->publica($r)];
    }

    private function aviatorEstado(Request $request, Campanha $campanha, int $uid): array
    {
        $r = $this->rodada($campanha, $uid);
        if (!$r || $r['jogo'] !== 'aviator') {
            return ['rodada' => null];
        }

        return $this->aviatorResolver($campanha, $uid, $r, microtime(true) - $r['inicio'], false);
    }

    private function aviatorSacar(Request $request, Campanha $campanha, int $uid): array
    {
        $r = $this->exigirRodada($campanha, $uid, 'aviator');

        return $this->aviatorResolver($campanha, $uid, $r, microtime(true) - $r['inicio'] - self::GRACA_AVIAO, true);
    }

    /** Vê em que pé está o voo depois de $seg segundos: saque automático, queda, saque pedido ou ainda voando. */
    private function aviatorResolver(Campanha $campanha, int $uid, array $r, float $seg, bool $sacar): array
    {
        $agora = Minijogos::multAviao($seg);
        $pagar = null;
        if ($r['auto'] !== null && $r['auto'] < $r['queda'] && $agora >= $r['auto']) {
            $pagar = $r['auto']; // o saque automático chegou antes da queda
        } elseif ($agora >= $r['queda']) {
            $this->encerrar($campanha, $uid);

            return ['fim' => ['ganhou' => false, 'premio' => 0, 'queda' => $r['queda']]];
        } elseif ($sacar) {
            $pagar = $agora;
        }
        if ($pagar === null) {
            return ['rodada' => $this->publica($r)];
        }
        $premio = (int) floor($r['aposta'] * $pagar);
        $this->pagar($campanha, $uid, $r['ficha'], $premio);
        $this->encerrar($campanha, $uid);

        return ['fim' => ['ganhou' => true, 'premio' => $premio, 'mult' => $pagar, 'queda' => $r['queda']]];
    }

    /* ---------- Caça-níquel ---------- */

    private function slotGirar(Request $request, Campanha $campanha, int $uid): array
    {
        $d = $request->validate($this->regrasAposta());
        $this->semRodada($campanha, $uid);
        [$ficha, $casa] = $this->apostar($campanha, $uid, $d);
        [$rolos, $quase] = Minijogos::rolosDaCasa(Minijogos::girar(), (int) $d['aposta'], $casa);
        $vezes = Minijogos::premioSlot($rolos);
        $premio = (int) $d['aposta'] * $vezes;
        if ($premio > 0) {
            $this->pagar($campanha, $uid, $ficha, $premio);
        }

        return ['fim' => ['ganhou' => $premio > 0, 'premio' => $premio, 'vezes' => $vezes, 'rolos' => $rolos, 'quase' => $quase]];
    }

    /* ---------- rodada no cache ---------- */

    private function chave(Campanha $campanha, int $uid): string
    {
        return "minijogo:{$campanha->id}:$uid";
    }

    private function rodada(Campanha $campanha, int $uid): ?array
    {
        $r = Cache::get($this->chave($campanha, $uid));

        return is_array($r) ? $r : null;
    }

    private function guardar(Campanha $campanha, int $uid, array $r): void
    {
        Cache::put($this->chave($campanha, $uid), $r, now()->addHours(6));
    }

    private function encerrar(Campanha $campanha, int $uid): void
    {
        Cache::forget($this->chave($campanha, $uid));
    }

    private function semRodada(Campanha $campanha, int $uid): void
    {
        if ($r = $this->rodada($campanha, $uid)) {
            throw new \InvalidArgumentException($r['jogo'] === 'mines' ? 'Você tem uma rodada de Mines em andamento: saque ou continue abrindo.' : 'Você tem um voo do Aviator em andamento.');
        }
    }

    private function exigirRodada(Campanha $campanha, int $uid, string $jogo): array
    {
        $r = $this->rodada($campanha, $uid);
        if (!$r || $r['jogo'] !== $jogo) {
            throw new \InvalidArgumentException('Essa rodada já terminou.');
        }

        return $r;
    }

    /** O que a tela pode ver da rodada (nunca onde estão as minas nem quando o voo cai). */
    private function publica(?array $r): ?array
    {
        if (!$r) {
            return null;
        }
        $base = ['jogo' => $r['jogo'], 'aposta' => $r['aposta'], 'ficha' => $r['ficha']];
        if ($r['jogo'] === 'mines') {
            return $base + ['minas' => $r['minas'], 'abertas' => $r['abertas'], 'mult' => Minijogos::multMines($r['minas'], count($r['abertas'])),
                'proximo' => count($r['abertas']) < Minijogos::CASAS - $r['minas'] ? Minijogos::multMines($r['minas'], count($r['abertas']) + 1) : null];
        }

        return $base + ['decorrido' => round(microtime(true) - $r['inicio'], 3), 'auto' => $r['auto']];
    }

    /* ---------- Dinheiro ---------- */

    private function regrasAposta(): array
    {
        return ['aposta' => ['required', 'integer', 'min:1', 'max:1000000000'], 'ficha' => ['nullable', 'integer', 'min:0']];
    }

    /** Fichas do jogador vinculadas à campanha que têm Dinheiro: [{ id, nome, dinheiro }]. */
    private function fichas(Campanha $campanha, int $uid): array
    {
        if (!Schema::hasColumn('fichas', 'dinheiro')) {
            return [];
        }
        $ids = CampanhaFicha::where('campanha_id', $campanha->id)->pluck('ficha_id');

        return Personagem::withoutGlobalScopes()->whereIn('id', $ids)->where('usuario_id', $uid)->orderBy('nome')
            ->get(['id', 'nome', 'dinheiro'])->map(fn ($f) => ['id' => (int) $f->id, 'nome' => (string) $f->nome, 'dinheiro' => (int) $f->dinheiro])->all();
    }

    private function saldos(Campanha $campanha, int $uid): array
    {
        return [
            'carteira' => (int) (JogoCarteira::where('campanha_id', $campanha->id)->where('user_id', $uid)->value('dinheiro') ?? 0),
            'fichas'   => $this->fichas($campanha, $uid),
        ];
    }

    /**
     * Tira a aposta do personagem escolhido (ou da carteira). Devolve [ficha usada (null = carteira), condições da Casa].
     * A "noite de cassino" de cada personagem/carteira guarda quanto ele tinha ao começar (a base do teto) e quantas
     * rodadas já jogou; dura NOITE_HORAS e recomeça se o saldo passar do teto por fora (o mestre deu Dinheiro, por exemplo).
     */
    private function apostar(Campanha $campanha, int $uid, array $d): array
    {
        $ficha = (int) ($d['ficha'] ?? 0) ?: null;
        $fichas = $this->fichas($campanha, $uid);
        if ($ficha !== null && !in_array($ficha, array_column($fichas, 'id'), true)) {
            throw new \InvalidArgumentException('Escolha um personagem seu vinculado a esta campanha.');
        }
        $saldo = $ficha !== null ? (int) collect($fichas)->firstWhere('id', $ficha)['dinheiro'] : $this->saldos($campanha, $uid)['carteira'];
        $chave = "minijogo-noite:{$campanha->id}:$uid:" . ($ficha ?? 'carteira');
        $noite = Cache::get($chave);
        if (!is_array($noite) || ($noite['ate'] ?? 0) < time() || $saldo > Minijogos::teto((int) $noite['base'])) {
            $noite = ['base' => $saldo, 'rodadas' => 0, 'ate' => time() + Minijogos::NOITE_HORAS * 3600];
        }
        $casa = Minijogos::casa($saldo, (int) $d['aposta'], (int) $noite['base'], (int) $noite['rodadas']);
        $this->mover($campanha, $uid, $ficha, -(int) $d['aposta']);
        $noite['rodadas']++;
        Cache::put($chave, $noite, now()->addHours(Minijogos::NOITE_HORAS + 1));

        return [$ficha, $casa];
    }

    private function pagar(Campanha $campanha, int $uid, ?int $ficha, int $premio): void
    {
        $this->mover($campanha, $uid, $ficha, $premio);
    }

    private function mover(Campanha $campanha, int $uid, ?int $ficha, int $quanto): void
    {
        DB::transaction(function () use ($campanha, $uid, $ficha, $quanto) {
            $f = $ficha !== null ? Personagem::withoutGlobalScopes()->lockForUpdate()->find($ficha) : null;
            if ($f && (int) $f->usuario_id !== $uid) {
                if ($quanto < 0) {
                    throw new \InvalidArgumentException('Esse personagem não é mais seu.');
                }
                $f = null; // o prêmio vai para a carteira da campanha
            }
            if ($f) {
                if ((int) $f->dinheiro + $quanto < 0) {
                    throw new \InvalidArgumentException("{$f->nome} não tem Dinheiro suficiente para essa aposta.");
                }
                $f->forceFill(['dinheiro' => (int) $f->dinheiro + $quanto])->save();

                return;
            }
            JogoCarteira::firstOrCreate(['campanha_id' => $campanha->id, 'user_id' => $uid], ['dinheiro' => 0]);
            $c = JogoCarteira::where('campanha_id', $campanha->id)->where('user_id', $uid)->lockForUpdate()->firstOrFail();
            if ($c->dinheiro + $quanto < 0) {
                throw new \InvalidArgumentException('Você não tem Dinheiro suficiente na carteira da campanha.');
            }
            $c->dinheiro += $quanto;
            $c->save();
        });
    }

    private function exigirMembro(Campanha $campanha): int
    {
        $uid = (int) auth()->id();
        abort_unless($campanha->temMembro($uid), 403, 'Você não participa desta campanha.');

        return $uid;
    }
}
