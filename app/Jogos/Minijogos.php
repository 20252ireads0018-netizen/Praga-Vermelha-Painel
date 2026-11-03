<?php

namespace App\Jogos;

/**
 * Minijogos de aposta fictícia (só com o Dinheiro dos personagens): Mines, Aviator e caça-níquel.
 * Tudo que é sorte sai daqui, no servidor; a tela só anima o resultado. A casa fica com cerca de 3–4%.
 */
class Minijogos
{
    public const CASAS = 25;          // Mines: grade 5×5
    public const MARGEM = 0.97;       // o que volta para o jogador, em média (Mines e Aviator)
    public const VELOCIDADE = 0.08;   // Aviator: multiplicador = e^(0.08 · segundos)
    public const TETO_AVIAO = 1000.0;

    /* ---------- Mines ---------- */

    /** Sorteia onde ficam as minas: lista de índices 0..24. */
    public static function sortearMinas(int $minas, ?callable $rand = null): array
    {
        $rand ??= fn (int $a, int $b) => random_int($a, $b);
        $casas = range(0, self::CASAS - 1);
        for ($i = self::CASAS - 1; $i > 0; $i--) { // Fisher–Yates
            $j = $rand(0, $i);
            [$casas[$i], $casas[$j]] = [$casas[$j], $casas[$i]];
        }
        $lista = array_slice($casas, 0, $minas);
        sort($lista);

        return $lista;
    }

    /** Multiplicador depois de abrir $abertas casas seguras com $minas minas (justo × margem, 2 casas, para baixo). */
    public static function multMines(int $minas, int $abertas): float
    {
        if ($abertas <= 0) {
            return 1.0;
        }
        $m = self::MARGEM;
        for ($i = 0; $i < $abertas; $i++) {
            $m *= (self::CASAS - $i) / (self::CASAS - $minas - $i);
        }

        return floor($m * 100 + 1e-9) / 100;
    }

    /* ---------- Aviator ---------- */

    /** Ponto em que o voo cai (1,00 = cai na hora). $u: número em [0, 1). */
    public static function sortearQueda(?float $u = null): float
    {
        $u ??= random_int(0, PHP_INT_MAX - 1) / PHP_INT_MAX;
        $q = floor(100 * self::MARGEM / (1 - $u)) / 100;

        return max(1.0, min(self::TETO_AVIAO, $q));
    }

    /** Multiplicador depois de $seg segundos de voo. */
    public static function multAviao(float $seg): float
    {
        return floor(exp(self::VELOCIDADE * max(0.0, $seg)) * 100 + 1e-9) / 100;
    }

    /** Segundos até o multiplicador chegar a $mult. */
    public static function tempoAte(float $mult): float
    {
        return log(max(1.0, $mult)) / self::VELOCIDADE;
    }

    /* ---------- Caça-níquel ---------- */

    /** Símbolos do rolo: [id => [nome, peso, paga 3 iguais, paga 2 iguais (1º e 2º rolo)]]. */
    public const SIMBOLOS = [
        'rosa'    => ['Rosa vermelha', 30, 5, 1],
        'olho'    => ['Olho do profano', 22, 10, 2],
        'selo'    => ['Selo hexomante', 16, 20, 3],
        'caveira' => ['Caveira', 12, 40, 4],
        'kuro'    => ['Kuro', 7, 120, 6],
        'vassago' => ['Coroa de Vassago', 3, 600, 10],
    ];

    /** Gira os três rolos. */
    public static function girar(?callable $rand = null): array
    {
        $rand ??= fn (int $a, int $b) => random_int($a, $b);
        $total = array_sum(array_column(self::SIMBOLOS, 1));
        $rolos = [];
        for ($r = 0; $r < 3; $r++) {
            $n = $rand(1, $total);
            foreach (self::SIMBOLOS as $id => $s) {
                if (($n -= $s[1]) <= 0) {
                    $rolos[] = $id;
                    break;
                }
            }
        }

        return $rolos;
    }

    /** Quantas vezes a aposta o resultado paga (0 = perdeu). */
    public static function premioSlot(array $rolos): int
    {
        [$a, $b, $c] = $rolos;
        if ($a === $b && $b === $c) {
            return self::SIMBOLOS[$a][2];
        }
        if ($a === $b) {
            return self::SIMBOLOS[$a][3];
        }

        return 0;
    }

    /** Retorno médio do caça-níquel (para conferir a margem). */
    public static function retornoSlot(): float
    {
        $total = array_sum(array_column(self::SIMBOLOS, 1));
        $r = 0.0;
        foreach (self::SIMBOLOS as $a => $sa) {
            foreach (self::SIMBOLOS as $b => $sb) {
                foreach (self::SIMBOLOS as $c => $sc) {
                    $r += ($sa[1] * $sb[1] * $sc[1]) / $total ** 3 * self::premioSlot([$a, $b, $c]);
                }
            }
        }

        return $r;
    }
}
