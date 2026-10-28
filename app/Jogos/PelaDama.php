<?php

namespace App\Jogos;

/**
 * Pela Dama: xadrez com peças de movimento customizável. Regras do xadrez normal
 * (xeque, xeque-mate, afogamento, roque, en passant e promoção), mas cada tipo de peça
 * descreve o próprio movimento como uma lista de regras.
 *
 * Coordenadas: x = coluna (0 = a … 7 = h), y = linha (0 = 1ª fila das brancas … 7 = 8ª).
 * As regras são escritas do ponto de vista de quem joga: dy positivo é "para a frente";
 * para as pretas o dy é invertido.
 *
 * Regra: { dx, dy, d (desliza até "alc" casas), alc, m: 'ambos' | 'mover' | 'capturar' }
 * Tipo:  { nome, simbolo, regras: [...], especial: 'rei' | 'peao' | null }
 *
 * Estado (JSON da partida):
 *   tab[y][x] = null | { t: tipo, c: 'b'|'p', m: já moveu? }
 *   tipos, vez, ep ([x,y] da casa de en passant), ultimo, lances, fim
 */
class PelaDama
{
    public const LADO = 8;
    public const MAX_REGRAS = 64;
    public const MODOS = ['ambos', 'mover', 'capturar'];

    /** Peças padrão do xadrez, já no formato de regras. */
    public static function tiposPadrao(): array
    {
        $raios = fn (array $dirs) => array_map(fn ($d) => ['dx' => $d[0], 'dy' => $d[1], 'd' => true, 'alc' => 7, 'm' => 'ambos'], $dirs);
        $saltos = fn (array $dirs) => array_map(fn ($d) => ['dx' => $d[0], 'dy' => $d[1], 'd' => false, 'alc' => 1, 'm' => 'ambos'], $dirs);
        $retas = [[1, 0], [-1, 0], [0, 1], [0, -1]];
        $diags = [[1, 1], [1, -1], [-1, 1], [-1, -1]];

        return [
            'rei'    => ['nome' => 'Rei', 'simbolo' => '♚', 'especial' => 'rei', 'regras' => $saltos(array_merge($retas, $diags))],
            'dama'   => ['nome' => 'Dama', 'simbolo' => '♛', 'especial' => null, 'regras' => $raios(array_merge($retas, $diags))],
            'torre'  => ['nome' => 'Torre', 'simbolo' => '♜', 'especial' => null, 'regras' => $raios($retas)],
            'bispo'  => ['nome' => 'Bispo', 'simbolo' => '♝', 'especial' => null, 'regras' => $raios($diags)],
            'cavalo' => ['nome' => 'Cavalo', 'simbolo' => '♞', 'especial' => null,
                         'regras' => $saltos([[1, 2], [2, 1], [-1, 2], [-2, 1], [1, -2], [2, -1], [-1, -2], [-2, -1]])],
            'peao'   => ['nome' => 'Peão', 'simbolo' => '♟', 'especial' => 'peao', 'regras' => []],
        ];
    }

    /** Exército padrão: 8 peças da fileira de trás (a → h) e 8 da fileira dos peões. */
    public static function exercitoPadrao(): array
    {
        return array_merge(
            ['torre', 'cavalo', 'bispo', 'dama', 'rei', 'bispo', 'cavalo', 'torre'],
            array_fill(0, 8, 'peao')
        );
    }

    /** Limpa as regras vindas do criador de peças. Devolve [] se nada for válido. */
    public static function limparRegras(mixed $regras): array
    {
        $out = [];
        $vistos = [];
        foreach (is_array($regras) ? $regras : [] as $r) {
            if (!is_array($r) || count($out) >= self::MAX_REGRAS) {
                continue;
            }
            $dx = (int) ($r['dx'] ?? 0);
            $dy = (int) ($r['dy'] ?? 0);
            if (($dx === 0 && $dy === 0) || abs($dx) > 7 || abs($dy) > 7) {
                continue;
            }
            $d = !empty($r['d']);
            $alc = $d ? max(1, min(7, (int) ($r['alc'] ?? 7))) : 1;
            $m = in_array($r['m'] ?? '', self::MODOS, true) ? $r['m'] : 'ambos';
            $chave = "$dx,$dy";
            if (isset($vistos[$chave])) {
                continue; // um vetor por casa/direção
            }
            $vistos[$chave] = true;
            $out[] = ['dx' => $dx, 'dy' => $dy, 'd' => $d, 'alc' => $alc, 'm' => $m];
        }

        return $out;
    }

    /**
     * Monta a partida. $tipos: tipos disponíveis (padrão + customizados), $exB / $exP: 16 ids de tipo cada.
     * Os tipos usados ficam guardados no estado (a partida não muda se a peça for editada depois).
     */
    public static function iniciar(array $tipos, array $exB, array $exP): array
    {
        $tab = array_fill(0, self::LADO, array_fill(0, self::LADO, null));
        $usados = [];
        foreach (['b' => $exB, 'p' => $exP] as $cor => $ex) {
            $tras = $cor === 'b' ? 0 : 7;
            $frente = $cor === 'b' ? 1 : 6;
            for ($i = 0; $i < 16; $i++) {
                $t = $ex[$i];
                $usados[$t] = $tipos[$t];
                $tab[$i < 8 ? $tras : $frente][$i % 8] = ['t' => $t, 'c' => $cor, 'm' => false];
            }
        }
        // a promoção vira Dama: garante o tipo mesmo que ninguém tenha escalado uma
        $usados['dama'] = $usados['dama'] ?? self::tiposPadrao()['dama'];

        return ['tab' => $tab, 'tipos' => $usados, 'vez' => 'b', 'ep' => null, 'ultimo' => null, 'lances' => 0, 'fim' => null];
    }

    /** Valida um exército: 16 ids conhecidos e exatamente um Rei. Devolve a mensagem de erro ou null. */
    public static function validarExercito(mixed $ex, array $tipos): ?string
    {
        if (!is_array($ex) || count($ex) !== 16) {
            return 'O exército precisa de 16 peças.';
        }
        $reis = 0;
        foreach ($ex as $t) {
            if (!is_string($t) || !isset($tipos[$t])) {
                return 'O exército tem uma peça que não existe mais.';
            }
            if (($tipos[$t]['especial'] ?? null) === 'rei') {
                $reis++;
            }
        }

        return $reis === 1 ? null : 'O exército precisa de exatamente um Rei.';
    }

    /* ------------------------------------------------------------------ */
    /*  Movimento                                                          */
    /* ------------------------------------------------------------------ */

    private static function dentro(int $x, int $y): bool
    {
        return $x >= 0 && $x < self::LADO && $y >= 0 && $y < self::LADO;
    }

    private static function frente(string $cor): int
    {
        return $cor === 'b' ? 1 : -1;
    }

    private static function outra(string $cor): string
    {
        return $cor === 'b' ? 'p' : 'b';
    }

    /**
     * Casas para onde a peça em (x, y) pode ir sem olhar o xeque.
     * Cada item: [x, y, especial] com especial = '' | 'duplo' | 'ep' | 'roque-c' | 'roque-l'.
     * Com $soAtaque = true devolve só as casas que a peça ameaça (para saber se uma casa está atacada).
     */
    public static function pseudo(array $e, int $x, int $y, bool $soAtaque = false): array
    {
        $p = $e['tab'][$y][$x] ?? null;
        if (!$p) {
            return [];
        }
        $tipo = $e['tipos'][$p['t']] ?? null;
        if (!$tipo) {
            return [];
        }
        $cor = $p['c'];
        $f = self::frente($cor);
        $out = [];

        if (($tipo['especial'] ?? null) === 'peao') {
            foreach ([-1, 1] as $lado) { // captura na diagonal da frente (também en passant)
                $tx = $x + $lado;
                $ty = $y + $f;
                if (!self::dentro($tx, $ty)) {
                    continue;
                }
                $alvo = $e['tab'][$ty][$tx];
                if ($soAtaque) {
                    $out[] = [$tx, $ty, ''];
                } elseif ($alvo && $alvo['c'] !== $cor) {
                    $out[] = [$tx, $ty, ''];
                } elseif (!$alvo && $e['ep'] && $e['ep'][0] === $tx && $e['ep'][1] === $ty) {
                    $out[] = [$tx, $ty, 'ep'];
                }
            }
            if (!$soAtaque && self::dentro($x, $y + $f) && !$e['tab'][$y + $f][$x]) {
                $out[] = [$x, $y + $f, ''];
                if (!$p['m'] && self::dentro($x, $y + 2 * $f) && !$e['tab'][$y + 2 * $f][$x]) {
                    $out[] = [$x, $y + 2 * $f, 'duplo'];
                }
            }

            return $out;
        }

        foreach ($tipo['regras'] as $r) {
            $dx = $r['dx'];
            $dy = $r['dy'] * $f;
            $passos = $r['d'] ? $r['alc'] : 1;
            for ($k = 1; $k <= $passos; $k++) {
                $tx = $x + $dx * $k;
                $ty = $y + $dy * $k;
                if (!self::dentro($tx, $ty)) {
                    break;
                }
                $alvo = $e['tab'][$ty][$tx];
                if ($alvo) {
                    if ($alvo['c'] !== $cor && $r['m'] !== 'mover') {
                        $out[] = [$tx, $ty, ''];
                    } elseif ($soAtaque && $r['m'] !== 'mover') {
                        $out[] = [$tx, $ty, '']; // protege a própria peça: conta como casa atacada
                    }
                    break; // ninguém atravessa peças
                }
                if ($soAtaque) {
                    if ($r['m'] !== 'mover') {
                        $out[] = [$tx, $ty, ''];
                    }
                } elseif ($r['m'] !== 'capturar') {
                    $out[] = [$tx, $ty, ''];
                }
            }
        }

        // roque: Rei e Torre sem terem movido, casas livres, Rei sem passar por casa atacada
        if (!$soAtaque && ($tipo['especial'] ?? null) === 'rei' && !$p['m']) {
            $inimigo = self::outra($cor);
            if (!self::atacada($e, $x, $y, $inimigo)) {
                foreach ([[7, 1, 'roque-c'], [0, -1, 'roque-l']] as [$tx, $dir, $tag]) {
                    $torre = $e['tab'][$y][$tx] ?? null;
                    if (!$torre || $torre['c'] !== $cor || $torre['m'] || $torre['t'] !== 'torre') {
                        continue;
                    }
                    $livre = true;
                    for ($i = $x + $dir; $i !== $tx; $i += $dir) {
                        if ($e['tab'][$y][$i]) {
                            $livre = false;
                            break;
                        }
                    }
                    $destino = $x + 2 * $dir;
                    if ($livre && self::dentro($destino, $y)
                        && !self::atacada($e, $x + $dir, $y, $inimigo) && !self::atacada($e, $destino, $y, $inimigo)) {
                        $out[] = [$destino, $y, $tag];
                    }
                }
            }
        }

        return $out;
    }

    /** A casa (x, y) está ameaçada por alguma peça da cor $por? */
    public static function atacada(array $e, int $x, int $y, string $por): bool
    {
        for ($yy = 0; $yy < self::LADO; $yy++) {
            for ($xx = 0; $xx < self::LADO; $xx++) {
                $p = $e['tab'][$yy][$xx];
                if (!$p || $p['c'] !== $por) {
                    continue;
                }
                foreach (self::pseudo($e, $xx, $yy, true) as $c) {
                    if ($c[0] === $x && $c[1] === $y) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private static function rei(array $e, string $cor): ?array
    {
        for ($y = 0; $y < self::LADO; $y++) {
            for ($x = 0; $x < self::LADO; $x++) {
                $p = $e['tab'][$y][$x];
                if ($p && $p['c'] === $cor && (($e['tipos'][$p['t']]['especial'] ?? null) === 'rei')) {
                    return [$x, $y];
                }
            }
        }

        return null;
    }

    public static function emXeque(array $e, string $cor): bool
    {
        $r = self::rei($e, $cor);

        return $r !== null && self::atacada($e, $r[0], $r[1], self::outra($cor));
    }

    /** Aplica o lance sem validar (usado também para testar se o lance deixa o Rei em xeque). */
    private static function aplicar(array $e, int $x, int $y, array $mv): array
    {
        [$tx, $ty, $tag] = $mv;
        $p = $e['tab'][$y][$x];
        $capturou = $e['tab'][$ty][$tx] !== null;
        $e['tab'][$y][$x] = null;
        if ($tag === 'ep') {
            $e['tab'][$y][$tx] = null; // o peão capturado está ao lado, não na casa de destino
            $capturou = true;
        }
        if ($tag === 'roque-c' || $tag === 'roque-l') {
            $ox = $tag === 'roque-c' ? 7 : 0;
            $nx = $tag === 'roque-c' ? $tx - 1 : $tx + 1;
            $e['tab'][$y][$nx] = array_merge($e['tab'][$y][$ox], ['m' => true]);
            $e['tab'][$y][$ox] = null;
        }
        $p['m'] = true;
        $promo = false;
        if (($e['tipos'][$p['t']]['especial'] ?? null) === 'peao' && $ty === ($p['c'] === 'b' ? 7 : 0)) {
            $p['t'] = 'dama';
            $promo = true;
        }
        $e['tab'][$ty][$tx] = $p;
        $e['ep'] = $tag === 'duplo' ? [$x, intdiv($y + $ty, 2)] : null;
        $e['ultimo'] = ['de' => [$x, $y], 'para' => [$tx, $ty], 'captura' => $capturou, 'promo' => $promo];

        return $e;
    }

    /** Lances legais da peça em (x, y): os que não deixam o próprio Rei em xeque. */
    public static function legais(array $e, int $x, int $y): array
    {
        $p = $e['tab'][$y][$x] ?? null;
        if (!$p) {
            return [];
        }

        return array_values(array_filter(
            self::pseudo($e, $x, $y),
            fn ($mv) => !self::emXeque(self::aplicar($e, $x, $y, $mv), $p['c'])
        ));
    }

    public static function temLance(array $e, string $cor): bool
    {
        for ($y = 0; $y < self::LADO; $y++) {
            for ($x = 0; $x < self::LADO; $x++) {
                $p = $e['tab'][$y][$x];
                if ($p && $p['c'] === $cor && self::legais($e, $x, $y)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Todos os lances legais de quem tem a vez, por casa de origem "x,y" (vai para o navegador desenhar). */
    public static function mapaLegais(array $e): array
    {
        $out = [];
        if ($e['fim']) {
            return $out;
        }
        for ($y = 0; $y < self::LADO; $y++) {
            for ($x = 0; $x < self::LADO; $x++) {
                $p = $e['tab'][$y][$x];
                if ($p && $p['c'] === $e['vez']) {
                    $l = self::legais($e, $x, $y);
                    if ($l) {
                        $out["$x,$y"] = array_map(fn ($m) => [$m[0], $m[1]], $l);
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Joga um lance de quem tem a vez. Devolve o novo estado ou lança \InvalidArgumentException.
     * Depois do lance, vê se o outro levou xeque-mate ou ficou afogado.
     */
    public static function mover(array $e, string $cor, int $x, int $y, int $tx, int $ty): array
    {
        if ($e['fim']) {
            throw new \InvalidArgumentException('A partida já acabou.');
        }
        if ($e['vez'] !== $cor) {
            throw new \InvalidArgumentException('Não é a sua vez.');
        }
        if (!self::dentro($x, $y) || !self::dentro($tx, $ty)) {
            throw new \InvalidArgumentException('Casa inválida.');
        }
        $p = $e['tab'][$y][$x];
        if (!$p || $p['c'] !== $cor) {
            throw new \InvalidArgumentException('Escolha uma peça sua.');
        }
        $mv = null;
        foreach (self::legais($e, $x, $y) as $m) {
            if ($m[0] === $tx && $m[1] === $ty) {
                $mv = $m;
                break;
            }
        }
        if (!$mv) {
            throw new \InvalidArgumentException('Essa peça não pode ir para essa casa.');
        }

        $e = self::aplicar($e, $x, $y, $mv);
        $e['lances']++;
        $outra = self::outra($cor);
        $e['vez'] = $outra;
        $e['xeque'] = self::emXeque($e, $outra);
        if (!self::temLance($e, $outra)) {
            $e['fim'] = $e['xeque']
                ? ['motivo' => 'xeque-mate', 'vencedor' => $cor]
                : ['motivo' => 'afogamento', 'vencedor' => null];
        }

        return $e;
    }
}
