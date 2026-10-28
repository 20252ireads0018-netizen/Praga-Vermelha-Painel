<?php

namespace App\Jogos;

/**
 * Gladio: duelo de cartas criadas pelo mestre.
 *
 * - A vida de cada jogador é o Dinheiro apostado (amistoso, sem aposta: 30 de vida).
 * - Lenda: criatura com custo, ataque e vida. Fica na mesa (até 6) e, a partir do turno seguinte,
 *   ataca uma Lenda inimiga ou o jogador inimigo. Num combate entre Lendas as duas causam dano.
 * - Feitiço: efeito de uso único (dano, dano em todas as Lendas inimigas, cura, comprar cartas,
 *   fortalecer uma Lenda sua ou ganhar energia).
 * - Energia: começa em 1 e o máximo sobe 1 a cada turno do jogador (até 10).
 * - Cada um começa com 3 cartas (quem joga depois, 4) e compra 1 no início do turno. Sem cartas
 *   no baralho, leva dano de fadiga (1, 2, 3…). Mão cheia (10): a carta comprada é perdida.
 * - Quem zerar a vida do outro vence e leva a aposta.
 *
 * Estado: { lados: { a: {...}, b: {...} }, vez, turno, cartas (catálogo da partida), seq, log, fim }
 * Carta na mão/baralho: { i: id da instância, c: id da carta }. Lenda na mesa: { i, c, atk, vida, max, pode }.
 */
class Gladio
{
    public const TIPOS = ['lenda', 'feitico'];
    public const EFEITOS = ['dano', 'dano_todos', 'cura', 'comprar', 'fortalecer', 'energia'];
    public const BARALHO = 20;
    public const MAO_MAX = 10;
    public const MESA_MAX = 6;
    public const ENERGIA_MAX = 10;
    public const VIDA_AMISTOSO = 30;

    /** Quais efeitos precisam de alvo e de que tipo. */
    public const ALVOS = [
        'dano'       => 'inimigo',  // Lenda inimiga ou o jogador inimigo
        'cura'       => 'aliado',   // Lenda sua ou você mesmo
        'fortalecer' => 'lenda',    // Lenda sua
    ];

    /**
     * Monta a partida. $cartas: catálogo [id => carta]. $embaralhar recebe e devolve um array
     * (nos testes dá para passar uma função sem sorte).
     */
    public static function iniciar(array $cartas, int $aposta, ?callable $embaralhar = null): array
    {
        if (!$cartas) {
            throw new \InvalidArgumentException('O mestre ainda não criou nenhuma carta de Gladio.');
        }
        $embaralhar ??= function (array $a): array {
            shuffle($a);

            return $a;
        };
        $vida = $aposta > 0 ? $aposta : self::VIDA_AMISTOSO;
        $e = ['lados' => [], 'vez' => 'a', 'turno' => 1, 'cartas' => $cartas, 'seq' => 0, 'log' => [], 'fim' => null];

        foreach (['a', 'b'] as $lado) {
            // baralho de 20: as cartas do mestre embaralhadas e repetidas até completar
            $ids = [];
            while (count($ids) < self::BARALHO) {
                foreach ($embaralhar(array_keys($cartas)) as $id) {
                    if (count($ids) < self::BARALHO) {
                        $ids[] = (string) $id;
                    }
                }
            }
            $baralho = [];
            foreach ($ids as $id) {
                $baralho[] = ['i' => ++$e['seq'], 'c' => $id];
            }
            $e['lados'][$lado] = [
                'vida' => $vida, 'max' => $vida, 'energia' => 0, 'energiaMax' => 0,
                'mao' => [], 'baralho' => $baralho, 'mesa' => [], 'fadiga' => 0,
            ];
            for ($k = 0; $k < ($lado === 'a' ? 3 : 4); $k++) {
                $e = self::comprar($e, $lado);
            }
        }
        $e['lados']['a']['energiaMax'] = 1;
        $e['lados']['a']['energia'] = 1;
        $e['log'] = [];
        self::log($e, 'A partida começou.');

        return $e;
    }

    public static function outro(string $lado): string
    {
        return $lado === 'a' ? 'b' : 'a';
    }

    private static function log(array &$e, string $t): void
    {
        $e['log'][] = $t;
        $e['log'] = array_slice($e['log'], -30);
    }

    private static function nome(array $e, string $c): string
    {
        return (string) ($e['cartas'][$c]['nome'] ?? 'Carta');
    }

    private static function comprar(array $e, string $lado): array
    {
        $l = &$e['lados'][$lado];
        if (!$l['baralho']) {
            $l['fadiga']++;
            $l['vida'] -= $l['fadiga'];
            self::log($e, "Sem cartas: fadiga de {$l['fadiga']}.");

            return $e;
        }
        $carta = array_shift($l['baralho']);
        if (count($l['mao']) >= self::MAO_MAX) {
            self::log($e, 'Mão cheia: ' . self::nome($e, $carta['c']) . ' foi descartada.');

            return $e;
        }
        $l['mao'][] = $carta;

        return $e;
    }

    private static function idxMao(array $l, int $i): int
    {
        foreach ($l['mao'] as $k => $c) {
            if ((int) $c['i'] === $i) {
                return $k;
            }
        }

        return -1;
    }

    private static function idxMesa(array $l, int $i): int
    {
        foreach ($l['mesa'] as $k => $c) {
            if ((int) $c['i'] === $i) {
                return $k;
            }
        }

        return -1;
    }

    /** Tira da mesa as Lendas sem vida e vê se alguém perdeu. */
    private static function conferir(array $e, string $quem): array
    {
        foreach (['a', 'b'] as $lado) {
            $mortas = array_filter($e['lados'][$lado]['mesa'], fn ($m) => $m['vida'] <= 0);
            foreach ($mortas as $m) {
                self::log($e, self::nome($e, $m['c']) . ' caiu.');
            }
            $e['lados'][$lado]['mesa'] = array_values(array_filter($e['lados'][$lado]['mesa'], fn ($m) => $m['vida'] > 0));
        }
        $aMorto = $e['lados']['a']['vida'] <= 0;
        $bMorto = $e['lados']['b']['vida'] <= 0;
        if ($aMorto || $bMorto) {
            // os dois zeraram na mesma ação: vence quem agiu
            $vencedor = $aMorto && $bMorto ? $quem : ($aMorto ? 'b' : 'a');
            $e['fim'] = ['vencedor' => $vencedor, 'motivo' => 'vida'];
        }

        return $e;
    }

    private static function exigirVez(array $e, string $lado): void
    {
        if ($e['fim']) {
            throw new \InvalidArgumentException('A partida já acabou.');
        }
        if ($e['vez'] !== $lado) {
            throw new \InvalidArgumentException('Não é a sua vez.');
        }
    }

    /**
     * Joga uma carta da mão. $alvo: 'eu' | 'inimigo' | id de instância de uma Lenda na mesa.
     */
    public static function jogar(array $e, string $lado, int $inst, mixed $alvo = null): array
    {
        self::exigirVez($e, $lado);
        $op = self::outro($lado);
        $l = &$e['lados'][$lado];
        $k = self::idxMao($l, $inst);
        if ($k < 0) {
            throw new \InvalidArgumentException('Essa carta não está na sua mão.');
        }
        $carta = $e['cartas'][$l['mao'][$k]['c']] ?? null;
        if (!$carta) {
            throw new \InvalidArgumentException('Carta desconhecida.');
        }
        $custo = (int) $carta['custo'];
        if ($custo > $l['energia']) {
            throw new \InvalidArgumentException('Energia insuficiente.');
        }
        $nome = $carta['nome'];

        if ($carta['tipo'] === 'lenda') {
            if (count($l['mesa']) >= self::MESA_MAX) {
                throw new \InvalidArgumentException('Sua mesa já tem ' . self::MESA_MAX . ' Lendas.');
            }
            $l['mesa'][] = ['i' => $l['mao'][$k]['i'], 'c' => $l['mao'][$k]['c'],
                            'atk' => (int) $carta['ataque'], 'vida' => (int) $carta['vida'], 'max' => (int) $carta['vida'], 'pode' => false];
            array_splice($l['mao'], $k, 1);
            $l['energia'] -= $custo;
            unset($l);
            self::log($e, "Lenda $nome entrou na mesa.");

            return $e;
        }

        // feitiço
        $efeito = $carta['efeito'];
        $valor = max(0, (int) $carta['valor']);
        $precisa = self::ALVOS[$efeito] ?? null;
        $alvoLenda = null; // [lado, índice]
        $alvoHeroi = null; // lado
        if ($precisa) {
            if ($alvo === 'inimigo' && $precisa === 'inimigo') {
                $alvoHeroi = $op;
            } elseif ($alvo === 'eu' && $precisa === 'aliado') {
                $alvoHeroi = $lado;
            } elseif (is_numeric($alvo)) {
                $dono = $precisa === 'inimigo' ? $op : $lado;
                $ki = self::idxMesa($e['lados'][$dono], (int) $alvo);
                if ($ki >= 0) {
                    $alvoLenda = [$dono, $ki];
                }
            }
            if (!$alvoLenda && !$alvoHeroi) {
                throw new \InvalidArgumentException(match ($precisa) {
                    'inimigo' => 'Escolha uma Lenda inimiga ou o adversário.',
                    'aliado'  => 'Escolha uma Lenda sua ou você mesmo.',
                    default   => 'Escolha uma Lenda sua.',
                });
            }
        }

        array_splice($l['mao'], $k, 1);
        $l['energia'] -= $custo;
        unset($l);

        switch ($efeito) {
            case 'dano':
                if ($alvoLenda) {
                    $e['lados'][$alvoLenda[0]]['mesa'][$alvoLenda[1]]['vida'] -= $valor;
                    self::log($e, "$nome causou $valor de dano em " . self::nome($e, $e['lados'][$alvoLenda[0]]['mesa'][$alvoLenda[1]]['c']) . '.');
                } else {
                    $e['lados'][$alvoHeroi]['vida'] -= $valor;
                    self::log($e, "$nome causou $valor de dano no adversário.");
                }
                break;
            case 'dano_todos':
                foreach ($e['lados'][$op]['mesa'] as $i => $m) {
                    $e['lados'][$op]['mesa'][$i]['vida'] -= $valor;
                }
                self::log($e, "$nome causou $valor de dano em todas as Lendas inimigas.");
                break;
            case 'cura':
                if ($alvoLenda) {
                    $m = &$e['lados'][$alvoLenda[0]]['mesa'][$alvoLenda[1]];
                    $m['vida'] = min($m['max'], $m['vida'] + $valor);
                    unset($m);
                } else {
                    $h = &$e['lados'][$alvoHeroi];
                    $h['vida'] = min($h['max'], $h['vida'] + $valor);
                    unset($h);
                }
                self::log($e, "$nome curou $valor.");
                break;
            case 'comprar':
                for ($n = 0; $n < $valor; $n++) {
                    $e = self::comprar($e, $lado);
                }
                self::log($e, "$nome: comprou $valor carta(s).");
                break;
            case 'fortalecer':
                $e['lados'][$lado]['mesa'][$alvoLenda[1]]['atk'] += $valor;
                self::log($e, "$nome deu +$valor de ataque a " . self::nome($e, $e['lados'][$lado]['mesa'][$alvoLenda[1]]['c']) . '.');
                break;
            case 'energia':
                $e['lados'][$lado]['energia'] += $valor;
                self::log($e, "$nome deu +$valor de energia.");
                break;
        }

        return self::conferir($e, $lado);
    }

    /** Uma Lenda sua ataca. $alvo: 'inimigo' (o jogador) ou id de instância de uma Lenda inimiga. */
    public static function atacar(array $e, string $lado, int $inst, mixed $alvo): array
    {
        self::exigirVez($e, $lado);
        $op = self::outro($lado);
        $k = self::idxMesa($e['lados'][$lado], $inst);
        if ($k < 0) {
            throw new \InvalidArgumentException('Essa Lenda não está na sua mesa.');
        }
        $a = $e['lados'][$lado]['mesa'][$k];
        if (!$a['pode']) {
            throw new \InvalidArgumentException('Essa Lenda ainda não pode atacar neste turno.');
        }
        if ($a['atk'] <= 0) {
            throw new \InvalidArgumentException('Essa Lenda não tem ataque.');
        }
        $e['lados'][$lado]['mesa'][$k]['pode'] = false;

        if ($alvo === 'inimigo') {
            $e['lados'][$op]['vida'] -= $a['atk'];
            self::log($e, self::nome($e, $a['c']) . " atacou o adversário: {$a['atk']} de dano.");
        } else {
            $ki = is_numeric($alvo) ? self::idxMesa($e['lados'][$op], (int) $alvo) : -1;
            if ($ki < 0) {
                throw new \InvalidArgumentException('Escolha uma Lenda inimiga ou o adversário.');
            }
            $d = $e['lados'][$op]['mesa'][$ki];
            $e['lados'][$op]['mesa'][$ki]['vida'] -= $a['atk'];
            $e['lados'][$lado]['mesa'][$k]['vida'] -= $d['atk'];
            self::log($e, self::nome($e, $a['c']) . ' atacou ' . self::nome($e, $d['c']) . '.');
        }

        return self::conferir($e, $lado);
    }

    /** Termina o turno: o outro ganha +1 de energia máxima, recarrega, compra 1 e as Lendas dele podem atacar. */
    public static function passar(array $e, string $lado): array
    {
        self::exigirVez($e, $lado);
        $op = self::outro($lado);
        $e['vez'] = $op;
        $e['turno']++;
        $l = &$e['lados'][$op];
        $l['energiaMax'] = min(self::ENERGIA_MAX, $l['energiaMax'] + 1);
        $l['energia'] = $l['energiaMax'];
        foreach ($l['mesa'] as $i => $m) {
            $l['mesa'][$i]['pode'] = true;
        }
        unset($l);
        $e = self::comprar($e, $op);

        return self::conferir($e, $lado);
    }

    public static function desistir(array $e, string $lado): array
    {
        if ($e['fim']) {
            throw new \InvalidArgumentException('A partida já acabou.');
        }
        $e['fim'] = ['vencedor' => self::outro($lado), 'motivo' => 'desistencia'];
        self::log($e, 'Um jogador desistiu.');

        return $e;
    }

    /** O que um lado pode ver: a mão do outro e os baralhos viram só a quantidade. */
    public static function visao(array $e, ?string $eu): array
    {
        foreach (['a', 'b'] as $lado) {
            $e['lados'][$lado]['baralho'] = count($e['lados'][$lado]['baralho']);
            if ($lado !== $eu) {
                $e['lados'][$lado]['mao'] = count($e['lados'][$lado]['mao']);
            }
        }

        return $e;
    }
}
