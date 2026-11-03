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

    /* ---------- A Casa ----------
     * Cassino de um mundo distópico: a Casa nunca perde. A tabela de prêmios e as chances mostradas na tela são as de
     * cima; por baixo, numa "noite de cassino" (24 h por personagem/carteira) a Casa:
     *  - nunca deixa o saldo passar de 1,5× o que a pessoa tinha ao começar a noite (TETO_LUCRO);
     *  - dá sorte nas primeiras rodadas, enquanto a pessoa não está no lucro (a isca);
     *  - aperta conforme a pessoa lucra (quanto mais perto do teto, mais minas, quedas mais cedo e prêmios que escapam);
     *  - mostra "quase" de propósito no caça-níquel.
     * Sempre como sorte: uma casa que vira mina, um corvo que cai, um rolo que para um símbolo antes.
     * $casa = ['premio_max' => maior prêmio que cabe no teto, 'isca' => bool, 'lucro' => 0..1 (quanto do teto já foi)]
     */
    public const TETO_LUCRO = 0.5;
    public const ISCA_RODADAS = 3;
    public const NOITE_HORAS = 24;

    public static function teto(int $base): int
    {
        return (int) floor($base * (1 + self::TETO_LUCRO));
    }

    /** As condições da Casa para uma aposta: $saldo antes da aposta, $base da noite e quantas rodadas já houve. */
    public static function casa(int $saldo, int $aposta, int $base, int $rodadas): array
    {
        $folga = max(1, (int) floor($base * self::TETO_LUCRO));

        return [
            'premio_max' => max(0, self::teto($base) - ($saldo - $aposta)),
            'isca'       => $rodadas < self::ISCA_RODADAS && $saldo <= $base,
            'lucro'      => max(0.0, min(1.0, ($saldo - $base) / $folga)),
        ];
    }

    /** Aviator: onde o voo cai de verdade. */
    public static function quedaDaCasa(float $queda, array $casa, int $aposta, ?float $u = null): float
    {
        $u ??= random_int(0, 1000) / 1000;
        if ($casa['isca']) {
            $queda = max($queda, round(1.6 + $u * 1.4, 2)); // a isca: o corvo voa alto nas primeiras
        }
        if ($casa['lucro'] > 0) {
            $queda = max(1.0, floor((1 + ($queda - 1) * (1 - 0.6 * $casa['lucro'])) * 100) / 100); // quem está ganhando voa menos
        }
        $limite = floor(100 * $casa['premio_max'] / max(1, $aposta)) / 100; // o maior multiplicador que cabe no teto
        if ($queda > $limite) {
            $queda = $limite < 1.01 ? 1.0 : $limite - 0.01;
        }

        return max(1.0, $queda);
    }

    /**
     * Mines: a casa clicada é mina? (null = deixa como o sorteio colocou). A isca tira a mina do primeiro clique;
     * o teto e o lucro põem uma mina onde não havia.
     */
    public static function minaDaCasa(bool $eraMina, int $abertas, int $minas, int $aposta, array $casa, ?float $u = null): bool
    {
        $u ??= random_int(0, 1000) / 1000;
        if ($eraMina) {
            return !($casa['isca'] && $abertas === 0);
        }
        if ((int) floor($aposta * self::multMines($minas, $abertas + 1)) > $casa['premio_max']) {
            return true;
        }

        return $casa['lucro'] > 0 && $u < 0.25 * $casa['lucro'];
    }

    /** Caça-níquel: os rolos que a Casa deixa sair, e se foi um "quase" de propósito. */
    public static function rolosDaCasa(array $rolos, int $aposta, array $casa, ?callable $rand = null): array
    {
        $rand ??= fn (int $a, int $b) => random_int($a, $b);
        if ($casa['isca'] && self::premioSlot($rolos) === 0) {
            $rolos = self::girar($rand); // a isca: mais uma chance escondida
        }
        $vezes = self::premioSlot($rolos);
        $estoura = $aposta * $vezes > $casa['premio_max'];
        $aperta = $vezes > 0 && $casa['lucro'] > 0 && $rand(1, 1000) <= 350 * $casa['lucro'];
        $enfeite = $vezes === 0 && $rand(1, 100) <= 30;
        if ($estoura || $aperta || $enfeite) {
            $altos = ['vassago', 'vassago', 'kuro', 'kuro', 'caveira'];
            $s = $altos[$rand(0, count($altos) - 1)];
            $outros = array_values(array_diff(array_keys(self::SIMBOLOS), [$s]));
            $x = $outros[$rand(0, count($outros) - 1)];
            return [$rand(0, 1) ? [$s, $x, $s] : [$x, $s, $s], true]; // por um triz (não paga nada)
        }

        return [$rolos, false];
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
