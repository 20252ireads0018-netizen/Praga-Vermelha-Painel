<?php

namespace App\Support;

/**
 * Regras de um time skip montado pelo mestre (formato em config/timeskip.php) e as contas das escolhas de um jogador.
 * public/js/timeskip-calc.js faz as mesmas contas na tela; aqui elas valem para gravar.
 */
class TimeSkipCalc
{
    public const TIPOS = ['', 'escolhas', 'salario', 'salarios', 'dinheiro'];

    /** Limpa as regras vindas do editor: só as chaves conhecidas, com tamanhos e números limitados. */
    public static function limparConfig(array $c): array
    {
        $ramos = [];
        foreach (array_slice((array) ($c['ramos'] ?? []), 0, 12) as $r) {
            $id = self::id($r['id'] ?? '', $r['nome'] ?? '');
            if ($id === '' || isset($ramos[$id])) {
                continue;
            }
            $ramos[$id] = ['id' => $id, 'nome' => self::texto($r['nome'] ?? $id, 60), 'descricao' => self::texto($r['descricao'] ?? '', 1500)];
        }
        $ramoOk = fn ($v) => isset($ramos[(string) $v]) ? (string) $v : '';

        $vistos = [];
        $itens = function (array $lista, bool $prop) use ($ramoOk, &$vistos) {
            $saida = [];
            foreach (array_slice($lista, 0, 120) as $a) {
                if (!is_array($a)) {
                    continue;
                }
                $id = self::id($a['id'] ?? '', $a['nome'] ?? '');
                if ($id === '') {
                    continue;
                }
                for ($base = $id, $k = 2; isset($vistos[$id]); $k++) {
                    $id = $base . '_' . $k; // dois itens com o mesmo nome: o segundo ganha outro id
                }
                $vistos[$id] = true;
                $item = [
                    'id'        => $id,
                    'nome'      => self::texto($a['nome'] ?? $id, 80),
                    'descricao' => self::texto($a['descricao'] ?? '', 2000),
                    'ramo'      => $ramoOk($a['ramo'] ?? ''),
                    'max'       => self::int($a['max'] ?? 0, 0, 99),
                    'requisito' => self::texto($a['requisito'] ?? '', 200),
                    'efeitos'   => self::efeitos($a['efeitos'] ?? []),
                ];
                if ($prop) {
                    $item['preco'] = self::int($a['preco'] ?? 0, 0, 1000000000);
                } else {
                    $item['grupo'] = self::texto($a['grupo'] ?? '', 60);
                    $item['custo'] = self::int($a['custo'] ?? 1, 1, 99);
                    $item['fator'] = self::int($a['fator'] ?? 0, 0, 100);
                }
                $saida[] = $item;
            }

            return $saida;
        };

        return [
            'descricao'     => self::texto($c['descricao'] ?? '', 3000),
            'escolhas'      => self::int($c['escolhas'] ?? 20, 0, 999),
            'meses'         => self::int($c['meses'] ?? 0, 0, 1200),
            'fator'         => self::int($c['fator'] ?? 100, 0, 100),
            'parametros'    => self::texto($c['parametros'] ?? '', 4000),
            'bonus'         => self::texto($c['bonus'] ?? '', 4000),
            'bonus_efeitos' => self::efeitos($c['bonus_efeitos'] ?? []),
            'ramos'         => array_values($ramos),
            'atividades'    => $itens((array) ($c['atividades'] ?? []), false),
            'propriedades'  => $itens((array) ($c['propriedades'] ?? []), true),
        ];
    }

    /** Limpa as escolhas de um jogador contra as regras (ids desconhecidos somem). */
    public static function limparResposta(array $r, array $cfg): array
    {
        $ramos = array_column($cfg['ramos'] ?? [], 'id');
        $contagem = function ($v, array $lista) {
            $ids = array_column($lista, 'id');
            $saida = [];
            foreach ((array) $v as $id => $n) {
                $n = self::int($n, 0, 999);
                if ($n > 0 && in_array((string) $id, $ids, true)) {
                    $saida[(string) $id] = $n;
                }
            }

            return $saida;
        };

        return [
            'ramo'             => in_array((string) ($r['ramo'] ?? ''), $ramos, true) ? (string) $r['ramo'] : '',
            'atividades'       => $contagem($r['atividades'] ?? [], $cfg['atividades'] ?? []),
            'propriedades'     => $contagem($r['propriedades'] ?? [], $cfg['propriedades'] ?? []),
            'salario'          => self::int($r['salario'] ?? 0, 0, 100000000),
            'dinheiro_inicial' => self::int($r['dinheiro_inicial'] ?? 0, 0, 1000000000),
            'notas'            => self::texto($r['notas'] ?? '', 5000),
        ];
    }

    /** As contas: escolhas, dinheiro, ganhos somados e o que está fora das regras. */
    public static function calcular(array $cfg, array $r): array
    {
        $ramo = (string) ($r['ramo'] ?? '');
        $nomeRamo = [];
        foreach ($cfg['ramos'] ?? [] as $x) {
            $nomeRamo[$x['id']] = $x['nome'];
        }
        $totais = [];
        $extra = ['escolhas' => 0.0, 'salario' => 0.0, 'salarios' => 0.0, 'dinheiro' => 0.0];
        $erros = [];
        $aplicar = function (array $efeitos, int $n) use (&$totais, &$extra) {
            foreach ($efeitos as $ef) {
                $ganho = intdiv($n, max(1, (int) $ef['a_cada'])) * (float) $ef['valor'];
                if ((float) $ef['limite'] > 0) {
                    $ganho = min($ganho, (float) $ef['limite']);
                }
                if ($ganho == 0) {
                    continue;
                }
                if ($ef['tipo'] !== '') {
                    $extra[$ef['tipo']] += $ganho;
                } else {
                    $totais[$ef['rotulo']] = ($totais[$ef['rotulo']] ?? 0) + $ganho;
                }
            }
        };
        $aplicar($cfg['bonus_efeitos'] ?? [], 1);

        $usadas = 0;
        $fator = (int) ($cfg['fator'] ?? 100);
        foreach ($cfg['atividades'] ?? [] as $a) {
            $n = (int) ($r['atividades'][$a['id']] ?? 0);
            if ($n <= 0) {
                continue;
            }
            if ($a['max'] > 0 && $n > $a['max']) {
                $erros[] = "{$a['nome']}: no máximo {$a['max']} vez(es).";
            }
            if ($a['ramo'] !== '' && $a['ramo'] !== $ramo) {
                $erros[] = "{$a['nome']} exige " . ($nomeRamo[$a['ramo']] ?? $a['ramo']) . '.';
            }
            $usadas += $n * (int) $a['custo'];
            if ($a['fator'] > 0) {
                $fator = max($fator, (int) $a['fator']);
            }
            $aplicar($a['efeitos'], $n);
        }

        $gasto = 0;
        foreach ($cfg['propriedades'] ?? [] as $p) {
            $n = (int) ($r['propriedades'][$p['id']] ?? 0);
            if ($n <= 0) {
                continue;
            }
            if ($p['max'] > 0 && $n > $p['max']) {
                $erros[] = "{$p['nome']}: no máximo {$p['max']} marco(s).";
            }
            if ($p['ramo'] !== '' && $p['ramo'] !== $ramo) {
                $erros[] = "{$p['nome']} exige " . ($nomeRamo[$p['ramo']] ?? $p['ramo']) . '.';
            }
            $gasto += $n * (int) $p['preco'];
            $aplicar($p['efeitos'], $n);
        }

        $escolhas = (int) ($cfg['escolhas'] ?? 0) + (int) floor($extra['escolhas']);
        if ($usadas > $escolhas) {
            $erros[] = "Escolhas demais: {$usadas} de {$escolhas}.";
        }
        $salarioMensal = (int) ($r['salario'] ?? 0) + $extra['salario'];
        $renda = (int) round($salarioMensal * (int) ($cfg['meses'] ?? 0) * $fator / 100 + $salarioMensal * $extra['salarios'] + $extra['dinheiro']);
        $saldo = (int) ($r['dinheiro_inicial'] ?? 0) + $renda - $gasto;
        if ($saldo < 0) {
            $erros[] = 'Dinheiro insuficiente para as propriedades.';
        }

        return [
            'escolhas'       => $escolhas,
            'usadas'         => $usadas,
            'fator'          => $fator,
            'salario_mensal' => round($salarioMensal, 2),
            'salarios_extra' => round($extra['salarios'], 2),
            'renda'          => $renda,
            'gasto'          => $gasto,
            'saldo'          => $saldo,
            'totais'         => array_map(fn ($v) => round($v, 2), $totais),
            'erros'          => $erros,
        ];
    }

    private static function efeitos($lista): array
    {
        $saida = [];
        foreach (array_slice((array) $lista, 0, 12) as $ef) {
            if (!is_array($ef)) {
                continue;
            }
            $tipo = (string) ($ef['tipo'] ?? '');
            $tipo = in_array($tipo, self::TIPOS, true) ? $tipo : '';
            $rotulo = self::texto($ef['rotulo'] ?? '', 80);
            $valor = round(max(-1000000000, min(1000000000, (float) ($ef['valor'] ?? 0))), 2);
            if (($rotulo === '' && $tipo === '') || $valor == 0) {
                continue;
            }
            $saida[] = [
                'rotulo' => $rotulo !== '' ? $rotulo : $tipo,
                'valor'  => $valor,
                'a_cada' => self::int($ef['a_cada'] ?? 1, 1, 99),
                'limite' => round(max(0, min(1000000000, (float) ($ef['limite'] ?? 0))), 2),
                'tipo'   => $tipo,
            ];
        }

        return $saida;
    }

    private static function id($id, $nome): string
    {
        $base = trim((string) $id) !== '' ? (string) $id : (string) $nome;
        $s = strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', \Illuminate\Support\Str::ascii($base)));

        return substr(trim($s, '_'), 0, 40);
    }

    private static function texto($v, int $max): string
    {
        return mb_substr(trim(is_scalar($v) ? (string) $v : ''), 0, $max);
    }

    private static function int($v, int $min, int $max): int
    {
        return max($min, min($max, (int) (is_numeric($v) ? $v : 0)));
    }
}
