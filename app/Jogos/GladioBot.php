<?php

namespace App\Jogos;

/**
 * Bot do Gladio: joga um lado da partida usando só as ações públicas do motor (as mesmas dos jogadores), então
 * obedece a todas as regras: o que o motor recusa, o bot simplesmente não faz. Na config da partida ele aparece como
 * o jogador de id ID (0, que nenhuma conta usa) e config.bot guarda o nome e o deck sorteado.
 *
 * Estratégia simples e gananciosa: mantém a mão inicial; no turno invoca o comandante e joga as cartas mais caras que
 * couberem na energia, depois ataca com todas as Lendas prontas o adversário com menos vida; na defesa bloqueia quando
 * a Lenda sobrevive ou derruba o atacante (ou com qualquer uma, se o ataque for letal); nas escolhas mira as Lendas
 * mais fortes do adversário para efeitos ruins e as próprias para efeitos bons.
 */
class GladioBot
{
    public const ID = 0;
    private const LIMITE = 300; // ações por chamada: nunca fica preso num laço

    /** Ações que machucam o alvo (o bot as aponta para o adversário). */
    private const RUINS = ['dano', 'destru', 'elimin', 'matar', 'exil', 'banir', 'remov', 'devolv', 'virar', 'preso', 'prender',
                           'congel', 'paralis', 'reduz', 'tirar', 'roubar', 'controlar', 'silenc', 'enfraq', 'veneno', 'intox',
                           'shot', 'perde', 'descart', 'atordo', 'desafiar'];

    /** Joga pelo $lado enquanto a mesa esperar por ele. */
    public static function agir(array $e, string $lado): array
    {
        // o mulligan é de todos ao mesmo tempo (a mesa só lista um por vez): o bot mantém a mão já
        if (($e['fase'] ?? '') === 'mulligan' && empty($e['mull'][$lado]['ok']) && empty($e['lados'][$lado]['fora'])) {
            $e = Gladio::manter($e, $lado, []);
        }
        for ($n = 0; $n < self::LIMITE; $n++) {
            if (!empty($e['fim']) || !empty($e['pausa']) || !empty($e['lados'][$lado]['fora']) || Gladio::esperando($e) !== $lado) {
                break;
            }
            $antes = $e;
            try {
                $e = match (true) {
                    $e['fase'] === 'mulligan' => Gladio::manter($e, $lado, []),
                    !empty($e['escolha'])     => self::escolha($e, $lado),
                    $e['fase'] === 'defesa'   => self::defesa($e, $lado),
                    $e['fase'] === 'principal' && $e['vez'] === $lado => self::turno($e, $lado),
                    default                   => $e,
                };
            } catch (\InvalidArgumentException $x) {
                $e = self::destravar($e, $lado);
            }
            $e = Gladio::conferirVitorias($e);
            if ($e === $antes) { // nada mudou: deixa a mesa como está (o relógio da vez resolve)
                break;
            }
        }

        return $e;
    }

    /** Último recurso quando a jogada pensada falhou: pula a escolha, confirma a defesa ou passa o turno. */
    private static function destravar(array $e, string $lado): array
    {
        try {
            if (!empty($e['escolha'])) {
                $es = $e['escolha'];

                return Gladio::escolher($e, $lado, $es['obrig'] ? ($es['opcoes'][0] ?? null) : null);
            }
            if ($e['fase'] === 'defesa') {
                return Gladio::defender($e, $lado);
            }
            if ($e['fase'] === 'principal' && $e['vez'] === $lado) {
                return Gladio::passar($e, $lado, true);
            }
        } catch (\InvalidArgumentException $x) {
        }

        return $e;
    }

    /* ------------------------------------------------------------------ */
    /*  Turno                                                              */
    /* ------------------------------------------------------------------ */

    /** Uma jogada do turno: comandante, uma carta da mão, o ataque ou passar. */
    private static function turno(array $e, string $lado): array
    {
        if (($cmd = self::tentar(fn () => Gladio::comandar($e, $lado))) !== null) {
            return $cmd;
        }
        $L = $e['lados'][$lado];
        $mao = $L['mao'];
        usort($mao, fn ($a, $b) => self::custo($e, $b['c']) <=> self::custo($e, $a['c'])); // as mais caras primeiro
        foreach ($mao as $c) {
            if (self::custo($e, $c['c']) > $L['energia']) {
                continue;
            }
            foreach (self::alvosParaJogar($e, $lado, $c['c']) as $alvo) {
                if (($novo = self::tentar(fn () => Gladio::jogar($e, $lado, (int) $c['i'], $alvo))) !== null) {
                    return $novo;
                }
            }
        }
        if (empty($e['atacou']) && ($novo = self::atacar($e, $lado)) !== null) {
            return $novo;
        }

        return Gladio::passar($e, $lado, true);
    }

    private static function tentar(callable $f): ?array
    {
        try {
            return $f();
        } catch (\InvalidArgumentException $x) {
            return null;
        }
    }

    private static function custo(array $e, string $id): int
    {
        return (int) ($e['cartas'][$id]['custo'] ?? 0);
    }

    /** Alvos a tentar ao jogar a carta, na ordem de preferência (null: sem alvo). */
    private static function alvosParaJogar(array $e, string $lado, string $id): array
    {
        $carta = $e['cartas'][$id] ?? [];
        $efeito = trim((string) ($carta['descricao'] ?? '')) === '' ? ($carta['efeito'] ?? null) : null;
        $inimigo = self::alvoPreferido($e, $lado);
        $minhas = self::lendasPorForca($e, $lado);
        $delas = $inimigo ? self::lendasPorForca($e, $inimigo) : [];
        $deles = $inimigo ? ['j:' . $inimigo] : [];

        return match (Gladio::ALVOS[$efeito] ?? null) {
            'inimigo'      => array_merge(array_map('strval', $delas), $deles),
            'aliado'       => array_merge(['eu'], array_map('strval', $minhas)),
            'lenda'        => array_map('strval', $minhas),
            default        => [null],
        };
    }

    /** Adversário com menos vida (em equipes, só os de outra equipe). */
    private static function alvoPreferido(array $e, string $lado): ?string
    {
        $ini = array_values(array_filter(Gladio::vivos($e), fn ($l) => $l !== $lado
            && !(isset($e['equipes'][$l], $e['equipes'][$lado]) && $e['equipes'][$l] === $e['equipes'][$lado])));
        usort($ini, fn ($a, $b) => $e['lados'][$a]['vida'] <=> $e['lados'][$b]['vida']);

        return $ini[0] ?? null;
    }

    /** Instâncias das Lendas do lado, da mais forte para a mais fraca. */
    private static function lendasPorForca(array $e, string $lado): array
    {
        $mesa = $e['lados'][$lado]['mesa'] ?? [];
        usort($mesa, fn ($a, $b) => ($b['atk'] + $b['vida']) <=> ($a['atk'] + $a['vida']));

        return array_map(fn ($m) => (int) $m['i'], $mesa);
    }

    /** Ataca o adversário com menos vida com todas as Lendas que o motor aceitar. */
    private static function atacar(array $e, string $lado): ?array
    {
        $alvo = self::alvoPreferido($e, $lado);
        if ($alvo === null) {
            return null;
        }
        $prontas = array_values(array_filter($e['lados'][$lado]['mesa'], fn ($m) => $m['pode'] && empty($m['virada']) && $m['atk'] > 0));
        $validas = [];
        foreach ($prontas as $m) { // cada uma sozinha diz se pode atacar (Defensor, presa, Redoma…)
            if (self::tentar(fn () => Gladio::atacar($e, $lado, [(int) $m['i']], $alvo)) !== null) {
                $validas[] = (int) $m['i'];
            }
        }
        if (!$validas) {
            return null;
        }

        return self::tentar(fn () => Gladio::atacar($e, $lado, $validas, $alvo));
    }

    /* ------------------------------------------------------------------ */
    /*  Defesa                                                             */
    /* ------------------------------------------------------------------ */

    private static function defesa(array $e, string $lado): array
    {
        $at = $e['vez'];
        $atacantes = array_values(array_filter($e['lados'][$at]['mesa'], fn ($m) => in_array((int) $m['i'], $e['ataque']['atacantes'], true)));
        usort($atacantes, fn ($a, $b) => $b['atk'] <=> $a['atk']);
        $letal = array_sum(array_column($atacantes, 'atk')) >= $e['lados'][$lado]['vida'];
        $livres = array_values(array_filter($e['lados'][$lado]['mesa'], fn ($m) => empty($m['virada'])));
        $usadas = [];
        foreach ($atacantes as $a) {
            // a melhor: sobrevive e derruba; depois só sobrevive; depois troca; se for letal, qualquer uma
            $opcoes = [];
            foreach ($livres as $b) {
                if (in_array((int) $b['i'], $usadas, true)) {
                    continue;
                }
                $vive = $b['vida'] > $a['atk'];
                $derruba = $b['atk'] >= $a['vida'];
                $nota = ($vive ? 2 : 0) + ($derruba ? 1 : 0);
                if ($nota > 0 || $letal) {
                    $opcoes[] = [$nota, $b];
                }
            }
            usort($opcoes, fn ($x, $y) => $y[0] <=> $x[0] ?: ($x[1]['atk'] + $x[1]['vida']) <=> ($y[1]['atk'] + $y[1]['vida']));
            foreach ($opcoes as [, $b]) {
                if (($novo = self::tentar(fn () => Gladio::bloquear($e, $lado, (int) $b['i'], (int) $a['i']))) !== null) {
                    $e = $novo;
                    $usadas[] = (int) $b['i'];
                    break;
                }
            }
        }

        return Gladio::defender($e, $lado);
    }

    /* ------------------------------------------------------------------ */
    /*  Escolhas pedidas por efeitos                                       */
    /* ------------------------------------------------------------------ */

    private static function escolha(array $e, string $lado): array
    {
        $es = $e['escolha'];
        $opcoes = $es['opcoes'] ?? [];
        if ($es['tipo'] === 'resposta' || !$opcoes) { // não responde às jogadas dos outros
            return Gladio::escolher($e, $lado, $es['obrig'] ? ($opcoes[0] ?? null) : null);
        }
        if (in_array($es['tipo'], ['confirmar', 'palavra', 'opcao'], true)) {
            return Gladio::escolher($e, $lado, $opcoes[0]);
        }
        $acao = mb_strtolower((string) ($es['passo']['a'] ?? '') . ' ' . ($es['texto'] ?? ''));
        $ruim = (bool) array_filter(self::RUINS, fn ($r) => str_contains($acao, $r));
        $proprio = in_array($es['tipo'], ['mao', 'baralho', 'cemiterio'], true) || str_contains($acao, 'sacrific');

        if ($proprio) { // das próprias cartas: descarta/sacrifica a mais fraca; busca a mais forte
            $peso = fn ($v) => self::pesoCarta($e, $v);
            usort($opcoes, fn ($a, $b) => $peso($a) <=> $peso($b));
            $perde = str_contains($acao, 'descart') || str_contains($acao, 'sacrific') || str_contains($acao, 'fundo');

            return Gladio::escolher($e, $lado, $perde ? $opcoes[0] : end($opcoes));
        }
        $meu = fn ($v) => self::dono($e, $v) === $lado;
        $alvos = array_values(array_filter($opcoes, fn ($v) => $ruim ? !$meu($v) : $meu($v)));
        if (!$alvos) {
            if (!$es['obrig']) {
                return Gladio::escolher($e, $lado, null); // não vale a pena: pula
            }
            $alvos = $opcoes;
        }
        usort($alvos, fn ($a, $b) => self::forca($e, $b) <=> self::forca($e, $a)); // a mais forte primeiro

        return Gladio::escolher($e, $lado, $alvos[0]);
    }

    /** Lado dono de uma opção de escolha: 'j:<lado>' ou instância de carta. */
    private static function dono(array $e, mixed $v): ?string
    {
        if (is_string($v) && str_starts_with($v, 'j:')) {
            return substr($v, 2);
        }
        foreach ($e['lados'] as $l => $L) {
            foreach (['mesa', 'feiticos', 'mao', 'cemiterio', 'baralho', 'comando'] as $zona) {
                foreach ($L[$zona] ?? [] as $c) {
                    if ((int) ($c['i'] ?? -1) === (int) $v) {
                        return $l;
                    }
                }
            }
        }

        return null;
    }

    /** Força de uma opção: ataque + vida da Lenda, custo da carta ou a vida do jogador (alvo mais valioso primeiro). */
    private static function forca(array $e, mixed $v): int
    {
        if (is_string($v) && str_starts_with($v, 'j:')) {
            return 1000 - (int) ($e['lados'][substr($v, 2)]['vida'] ?? 0); // jogador com menos vida primeiro
        }
        foreach ($e['lados'] as $L) {
            foreach ($L['mesa'] as $m) {
                if ((int) $m['i'] === (int) $v) {
                    return (int) $m['atk'] + (int) $m['vida'];
                }
            }
        }

        return self::pesoCarta($e, $v);
    }

    private static function pesoCarta(array $e, mixed $v): int
    {
        foreach ($e['lados'] as $L) {
            foreach (['mao', 'cemiterio', 'baralho', 'feiticos', 'comando', 'mesa'] as $zona) {
                foreach ($L[$zona] ?? [] as $c) {
                    if ((int) ($c['i'] ?? -1) === (int) $v) {
                        return isset($c['c']) ? self::custo($e, (string) $c['c']) : 0;
                    }
                }
            }
        }

        return 0;
    }
}
