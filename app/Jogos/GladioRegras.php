<?php

namespace App\Jogos;

/**
 * Regras de Gladio que vêm dos roteiros das cartas (GladioRoteiro): subtipos, efeitos contínuos, dano com proteções,
 * regenerar e salvamentos, habilidades ativadas, respostas fora do turno (Vergonha de Dante, Imbuir), efeitos com
 * atraso, contadores e os passos novos (scry, ordenar, laço, "ou", Estripador, Azazyel…).
 *
 * Campos novos de uma Lenda na mesa: fl[] (efeitos contínuos de agora), est {atk, vida, pal} (o que eles somaram),
 * regen (vezes que ainda volta ao morrer), mk {contador: n}, st {silhueta, semdano}, ataques, tag, ficha (ficha de
 * uma carta: some ao sair), so (só os N primeiros efeitos da carta), estp (efeitos contínuos próprios da ficha),
 * ver (Maria), letra (Zequinha), mortas (Aguinha), por (Monolisco), combateu (Carneiro), usou {habilidade: turno}.
 * Lado: mk {estridio…}, mortes/mortes_ant, compras/compras_ant, pular (pula a próxima compra).
 * Estado: ev [eventos a disparar], atrasos [efeitos com hora marcada], pilha (resposta em andamento), retomar.
 */
trait GladioRegras
{
    /** Nomes de fichas que já dizem o subtipo (fichas não têm o campo). */
    private const SUB_FICHAS = ['hexomante' => 'hexomante', 'carnical' => 'kuro', 'infestador' => 'kuro', 'estripador' => 'kuro',
                                'monolisco' => 'kuro', 'sol vermelho' => 'kuro', 'lacaio' => 'kuro'];
    /** Segundos para quem pode responder decidir (Vergonha de Dante, Imbuir). */
    public const TEMPO_RESPOSTA = 20;
    /** Sorteio da moeda (Dificuldade de interação); os testes trocam por uma função sem sorte. */
    public static $moeda = null;

    /* ================================================================== */
    /*  Subtipos                                                           */
    /* ================================================================== */

    /** "hexomante, aluno" => ['hexomante', 'aluno'] (sem acento; "forma hexomante" vira forma, "propriedade…" vira propriedade). */
    public static function lerSubtipos(?string $t, string $nome = ''): array
    {
        $subs = [];
        foreach (preg_split('/[,;\/]+/', GladioTexto::normalizar((string) $t)) as $s) {
            $s = trim($s);
            if ($s === '') {
                continue;
            }
            $subs[] = match (true) {
                str_starts_with($s, 'forma') => 'forma',
                str_starts_with($s, 'propriedade') => 'propriedade',
                str_starts_with($s, 'hexoman') => 'hexomante',
                str_starts_with($s, 'fisioman') => 'fisiomante',
                str_starts_with($s, 'aluno') => 'aluno',
                str_starts_with($s, 'kuro') => 'kuro',
                default => $s,
            };
        }
        // sem subtipo escrito: o nome diz o óbvio ("Propriedade: Duro", "Kuro das cores", "Hexomante do comando")
        if (!$subs) {
            $n = GladioTexto::normalizar($nome);
            if (str_starts_with($n, 'propriedade')) {
                $subs[] = 'propriedade';
            }
            if (str_contains($n, 'kuro')) {
                $subs[] = 'kuro';
            }
            if (str_contains($n, 'hexomante')) {
                $subs[] = 'hexomante';
            }
        }

        return array_values(array_unique($subs));
    }

    /** Subtipos de uma carta (id) ou de uma Lenda na mesa (com as fichas e o "todas as cartas são hexomantes"). */
    private static function subs(array $e, array|string|null $x): array
    {
        if (is_array($x)) {
            $s = $x['c'] !== null ? ($e['cartas'][$x['c']]['sub'] ?? []) : [];
            if ($x['c'] === null || !empty($x['ficha'])) {
                $n = GladioTexto::normalizar((string) ($x['nome'] ?? ''));
                foreach (self::SUB_FICHAS as $nome => $sub) {
                    if (str_contains($n, $nome)) {
                        $s[] = $sub;
                    }
                }
            }
        } else {
            $s = $x === null ? [] : ($e['cartas'][$x]['sub'] ?? []);
        }
        if (!empty($e['hexo_todos'])) {
            $s[] = 'hexomante';
        }

        return array_values(array_unique($s));
    }

    private static function temSub(array $e, array|string|null $x, array|string|null $sub): bool
    {
        return $sub === null || (bool) array_intersect((array) $sub, self::subs($e, $x));
    }

    /* ================================================================== */
    /*  Onde está cada coisa                                               */
    /* ================================================================== */

    /** Carta em jogo (Lenda na mesa ou feitiço no campo) pela instância: [lado, zona, índice] ou null. */
    private static function emJogo(array $e, ?int $i): ?array
    {
        if ($i === null) {
            return null;
        }
        foreach ($e['ordem'] as $lado) {
            foreach (['mesa', 'feiticos'] as $zona) {
                $k = self::idx($e['lados'][$lado][$zona], $i);
                if ($k >= 0) {
                    return [$lado, $zona, $k];
                }
            }
        }

        return null;
    }

    /** A carta (instância) que está resolvendo o efeito atual, se ainda estiver em jogo. */
    private static function fonteDe(array $e, array $ef): ?array
    {
        $o = self::emJogo($e, $ef['i'] ?? null);

        return $o ? $e['lados'][$o[0]][$o[1]][$o[2]] : null;
    }

    /** Efeitos (com gatilho) de uma carta em jogo: os da ficha, só os primeiros (Monolisco) ou os da carta. */
    private static function habsDe(array $e, array $x): array
    {
        if (isset($x['hab'])) {
            return $x['hab'];
        }
        $h = $x['c'] !== null ? ($e['cartas'][$x['c']]['hab'] ?? []) : [];

        return isset($x['so']) ? array_slice($h, 0, (int) $x['so']) : $h;
    }

    private static function estDe(array $e, array $x): array
    {
        if (isset($x['estp'])) {
            return $x['estp'];
        }

        return isset($x['so']) || $x['c'] === null ? [] : ($e['cartas'][$x['c']]['est'] ?? []);
    }

    private static function fl(array $m, string $f): bool
    {
        return in_array($f, $m['fl'] ?? [], true);
    }

    /** Um evento para disparar os gatilhos das cartas em jogo (resolvido em seguir()). */
    private static function sinal(array &$e, string $tipo, array $ctx): void
    {
        if (!empty($e['iniciado'])) {
            $e['ev'][] = [$tipo, $ctx];
        }
    }

    /**
     * Momentos que a tela anima: 'conjurou' (feitiço, encantamento ou equipamento jogado) e 'efeito' (o texto de uma
     * carta começou a acontecer). Lista curta e numerada: a tela anima os números que ainda não viu.
     */
    private static function brilho(array &$e, string $t, string $lado, ?string $c, ?int $i): void
    {
        $e['brilho_n'] = (int) ($e['brilho_n'] ?? 0) + 1;
        $e['brilho'][] = ['n' => $e['brilho_n'], 't' => $t, 'lado' => $lado, 'c' => $c, 'i' => $i];
        $e['brilho'] = array_slice($e['brilho'], -20);
    }

    /* ================================================================== */
    /*  Palavras-chave do Magic                                            */
    /* ================================================================== */

    /** Por que $b não pode bloquear $a (Voar, Imbloqueável, Sombra, Furtividade) ou null se pode. */
    private static function naoBloqueia(array $e, array $a, array $b): ?string
    {
        $na = self::nomeLenda($e, $a);
        if (self::tem($a, 'imbloqueavel')) {
            return "$na é Imbloqueável.";
        }
        if (self::tem($a, 'voar') && !self::tem($b, 'voar') && !self::tem($b, 'alcance')) {
            return "$na tem Voar: só Lendas com Voar ou Alcance podem bloqueá-la.";
        }
        if (self::tem($a, 'sombra') && !self::tem($b, 'sombra')) {
            return "$na tem Sombra: só Lendas com Sombra podem bloqueá-la.";
        }
        if (!self::tem($a, 'sombra') && self::tem($b, 'sombra')) {
            return self::nomeLenda($e, $b) . ' tem Sombra: só bloqueia Lendas com Sombra.';
        }
        if (self::tem($a, 'furtividade') && (int) $b['atk'] > (int) $a['atk']) {
            return "$na tem Furtividade: Lendas com mais poder que ela não podem bloqueá-la.";
        }

        return null;
    }

    /** Muda ataque/vida da Lenda só até o fim do turno (a limpeza desfaz). */
    private static function ateOFim(array &$e, string $l, int $k, int $atk, int $vida): void
    {
        $m = &$e['lados'][$l]['mesa'][$k];
        $novo = max(0, $m['atk'] + $atk);
        $m['tmp']['atk'] = (int) ($m['tmp']['atk'] ?? 0) + ($novo - $m['atk']);
        $m['atk'] = $novo;
        $m['max'] += $vida;
        $m['vida'] += $vida;
        $m['tmp']['vida'] = (int) ($m['tmp']['vida'] ?? 0) + $vida;
        unset($m);
    }

    /** Ao declarar o ataque: Exaltado (atacou sozinha), Grito de guerra (as outras atacantes) e Desafiador. */
    private static function palavrasDoAtaque(array $e, string $lado, array $insts): array
    {
        if (count($insts) === 1) {
            $n = count(array_filter($e['lados'][$lado]['mesa'], fn ($m) => self::tem($m, 'exaltado')));
            $k = self::idx($e['lados'][$lado]['mesa'], $insts[0]);
            if ($n > 0 && $k >= 0) {
                self::ateOFim($e, $lado, $k, $n, $n);
                self::log($e, 'Exaltado: ' . self::nomeLenda($e, $e['lados'][$lado]['mesa'][$k]) . " atacou sozinha e ganhou +$n/+$n até o fim do turno.");
            }
        }
        foreach ($insts as $i) {
            $k = self::idx($e['lados'][$lado]['mesa'], $i);
            if ($k < 0 || !self::tem($e['lados'][$lado]['mesa'][$k], 'grito')) {
                continue;
            }
            $outras = array_filter($insts, fn ($o) => $o !== $i);
            foreach ($outras as $o) {
                self::ateOFim($e, $lado, self::idx($e['lados'][$lado]['mesa'], $o), 1, 0);
            }
            if ($outras) {
                self::log($e, 'Grito de guerra de ' . self::nomeLenda($e, $e['lados'][$lado]['mesa'][$k]) . ': as outras atacantes ganham +1/+0 até o fim do turno.');
            }
        }
        foreach ($insts as $i) {
            $m = $e['lados'][$lado]['mesa'][self::idx($e['lados'][$lado]['mesa'], $i)];
            if (self::tem($m, 'desafiador')) {
                $e['fila'][] = ['lado' => $lado, 'fonte' => self::nomeLenda($e, $m), 'i' => $i, 'c' => $m['c'], 'jog' => null, 'ctx' => null, 'ult' => null,
                                'passos' => [['a' => 'desafiar', 'atk' => $i, 'alvo' => 'par']]];
            }
        }

        return $e;
    }

    /** Bloqueios confirmados, antes do dano: Bushido (+1/+1 ao bloquear ou ser bloqueada) e Flanquear (-1/-1 em quem bloqueia). */
    private static function palavrasDoBloqueio(array $e, string $at, string $df, array $pares): array
    {
        foreach ($pares as $ai => $bs) {
            $ka = self::idx($e['lados'][$at]['mesa'], (int) $ai);
            if ($ka < 0 || !$bs) {
                continue;
            }
            $a = $e['lados'][$at]['mesa'][$ka];
            if (self::tem($a, 'bushido')) {
                self::ateOFim($e, $at, $ka, 1, 1);
                self::log($e, 'Bushido: ' . self::nomeLenda($e, $a) . ' ganhou +1/+1 até o fim do turno.');
            }
            foreach ($bs as $bi) {
                $kb = self::idx($e['lados'][$df]['mesa'], (int) $bi);
                if ($kb < 0) {
                    continue;
                }
                $b = $e['lados'][$df]['mesa'][$kb];
                if (self::tem($b, 'bushido')) {
                    self::ateOFim($e, $df, $kb, 1, 1);
                    self::log($e, 'Bushido: ' . self::nomeLenda($e, $b) . ' ganhou +1/+1 até o fim do turno.');
                }
                if (self::tem($a, 'flanquear') && !self::tem($b, 'flanquear')) {
                    self::ateOFim($e, $df, $kb, -1, -1);
                    self::log($e, 'Flanquear: ' . self::nomeLenda($e, $b) . ' bloqueou ' . self::nomeLenda($e, $a) . ' e ficou com -1/-1 até o fim do turno.');
                    if ($e['lados'][$df]['mesa'][$kb]['vida'] <= 0) {
                        $e['lados'][$df]['mesa'][$kb]['morto'] = true;
                    }
                }
            }
        }

        return $e;
    }

    /** Bravura: toda carta que não é Lenda conjurada por $lado dá +1/+1 até o fim do turno às Lendas dele com Bravura. */
    private static function bravura(array &$e, string $lado): void
    {
        foreach ($e['lados'][$lado]['mesa'] as $k => $m) {
            if (self::tem($m, 'bravura')) {
                self::ateOFim($e, $lado, $k, 1, 1);
                self::log($e, 'Bravura: ' . self::nomeLenda($e, $m) . ' ganhou +1/+1 até o fim do turno.');
            }
        }
    }

    /** Põe uma carta no cemitério (o "toda vez que um hexomante entrar num cemitério" fica sabendo). */
    private static function cem(array &$e, string $lado, array $carta): void
    {
        if (!empty($carta['banir'])) {
            return; // conjurada do cemitério (Cristian Fernandez): é banida em vez de voltar para lá
        }
        if ((int) $carta['i'] === (int) ($e['lados'][$lado]['cmd'] ?? 0)
            && !in_array((int) $carta['i'], array_map('intval', array_column($e['lados'][$lado]['comando'] ?? [], 'i')), true)) {
            // comandante que não é Lenda (Fantasma das paredes): encerrado, destruído ou descartado, volta para a zona de comando
            $e['lados'][$lado]['comando'][] = ['i' => $carta['i'], 'c' => $carta['c']];
            self::log($e, self::nome($e, $carta['c']) . ' é o comandante de ' . self::quem($e, $lado) . ' e voltou para a zona de comando.');

            return;
        }
        $e['lados'][$lado]['cemiterio'][] = ['i' => $carta['i'], 'c' => $carta['c']];
        self::sinal($e, 'cemiterio', ['lado' => $lado, 'i' => (int) $carta['i'], 'c' => $carta['c']]);
    }

    /** Perde vida (Dinheiro): dano, pagamento, roubo. Quem reage a "perder ao menos 10$" fica sabendo. */
    private static function perdeVida(array &$e, string $lado, int $n, bool $dano = false): void
    {
        if ($n <= 0) {
            return;
        }
        $e['lados'][$lado]['vida'] -= $n;
        self::sinal($e, 'perde', ['lado' => $lado, 'i' => null, 'valor' => $n]);
        if ($dano) { // Homem polvo: "toda vez que um jogador sofrer dano"
            self::sinal($e, 'dano_jog', ['lado' => $lado, 'i' => null, 'valor' => $n]);
        }
    }

    /** Ainda cabe mais uma Lenda (criatura ou ficha) na mesa de $lado? Máximo de MESA_MAX por jogador. */
    private static function cabeNaMesa(array $e, string $lado): bool
    {
        return count($e['lados'][$lado]['mesa']) < self::MESA_MAX;
    }

    /** Uma Lenda nova na mesa (da carta $id), com os valores da carta. */
    private static function novaLenda(array $e, string $id, int $inst): array
    {
        $def = $e['cartas'][$id];
        $pal = self::palavrasDe($e, $id);

        return ['i' => $inst, 'c' => $id, 'atk' => (int) $def['ataque'], 'vida' => (int) $def['vida'], 'max' => (int) $def['vida'],
                'pode' => in_array('impeto', $pal, true), 'virada' => false, 'pal' => $pal, 'cont' => 0, 'nota' => '',
                'regen' => (int) ($def['regen'] ?? 0)];
    }

    /* ================================================================== */
    /*  Quantias e condições dos roteiros                                  */
    /* ================================================================== */

    /** Quantia de um passo: número, 'poder' (ataque da fonte) ou '#chave'. */
    private static function quantia(array $e, array $ef, mixed $n): int
    {
        if (is_int($n) || (is_string($n) && is_numeric($n))) {
            return (int) $n;
        }
        $dono = $ef['lado'];
        $fonte = self::fonteDe($e, $ef);
        $m = $ef['ctx']['m'] ?? null;
        $lendas = fn () => array_merge(...array_map(fn ($l) => $e['lados'][$l]['mesa'], self::vivos($e)));

        if (is_string($n) && str_starts_with($n, '#cont:')) { // contador da própria carta
            return max(0, (int) ($fonte['mk'][substr($n, 6)] ?? 0));
        }

        return max(0, match ($n) {
            'poder'       => (int) ($fonte['atk'] ?? ($m['atk'] ?? 0)),
            '#mao'        => count($e['lados'][$dono]['mao']),
            '#props'      => count(array_filter(array_merge(...array_map(fn ($l) => $e['lados'][$l]['feiticos'], self::vivos($e))),
                                fn ($f) => self::temSub($e, $f['c'], 'propriedade'))),
            '#lendas_cem' => count(array_filter($e['lados'][$dono]['cemiterio'], fn ($c) => ($e['cartas'][$c['c']]['tipo'] ?? '') === 'lenda')),
            '#pv_outras'  => array_sum(array_map(fn ($x) => max(0, (int) $x['vida']),
                                array_filter($e['lados'][$dono]['mesa'], fn ($x) => (int) $x['i'] !== (int) $ef['i']))),
            '#ataques'    => (int) ($fonte['ataques'] ?? 0),
            '#alunos'     => count(array_filter($e['lados'][$dono]['mesa'], fn ($x) => self::temSub($e, $x, 'aluno'))),
            '#metade'     => intdiv((int) ($ef['ctx']['valor'] ?? 0), 2),
            '#vida_fonte' => (int) ($fonte['vida'] ?? 0),
            '#outras'     => count(array_filter($lendas(), fn ($x) => (int) $x['i'] !== (int) $ef['i'])),
            '#kuros3'     => 3 * count(array_filter($lendas(), fn ($x) => self::temSub($e, $x, 'kuro'))),
            '#olhinhos'   => count(array_filter($lendas(), fn ($x) => GladioTexto::normalizar(self::nomeLenda($e, $x)) === 'olhinho')),
            '#meia_ctx'   => intdiv((int) ($m['max'] ?? 0), 2),
            '#atk_ctx'    => (int) ($m['atk'] ?? 0),
            '#max_ctx'    => (int) ($m['max'] ?? 1),
            '#props_meu'  => count(array_filter($e['lados'][$dono]['feiticos'], fn ($f) => self::temSub($e, $f['c'], 'propriedade'))),
            '#defesa2'    => count(array_filter($e['lados'][$dono]['mesa'], fn ($x) => (int) $x['vida'] <= 2)),
            '#formas_cem' => count(array_filter(array_merge(...array_map(fn ($l) => $e['lados'][$l]['cemiterio'], $e['ordem'])), fn ($c) => self::temSub($e, $c['c'], 'forma'))),
            '#tranqueiras' => count(array_filter($lendas(), fn ($x) => GladioTexto::normalizar(self::nomeLenda($e, $x)) === 'tranqueira')),
            '#tranqueiras3' => 3 * count(array_filter($lendas(), fn ($x) => GladioTexto::normalizar(self::nomeLenda($e, $x)) === 'tranqueira')),
            '#valor'      => (int) ($ef['ctx']['valor'] ?? 0),
            default       => 0,
        });
    }

    /** Condição de um passo "se" (ou de um efeito contínuo). */
    private static function condicao(array $e, array $ef, string $c): bool
    {
        $dono = $ef['lado'];
        $fonte = self::fonteDe($e, $ef);
        $ctx = $ef['ctx'] ?? [];
        $num = function (string $c, string $prefixo): ?array { // "cont:lendas>=3" => ['lendas', '>=', 3]
            return preg_match('/^' . $prefixo . ':([\w@]+)(>=|>)(\d+)$/', $c, $m) ? [$m[1], $m[2], (int) $m[3]] : null;
        };
        $cmp = fn (int $v, string $op, int $x) => $op === '>' ? $v > $x : $v >= $x;
        if ($q = $num($c, 'cont')) {
            return $cmp((int) ($fonte['mk'][$q[0]] ?? 0), $q[1], $q[2]);
        }
        if ($q = $num($c, 'vez')) {
            return $cmp((int) ($e['lados'][$e['vez']]['mk'][$q[0]] ?? 0), $q[1], $q[2]);
        }
        if ($q = $num($c, 'evento_fonte')) {
            return $cmp((int) ($fonte['mk'][$q[0] . '@' . ($ctx['lado'] ?? '')] ?? 0), $q[1], $q[2]);
        }
        if ($q = $num($c, 'tag')) {
            return $cmp(count(array_filter($e['lados'][$dono]['mesa'], fn ($x) => ($x['tag'] ?? null) === $q[0])), $q[1], $q[2]);
        }
        if (preg_match('/^outras(>=|>)(\d+)$/', $c, $mm)) {
            return $cmp(count(array_filter($e['lados'][$dono]['mesa'], fn ($x) => (int) $x['i'] !== (int) $ef['i'])), $mm[1], (int) $mm[2]);
        }
        if (preg_match('/^ctx:max(>=|>)(\d+)$/', $c, $mm)) {
            return $cmp((int) ($ctx['m']['max'] ?? 0), $mm[1], (int) $mm[2]);
        }
        if (str_starts_with($c, 'equip:')) { // equipado com a carta de nome X (na mesa ou no instante em que morreu)
            $nome = substr($c, 6);
            $eqs = $fonte ? array_map(fn ($f) => GladioTexto::normalizar(self::nome($e, $f['c'])),
                array_filter($e['lados'][$dono]['feiticos'], fn ($f) => (int) ($f['em'] ?? 0) === (int) $fonte['i'])) : ($ctx['m']['eqs'] ?? []);
            foreach ($eqs as $n) {
                if (str_contains(str_replace(['ç', 'ã'], ['c', 'a'], $n), $nome)) {
                    return true;
                }
            }

            return false;
        }

        return match ($c) {
            'ctx:forma'      => self::temSub($e, $ctx['c'] ?? null, 'forma'),
            'morreu_turno'   => (int) ($e['lados'][$dono]['mortes_ant'] ?? 0) > 0,
            'comprou_mais1'  => (int) ($e['lados'][$dono]['compras_ant'] ?? 0) > 1,
            'comprada_lenda' => ($ci = $ef['comprada'] ?? null) !== null && ($k = self::idx($e['lados'][$dono]['mao'], (int) $ci)) >= 0
                                && ($e['cartas'][$e['lados'][$dono]['mao'][$k]['c']]['tipo'] ?? '') === 'lenda',
            'mao>=5'         => count($e['lados'][$dono]['mao']) >= 5,
            'menos_lendas_jog' => ($j = $ef['jog'] ?? null) !== null && count($e['lados'][$dono]['mesa']) < count($e['lados'][$j]['mesa']),
            'par:fichas'     => ($q = (int) ($fonte['mk']['fichas@' . ($ctx['lado'] ?? '')] ?? 0)) > 0 && $q % 2 === 0,
            'morte_barata'   => ($u = $ctx['m']['ultimo_dano'] ?? null) !== null && in_array($u['tipo'] ?? '', ['combate', 'feitico'], true) && (int) ($u['custo'] ?? 99) <= 3,
            'hexo_inimigo'   => (bool) array_filter(self::inimigos($e, $dono), fn ($l) => (bool) array_filter($e['lados'][$l]['mesa'], fn ($x) => self::temSub($e, $x, 'hexomante'))),
            default          => false,
        };
    }

    /* ================================================================== */
    /*  Efeitos contínuos                                                  */
    /* ================================================================== */

    /** Efeitos contínuos de jogador ("seus feitiços custam -1", "olhe o topo do baralho") que $lado tem em jogo. */
    private static function doJogador(array $e, string $lado, string $j): array
    {
        $r = [];
        foreach (array_merge($e['lados'][$lado]['mesa'], $e['lados'][$lado]['feiticos']) as $x) {
            foreach (self::estDe($e, $x) as $q) {
                if (($q['j'] ?? null) === $j) {
                    $r[] = $q + ['_x' => $x];
                }
            }
        }

        return $r;
    }

    /** Alguém tem esse efeito em jogo (Lucas e Friga vale para a mesa toda)? */
    private static function naMesa(array $e, string $j): bool
    {
        foreach (self::vivos($e) as $l) {
            if (self::doJogador($e, $l, $j)) {
                return true;
            }
        }

        return false;
    }

    /** Custo para $lado jogar a carta $id agora (com os descontos em jogo e o da própria carta na mão). */
    private static function custoDe(array $e, string $lado, string $id, array $naMao = []): int
    {
        $c = $e['cartas'][$id];
        $custo = (int) $c['custo'] - (int) ($naMao['desc'] ?? 0);
        foreach (self::doJogador($e, $lado, 'custo') as $q) {
            $vale = match (true) {
                isset($q['sub'])                    => self::temSub($e, $id, $q['sub']),
                ($q['tipo'] ?? '') === 'feitico_dano' => $c['tipo'] !== 'lenda'
                    && (bool) array_filter($c['hab'] ?? [], fn ($h) => in_array('dano', array_column($h['passos'], 'a'), true)),
                default => true,
            };
            if ($vale) { // "-1 para cada dois contadores": o desconto vem dos contadores da carta
                $custo += is_string($q['d']) && str_starts_with($q['d'], '#menos_meio:')
                    ? -intdiv((int) ($q['_x']['mk'][substr($q['d'], 12)] ?? 0), 2) : (int) $q['d'];
            }
        }

        return max(0, $custo);
    }

    /**
     * Recalcula os efeitos contínuos: o que cada Lenda ganha de ataque, vida e palavras-chave por estar perto de quem dá
     * ("todos os seus hexomantes ganham…", "+0/+X onde X é a sua mão"), as marcas (fl) de proteção e de regra, e o
     * "todas as cartas são hexomantes". Tira o que valia antes e põe o que vale agora.
     */
    private static function estaticos(array $e): array
    {
        $e['hexo_todos'] = false;
        $e['glob'] = []; // regras da mesa toda: compra_dupla, uma_conjuracao, ficha_moeda, basileu, mill_dobro
        foreach (self::vivos($e) as $l) {
            foreach (array_merge($e['lados'][$l]['mesa'], $e['lados'][$l]['feiticos']) as $x) {
                foreach (self::estDe($e, $x) as $q) {
                    if (($q['g'] ?? null) === 'hexo_todos') {
                        $e['hexo_todos'] = true;
                    } elseif (isset($q['g'])) {
                        $e['glob'][$q['g']] = ($e['glob'][$q['g']] ?? 0) + 1;
                    }
                }
            }
        }
        $quer = []; // inst => { atk, vida, pal[], fl[] }
        foreach ($e['ordem'] as $l) {
            foreach ($e['lados'][$l]['mesa'] as $x) {
                $quer[(int) $x['i']] = ['atk' => 0, 'vida' => 0, 'pal' => [], 'fl' => []];
                if (!empty($x['st']['silhueta'])) { // virada de cabeça para baixo: hexo-imune, indestrutível e não ataca
                    $quer[(int) $x['i']]['pal'] = ['resistencia', 'indestrutivel'];
                    $quer[(int) $x['i']]['fl'][] = 'silhueta';
                }
                if (($x['st']['semdano'] ?? null) === $e['turno']) {
                    $quer[(int) $x['i']]['fl'][] = 'semdano';
                }
            }
        }
        foreach (self::vivos($e) as $l) {
            // de Lendas e também de encantamentos/equipamentos no campo (Mundo Conjurado: "toda Lenda sua…")
            foreach (array_merge($e['lados'][$l]['mesa'], $e['lados'][$l]['feiticos']) as $s) {
                $ef = ['lado' => $l, 'i' => (int) $s['i'], 'ctx' => null];
                foreach (self::estDe($e, $s) as $q) {
                    if (!isset($q['q']) || (isset($q['se']) && !self::condicao($e, $ef, $q['se']))) {
                        continue;
                    }
                    $alvos = [];
                    foreach (self::vivos($e) as $l2) {
                        foreach ($e['lados'][$l2]['mesa'] as $x) {
                            $ok = match ($q['q']) {
                                'fonte' => (int) $x['i'] === (int) $s['i'],
                                'meus' => $l2 === $l,
                                'meus_outros' => $l2 === $l && (int) $x['i'] !== (int) $s['i'],
                                'inimigos' => $l2 !== $l,
                                default => true,
                            };
                            if ($ok && isset($q['nome']) && !str_contains(GladioTexto::normalizar(self::nomeLenda($e, $x)), $q['nome'])) {
                                $ok = false;
                            }
                            if ($ok && self::temSub($e, $x, $q['sub'] ?? null)) {
                                $alvos[] = $x;
                            }
                        }
                    }
                    $atk = self::quantia($e, $ef, $q['atk'] ?? 0);
                    $vida = self::quantia($e, $ef, $q['vida'] ?? 0);
                    $pal = $q['pal'] ?? [];
                    if ($pal === '*meu') { // Radagast: as palavras-chave das outras Lendas do campo dele
                        $pal = [];
                        foreach ($e['lados'][$l]['mesa'] as $x) {
                            if ((int) $x['i'] !== (int) $s['i']) {
                                $pal = array_merge($pal, array_diff($x['pal'] ?? [], $x['est']['pal'] ?? []));
                            }
                        }
                    }
                    foreach ($alvos as $x) {
                        $w = &$quer[(int) $x['i']];
                        $w['atk'] += $atk;
                        $w['vida'] += $vida;
                        $w['pal'] = array_merge($w['pal'], $pal === '*amir' ? self::palavrasDoAmir($e, $x) : (array) $pal);
                        $w['fl'] = array_merge($w['fl'], $q['fl'] ?? []);
                        unset($w);
                    }
                }
            }
            foreach ($e['lados'][$l]['feiticos'] as $f) { // marcas dos equipamentos (Mitigação, Maleável, moeda…)
                if (!empty($f['em']) && isset($quer[(int) $f['em']])) {
                    $quer[(int) $f['em']]['fl'] = array_merge($quer[(int) $f['em']]['fl'], $e['cartas'][$f['c']]['mods']['fl'] ?? [],
                        !empty($f['serrote']) ? ['serrote'] : []);
                }
            }
        }
        foreach ($e['ordem'] as $l) {
            foreach ($e['lados'][$l]['mesa'] as $k => $x) {
                $q = $quer[(int) $x['i']];
                $antes = $x['est'] ?? ['atk' => 0, 'vida' => 0, 'pal' => []];
                $x['atk'] += $q['atk'] - (int) $antes['atk'];
                $x['vida'] += $q['vida'] - (int) $antes['vida'];
                $x['max'] += $q['vida'] - (int) $antes['vida'];
                $pal = array_values(array_diff($x['pal'] ?? [], $antes['pal'] ?? []));
                $novas = array_values(array_diff(array_unique(array_intersect($q['pal'], array_keys(self::PALAVRAS))), $pal));
                $x['pal'] = array_merge($pal, $novas);
                $x['est'] = ['atk' => $q['atk'], 'vida' => $q['vida'], 'pal' => $novas];
                $x['fl'] = array_values(array_unique($q['fl']));
                $e['lados'][$l]['mesa'][$k] = $x;
            }
        }

        return $e;
    }

    /* ================================================================== */
    /*  Dano e salvamentos                                                 */
    /* ================================================================== */

    /** Moeda da "Dificuldade de interação": true = coroa (o efeito vale). */
    private static function coroa(): bool
    {
        return self::$moeda ? (bool) (self::$moeda)() : random_int(0, 1) === 1;
    }

    /**
     * Dano numa Lenda (lado, índice na mesa), com as proteções: não sofre dano neste turno, de feitiços, de efeitos,
     * de combate, de cartas baratas, a moeda e o Alvinho (fica com o dano que derrubaria uma aliada, se aguentar).
     * $orig: { tipo: combate | feitico | lenda, custo }. Devolve o dano que entrou.
     */
    private static function danoLenda(array &$e, string $l, int $k, int $n, array $orig): int
    {
        $m = $e['lados'][$l]['mesa'][$k];
        $tipo = $orig['tipo'] ?? 'lenda';
        $nome = self::nomeLenda($e, $m);
        $motivo = match (true) {
            $n <= 0 => '',
            self::protegida($e, $m) => 'não sofre dano neste turno',
            $tipo === 'combate' && self::fl($m, 'imune_combate') => 'não sofre dano de ataques',
            $tipo !== 'combate' && (self::fl($m, 'imune_efeito')) => 'não sofre dano de efeitos de cartas',
            $tipo === 'feitico' && (self::fl($m, 'imune_feitico') || self::fl($m, 'mitigacao')) => 'não sofre dano de feitiços',
            isset($orig['custo']) && (int) $orig['custo'] <= 2 && self::fl($m, 'imune_custo2') => 'não sofre dano de cartas de custo 2 ou menos',
            self::fl($m, 'moeda') && !self::coroa() => 'a moeda deu cara: não foi afetada',
            default => null,
        };
        if ($motivo !== null) {
            if ($motivo !== '') {
                self::log($e, "$nome $motivo.");
            }

            return 0;
        }
        // Alvinho: puxa para si o dano que derrubaria uma Lenda aliada, se ele aguentar
        $sobra = $n - max(0, (int) $m['vida'] - 1);
        if ($sobra > 0 && !self::fl($m, 'alvinho') && !self::tem($m, 'indestrutivel')) {
            foreach ($e['lados'][$l]['mesa'] as $ka => $a) {
                if ($ka !== $k && self::fl($a, 'alvinho') && (int) $a['vida'] - $sobra >= 1) {
                    $e['lados'][$l]['mesa'][$ka]['vida'] -= $sobra;
                    $n -= $sobra;
                    self::log($e, self::nomeLenda($e, $a) . " puxou $sobra do dano que ia em $nome.");
                    break;
                }
            }
        }
        $e['lados'][$l]['mesa'][$k]['vida'] -= $n;
        if ($n > 0) {
            $e['lados'][$l]['mesa'][$k]['ultimo_dano'] = ['tipo' => $tipo, 'custo' => $orig['custo'] ?? null];
            if (!empty($orig['quebra']) && $e['lados'][$l]['mesa'][$k]['vida'] <= 0) {
                $e['lados'][$l]['mesa'][$k]['forte'] = true; // cai mesmo sendo indestrutível
            }
            $e = self::enfileirar($e, $l, $m['c'], (int) $m['i'], 'sofreu', ['lado' => $l, 'i' => (int) $m['i'], 'valor' => $n]);
        }
        if ($tipo === 'feitico' && $n > 0) {
            self::sinal($e, 'dano_feitico', ['lado' => $orig['lado'] ?? $l, 'i' => null]);
        }

        return $n;
    }

    /** Tipo de dano que a fonte do efeito causa (feitiço ou Lenda) e o custo dela, para as proteções. */
    private static function origemDe(array $e, array $ef): array
    {
        $c = $ef['c'] ?? (self::fonteDe($e, $ef)['c'] ?? null);
        $def = $c !== null ? ($e['cartas'][$c] ?? []) : [];

        return ['tipo' => ($def['tipo'] ?? 'lenda') === 'lenda' ? 'lenda' : 'feitico', 'custo' => (int) ($def['custo'] ?? 0), 'lado' => $ef['lado']];
    }

    /**
     * A Lenda $k de $l ia cair: Regenerar, Recursão, Lâmina do conquistador ou o Serrotinho equipado a seguram de pé.
     * Devolve true se ela foi salva.
     */
    private static function salvar(array &$e, string $l, int $k): bool
    {
        $m = $e['lados'][$l]['mesa'][$k];
        $nome = self::nomeLenda($e, $m);
        $de = function (array &$e) use ($l, $k) {
            $e['lados'][$l]['mesa'][$k]['vida'] = $e['lados'][$l]['mesa'][$k]['max'];
            unset($e['lados'][$l]['mesa'][$k]['morto']);
        };
        // Persistir (-1/-1) e Imortal (+1/+1): volta uma vez, como uma Lenda que acabou de entrar
        foreach (['persistir' => [-1, 'persistiu'], 'imortal' => [1, 'imortalizou']] as $pal => [$d, $marca]) {
            if (self::tem($m, $pal) && empty($m[$marca])) {
                $x = &$e['lados'][$l]['mesa'][$k];
                $x[$marca] = true;
                $x['atk'] = max(0, $x['atk'] + $d);
                $x['max'] = max(1, $x['max'] + $d);
                $x['pode'] = self::tem($x, 'impeto');
                $x['virada'] = false;
                unset($x['tmp'], $x);
                $de($e);
                self::log($e, "$nome " . ($d < 0 ? 'persistiu e voltou com -1/-1' : 'é imortal e voltou com +1/+1') . '.');

                return true;
            }
        }
        if ((int) ($m['regen'] ?? 0) > 0) {
            $e['lados'][$l]['mesa'][$k]['regen']--;
            $e['lados'][$l]['mesa'][$k]['virada'] = true;
            $de($e);
            self::log($e, "$nome regenerou e voltou de pé (virada)" . ($e['lados'][$l]['mesa'][$k]['regen'] ? ', ainda pode mais ' . $e['lados'][$l]['mesa'][$k]['regen'] . 'x' : '') . '.');

            return true;
        }
        foreach ($e['ordem'] as $ld) {
            foreach ($e['lados'][$ld]['feiticos'] as $f) {
                if ((int) ($f['em'] ?? 0) !== (int) $m['i']) {
                    continue;
                }
                $salva = !empty($f['serrote']) ? 'serrote' : ($e['cartas'][$f['c']]['mods']['salva'] ?? null);
                if (!$salva) {
                    continue;
                }
                $e = self::desequipar($e, $ld, (int) $f['i']);
                $kf = self::idx($e['lados'][$ld]['feiticos'], (int) $f['i']);
                array_splice($e['lados'][$ld]['feiticos'], $kf, 1);
                if ($salva === 'serrote') { // o Serrotinho morre no lugar dela
                    self::cem($e, $ld, $f);
                    $e = self::enfileirar($e, $ld, $f['c'], (int) $f['i'], 'morrer');
                    self::log($e, self::nome($e, $f['c']) . " morreu no lugar de $nome.");
                } else {
                    self::cem($e, $ld, $f);
                    if ($salva === 'conquistador') {
                        foreach (['atk', 'vida', 'max'] as $campo) {
                            $e['lados'][$l]['mesa'][$k][$campo] += 3;
                        }
                    }
                    self::log($e, "$nome ressuscitou (" . self::nome($e, $f['c']) . ($salva === 'conquistador' ? ', +3/+3' : '') . ' foi para o cemitério).');
                }
                $de($e);

                return true;
            }
        }

        return false;
    }

    /* ================================================================== */
    /*  Habilidades ativadas                                               */
    /* ================================================================== */

    /** Habilidades que a carta $x (na mesa ou no cemitério de $lado) oferece agora. */
    private static function habilidadesDe(array $e, array $x, string $zona): array
    {
        $r = [];
        foreach ($x['c'] !== null ? ($e['cartas'][$x['c']]['ativ'] ?? []) : [] as $k => $h) {
            if (($h['zona'] ?? 'mesa') === $zona) {
                $r[$k] = $h;
            }
        }
        if ($zona === 'mesa' && !empty($x['st']['silhueta'])) {
            $r['silhueta'] = ['rot' => 'Pagar 1 de energia: desvirar (sai da Silhueta)', 'custo' => ['energia' => 1], 'vez' => 'qualquer',
                              'passos' => [['a' => 'desvirar', 'alvo' => 'fonte', 'filtro' => null]]];
        }

        return $r;
    }

    /** Usa uma habilidade de uma carta sua (na mesa ou, como Flavinho, no cemitério), pagando o custo escrito nela. */
    public static function habilidade(array $e, string $lado, int $inst, string $qual): array
    {
        self::exigirAtivo($e, $lado);
        self::exigirSemEscolha($e);
        $zona = self::idx($e['lados'][$lado]['mesa'], $inst) >= 0 ? 'mesa' : 'cemiterio';
        $k = self::idx($e['lados'][$lado][$zona], $inst);
        if ($k < 0) {
            throw new \InvalidArgumentException('Essa carta não está com você.');
        }
        $x = $e['lados'][$lado][$zona][$k];
        $h = self::habilidadesDe($e, $x, $zona)[$qual] ?? null;
        if (!$h) {
            throw new \InvalidArgumentException('Essa carta não tem essa habilidade agora.');
        }
        if (($h['vez'] ?? 'meu') === 'meu') {
            self::exigirTurno($e, $lado);
        } elseif ($e['fase'] === 'compra' || $e['fase'] === 'final') {
            throw new \InvalidArgumentException('Espere a vez andar.');
        }
        if (!empty($h['uma']) && ($x['usou'][$qual] ?? null) === $e['turno']) {
            throw new \InvalidArgumentException('Essa habilidade é uma vez por turno.');
        }
        $custo = $h['custo'] ?? [];
        $L = $e['lados'][$lado];
        if ((int) ($custo['energia'] ?? 0) > $L['energia']) {
            throw new \InvalidArgumentException('Energia insuficiente.');
        }
        if (!empty($custo['virar']) && !empty($x['virada'])) {
            throw new \InvalidArgumentException('Ela já está virada.');
        }
        if (!empty($custo['descartar']) && count($L['mao']) < (int) $custo['descartar']) {
            throw new \InvalidArgumentException('Você não tem cartas para descartar.');
        }
        if (!empty($custo['sacrificar']) && count($L['mesa']) - ($zona === 'mesa' ? 1 : 0) < (int) $custo['sacrificar']) {
            throw new \InvalidArgumentException('Você não tem outra Lenda para sacrificar.');
        }
        $e['lados'][$lado]['energia'] -= (int) ($custo['energia'] ?? 0);
        if (!empty($custo['vida'])) {
            self::perdeVida($e, $lado, (int) $custo['vida']);
        }
        if (!empty($custo['virar'])) {
            $e['lados'][$lado][$zona][$k]['virada'] = true;
        }
        $e['lados'][$lado][$zona][$k]['usou'][$qual] = $e['turno'];
        $passos = [];
        for ($n = 0; $n < (int) ($custo['descartar'] ?? 0); $n++) {
            $passos[] = ['a' => 'descartar', 'n' => 1, 'alvo' => 'eu', 'obrig' => true];
        }
        for ($n = 0; $n < (int) ($custo['sacrificar'] ?? 0); $n++) {
            $passos[] = ['a' => 'sacrificar', 'n' => 1, 'alvo' => 'eu', 'obrig' => true];
        }
        $nome = $x['c'] !== null ? self::nome($e, $x['c']) : self::nomeLenda($e, $x);
        $e['fila'][] = ['lado' => $lado, 'fonte' => $nome, 'i' => $inst, 'c' => $x['c'], 'passos' => array_merge($passos, $h['passos']),
                        'jog' => null, 'ctx' => null, 'ult' => null];
        self::log($e, self::quem($e, $lado) . " usou $nome: {$h['rot']}.");

        return self::seguir($e);
    }

    /** Vassago, o primeiro ascensionista: enquanto ele estiver na mesa, os encantamentos do dono não saem de campo. */
    private static function encantoFixo(array $e, string $lado, array $f): bool
    {
        return ($e['cartas'][$f['c']]['tipo'] ?? '') === 'encanto' && self::doJogador($e, $lado, 'encantos_fixos');
    }

    /** Cartas que dão a vitória enquanto a condição valer: Lendas de Vassago (4 cartas com o mesmo nome no seu campo). */
    /** Confere as vitórias de carta a qualquer momento (o controlador chama depois de toda ação, mesmo as que não andam o jogo). */
    public static function conferirVitorias(array $e): array
    {
        return $e['fim'] ? $e : self::vitoriasDeCarta($e);
    }

    private static function vitoriasDeCarta(array $e): array
    {
        if ($e['fase'] === 'mulligan') {
            return $e;
        }
        foreach (self::vivos($e) as $l) {
            $ganha = null;
            foreach (self::doJogador($e, $l, 'quatro_iguais') as $q) {
                $nome = GladioTexto::normalizar(self::nome($e, $q['_x']['c']));
                $iguais = count(array_filter(array_merge($e['lados'][$l]['feiticos'], $e['lados'][$l]['mesa']),
                    fn ($x) => $x['c'] !== null && GladioTexto::normalizar(self::nome($e, $x['c'])) === $nome));
                if ($iguais >= 4) {
                    $ganha = self::nome($e, $q['_x']['c']);
                }
            }
            if ($ganha) {
                self::log($e, self::quem($e, $l) . " venceu pelo efeito de $ganha.");
                foreach (self::inimigos($e, $l) as $o) {
                    $e = self::eliminar($e, $o, 'efeito de ' . $ganha);
                }

                return $e;
            }
        }

        return $e;
    }

    /* ================================================================== */
    /*  Mulligan (antes do primeiro turno)                                 */
    /* ================================================================== */

    /**
     * Abre a fase de mulligan: cada jogador olha a mão inicial e decide manter ou trocar. Mulligan: a mão volta para o
     * baralho, embaralha e compra MAO_INICIAL de novo; ao manter, põe no fundo do baralho uma carta por mulligan feito
     * (como o London mulligan do Magic). Quando todos mantêm, começa o turno 1.
     * Estado: mull { lado: { n (mulligans feitos), ok (manteve) } }, fase 'mulligan'.
     */
    public static function abrirMulligan(array $e): array
    {
        $e['fase'] = 'mulligan';
        $e['mull'] = [];
        foreach (self::vivos($e) as $l) {
            $e['mull'][$l] = ['n' => 0, 'ok' => false];
        }
        $e['desde'] = time();
        self::log($e, 'Mulligan: cada um olha a mão inicial e decide se mantém ou troca.');

        return $e;
    }

    private static function exigirMulligan(array $e, string $lado): void
    {
        self::exigirAtivo($e, $lado);
        if ($e['fase'] !== 'mulligan' || !isset($e['mull'][$lado])) {
            throw new \InvalidArgumentException('O mulligan só acontece antes do primeiro turno.');
        }
        if ($e['mull'][$lado]['ok']) {
            throw new \InvalidArgumentException('Você já manteve a sua mão.');
        }
    }

    /** Troca a mão: tudo volta para o baralho, embaralha e compra a mão inicial de novo. */
    public static function mulligan(array $e, string $lado, ?callable $embaralhar = null): array
    {
        self::exigirMulligan($e, $lado);
        if ($e['mull'][$lado]['n'] + 1 >= self::MAO_INICIAL) {
            throw new \InvalidArgumentException('Não dá para fazer mais mulligans: você ficaria sem cartas na mão.');
        }
        $L = &$e['lados'][$lado];
        $b = array_merge($L['baralho'], array_map(fn ($c) => ['i' => $c['i'], 'c' => $c['c']], $L['mao']));
        $L['baralho'] = array_values($embaralhar ? $embaralhar($b) : (function ($b) { shuffle($b); return $b; })($b));
        $L['mao'] = array_splice($L['baralho'], 0, self::MAO_INICIAL);
        unset($L);
        $e['mull'][$lado]['n']++;
        $n = $e['mull'][$lado]['n'];
        self::log($e, self::quem($e, $lado) . " fez mulligan ($n): ao manter, põe $n carta" . ($n > 1 ? 's' : '') . ' no fundo do baralho.');

        return $e;
    }

    /** Mantém a mão, pondo no fundo do baralho as cartas escolhidas (uma por mulligan feito). Todos mantiveram: começa. */
    public static function manter(array $e, string $lado, array $fundo = []): array
    {
        self::exigirMulligan($e, $lado);
        $n = $e['mull'][$lado]['n'];
        $fundo = array_values(array_unique(array_map('intval', $fundo)));
        if (count($fundo) !== $n) {
            throw new \InvalidArgumentException($n ? "Escolha $n carta" . ($n > 1 ? 's' : '') . ' da mão para o fundo do baralho.' : 'Não precisa escolher cartas.');
        }
        foreach ($fundo as $i) {
            $k = self::idx($e['lados'][$lado]['mao'], $i);
            if ($k < 0) {
                throw new \InvalidArgumentException('Essa carta não está na sua mão.');
            }
            $e['lados'][$lado]['baralho'][] = $e['lados'][$lado]['mao'][$k];
            array_splice($e['lados'][$lado]['mao'], $k, 1);
        }
        $e['mull'][$lado]['ok'] = true;
        self::log($e, self::quem($e, $lado) . ' manteve a mão' . ($n ? " com " . count($e['lados'][$lado]['mao']) . ' cartas' : '') . '.');

        return self::fimMulligan($e);
    }

    /** Todos mantiveram (ou saíram): começa o primeiro turno. */
    private static function fimMulligan(array $e): array
    {
        foreach (self::vivos($e) as $l) {
            if (empty($e['mull'][$l]['ok'])) {
                return $e;
            }
        }
        $e['fase'] = 'principal';
        $e['desde'] = time();
        self::log($e, 'Todos mantiveram a mão: a partida começa. Turno 1 de ' . self::quem($e, $e['vez']) . '.');

        return $e;
    }

    /** Acabou o tempo do mulligan: quem não decidiu mantém (o fundo vai ao acaso). */
    private static function tempoMulligan(array $e): array
    {
        foreach (self::vivos($e) as $l) {
            if (empty($e['mull'][$l]['ok'])) {
                $mao = array_column($e['lados'][$l]['mao'], 'i');
                shuffle($mao);
                $e = self::manter($e, $l, array_slice($mao, 0, $e['mull'][$l]['n']));
            }
        }

        return $e;
    }

    /* ================================================================== */
    /*  Respostas fora do turno (Vergonha de Dante, Imbuir)                */
    /* ================================================================== */

    /** Quem pode responder agora com uma carta de resposta $tipo (na roda, sem $quem): lados com a carta e energia. */
    private static function respondentes(array $e, string $quem, string|array $tipos, ?string $campo = null, ?string $carta = null): array
    {
        $r = [];
        foreach (self::vivos($e) as $l) {
            if ($l !== $quem && self::opcoesResposta($e, $l, (array) $tipos, $campo, $carta)) {
                $r[] = $l;
            }
        }

        return $r;
    }

    /**
     * O que $l pode usar para responder agora: cartas de resposta da mão (com energia para pagar) e efeitos do campo dele
     * (Marechal Fonsceca: pagar 10$ para negar uma Lenda de custo até 3; Painel de Kuros: pagar 2 de energia para negar
     * uma carta conjurada). Devolve [valor => rótulo].
     */
    private static function opcoesResposta(array $e, string $l, array $tipos, ?string $campo, ?string $carta): array
    {
        $ops = [];
        foreach ($e['lados'][$l]['mao'] as $c) {
            if (in_array($e['cartas'][$c['c']]['resp'] ?? null, $tipos, true) && self::custoDe($e, $l, $c['c'], $c) <= $e['lados'][$l]['energia']) {
                $ops[(string) $c['i']] = self::nome($e, $c['c']);
            }
        }
        if ($campo === 'marechal' && $carta !== null && (int) ($e['cartas'][$carta]['custo'] ?? 99) <= 3) {
            foreach (self::doJogador($e, $l, 'marechal_negar') as $q) {
                if ($e['lados'][$l]['vida'] > (int) $q['vida']) {
                    $ops['campo:' . $q['_x']['i']] = self::nome($e, $q['_x']['c']) . ': pagar ' . $q['vida'] . '$ e negar';
                }
            }
        }
        if (in_array('protecao', $tipos, true) && is_string($campo) && str_starts_with($campo, 'alvo:inst:')) {
            foreach (self::protecoes($e, $l, (int) substr($campo, 10)) as $chave => $rot) {
                $ops[$chave] = $rot;
            }
        }
        if ($campo === 'painel') {
            foreach (self::doJogador($e, $l, 'painel_negar') as $q) {
                if ($e['lados'][$l]['energia'] >= (int) $q['energia']) {
                    $ops['campo:' . $q['_x']['i']] = self::nome($e, $q['_x']['c']) . ': pagar ' . $q['energia'] . ' de energia e negar';
                }
            }
        }

        return $ops;
    }

    /** Pergunta ao próximo que pode responder; sem ninguém, a jogada segue como estava. */
    private static function pedirResposta(array $e): array
    {
        $p = $e['pilha'];
        while ($p['quem']) {
            $l = array_shift($p['quem']);
            $proprio = $l === ($p['lado'] ?? null); // quem conjurou só pode Intoxicar o próprio feitiço
            $ops = self::opcoesResposta($e, $l, $proprio ? ['intoxicar'] : (array) $p['resp'], $proprio ? null : ($p['campo'] ?? null), $p['c'] ?? null);
            if (!$ops) {
                continue;
            }
            $e['pilha'] = $p;
            $e['escolha'] = ['lado' => $l, 'tipo' => 'resposta', 'opcoes' => array_map(fn ($k) => is_numeric($k) ? (int) $k : (string) $k, array_keys($ops)),
                             'rotulos' => array_map('strval', $ops), 'texto' => $proprio ? 'Você pode Intoxicar o seu próprio feitiço (ele passa a mirar todas as Lendas do campo)' : $p['texto'], 'fonte' => 'Resposta', 'obrig' => false,
                             'passo' => ['a' => 'resposta'], 'desde' => time()];

            return $e;
        }
        $e['pilha'] = null;

        return self::terminarPilha($e, $p);
    }

    /** Ninguém respondeu: a jogada que esperava continua. */
    private static function terminarPilha(array $e, array $p): array
    {
        if ($p['tipo'] === 'negar') {
            return self::entrouLenda($e, $p['lado'], $p['c'], $p['i']);
        }
        if ($p['tipo'] === 'alvo') {
            return self::executar($e, $p['passo']);
        }
        if ($p['tipo'] === 'feitico') {
            return self::conjurado($e, $p['lado'], $p['c'], $p['i']);
        }
        if ($p['tipo'] === 'contra') {
            return self::aplicarResposta($e, $p);
        }

        return $e;
    }

    /** O feitiço resolve de verdade (ninguém negou): os efeitos e o "sempre que um feitiço for conjurado" dos outros. */
    private static function conjurado(array $e, string $lado, string $c, int $inst, ?array $passos = null): array
    {
        if ($passos !== null) {
            $e['fila'][] = ['lado' => $lado, 'fonte' => self::nome($e, $c), 'i' => $inst, 'c' => $c, 'passos' => $passos, 'jog' => null, 'ctx' => null, 'ult' => null];
        } else {
            $e = self::enfileirar($e, $lado, $c, $inst, 'conjurar');
        }

        return self::evento($e, 'feitico', ['lado' => $lado, 'i' => $inst, 'c' => $c]);
    }

    /**
     * Olhos de Rarakoara: a carta que o jogador marcado jogou (Lenda, feitiço, encantamento, equipamento ou resposta) é
     * cancelada e vai para o cemitério sem efeito. Depois de cancelar as duas, os Olhos vão para o cemitério.
     */
    private static function olhosCancelam(array &$e, string $lado, int $inst, string $id): bool
    {
        foreach (self::vivos($e) as $l) {
            foreach ($e['lados'][$l]['feiticos'] as $kf => $f) {
                if (($f['jog'] ?? null) !== $lado || (int) ($f['mk']['cancela'] ?? 0) <= 0) {
                    continue;
                }
                $e['lados'][$l]['feiticos'][$kf]['mk']['cancela']--;
                self::cem($e, $lado, ['i' => $inst, 'c' => $id]);
                self::brilho($e, 'efeito', $l, $f['c'], (int) $f['i']);
                self::log($e, self::quem($e, $lado) . ' jogou ' . self::nome($e, $id) . ', mas ' . self::nome($e, $f['c']) . ' cancelou.');
                if ($e['lados'][$l]['feiticos'][$kf]['mk']['cancela'] <= 0) {
                    array_splice($e['lados'][$l]['feiticos'], $kf, 1);
                    self::cem($e, $l, $f);
                }

                return true;
            }
        }

        return false;
    }

    /** Amir: as palavras-chave escolhidas pelas tranqueiras que ainda estão em campo. */
    private static function palavrasDoAmir(array $e, array $x): array
    {
        $r = [];
        foreach ($x['amir_pal'] ?? [] as $t => $pal) {
            if (self::acharLenda($e, (int) $t)) {
                $r[] = $pal;
            }
        }

        return $r;
    }

    /** A Lenda está protegida neste turno (Aporium: "não pode sofrer dano este turno"): não sofre dano nem é destruída. */
    private static function protegida(array $e, array $m): bool
    {
        return self::fl($m, 'semdano') || (($m['st']['semdano'] ?? null) === ($e['turno'] ?? null));
    }

    /**
     * Habilidades de proteção que $l pode usar agora na Lenda $inst dele (escolhida como alvo de um efeito):
     * [ 'hab:<inst>:<qual>' => rótulo ].
     */
    private static function protecoes(array $e, string $l, int $inst): array
    {
        $k = self::idx($e['lados'][$l]['mesa'], $inst);
        if ($k < 0) {
            return [];
        }
        $x = $e['lados'][$l]['mesa'][$k];
        if (self::protegida($e, $x)) {
            return [];
        }
        $r = [];
        foreach (self::habilidadesDe($e, $x, 'mesa') as $qual => $h) {
            $protege = in_array('semdano', array_column(array_filter($h['passos'], fn ($q) => ($q['a'] ?? '') === 'status'), 's'), true);
            $custo = $h['custo'] ?? [];
            if (!$protege || (!empty($h['uma']) && ($x['usou'][$qual] ?? null) === $e['turno'])
                || (int) ($custo['energia'] ?? 0) > $e['lados'][$l]['energia'] || count($e['lados'][$l]['mao']) < (int) ($custo['descartar'] ?? 0)) {
                continue;
            }
            $r["hab:$inst:$qual"] = self::nomeLenda($e, $x) . ': ' . $h['rot'];
        }

        return $r;
    }

    /** $lado responde com $valor: a instância de uma carta da mão ou 'campo:<inst>' (efeito de uma carta em jogo). */
    private static function responder(array $e, string $lado, int|string $valor): array
    {
        $p = $e['pilha'];
        $e['pilha'] = null;
        if (is_string($valor) && str_starts_with($valor, 'hab:')) { // Aporium: protege-se antes de o efeito acontecer
            [, $inst, $qual] = explode(':', $valor, 3);
            $k = self::idx($e['lados'][$lado]['mesa'], (int) $inst);
            $x = $e['lados'][$lado]['mesa'][$k];
            $h = self::habilidadesDe($e, $x, 'mesa')[$qual];
            $custo = $h['custo'] ?? [];
            $e['lados'][$lado]['energia'] -= (int) ($custo['energia'] ?? 0);
            $e['lados'][$lado]['mesa'][$k]['usou'][$qual] = $e['turno'];
            $e['lados'][$lado]['mesa'][$k]['st']['semdano'] = $e['turno']; // vale já, antes do efeito que a mirou
            $nome = self::nomeLenda($e, $x);
            self::log($e, self::quem($e, $lado) . " respondeu usando $nome: {$h['rot']}.");
            $passos = [];
            for ($n = 0; $n < (int) ($custo['descartar'] ?? 0); $n++) { // o custo de descartar vem logo depois
                $passos[] = ['a' => 'descartar', 'n' => 1, 'alvo' => 'eu', 'obrig' => true];
            }
            if ($passos) {
                $e['fila'][] = ['lado' => $lado, 'fonte' => $nome, 'i' => (int) $inst, 'c' => $x['c'], 'passos' => $passos, 'jog' => null, 'ctx' => null, 'ult' => null];
            }
            $e['pilha'] = $p; // os outros ainda podem responder ao mesmo efeito

            return self::pedirResposta($e);
        }
        if (is_string($valor) && str_starts_with($valor, 'campo:')) { // Marechal Fonsceca / Painel de Kuros
            $x = self::emJogo($e, (int) substr($valor, 6));
            $fonteX = $x ? $e['lados'][$x[0]][$x[1]][$x[2]] : null;
            if ($p['tipo'] === 'negar') {
                $q = self::doJogador($e, $lado, 'marechal_negar')[0] ?? ['vida' => 10];
                self::perdeVida($e, $lado, (int) $q['vida']);
            } else {
                $q = self::doJogador($e, $lado, 'painel_negar')[0] ?? ['energia' => 2];
                $e['lados'][$lado]['energia'] -= (int) $q['energia'];
            }
            $nome = $fonteX ? self::nome($e, $fonteX['c']) : 'Uma carta';
            $tipo = $p['tipo'] === 'negar' ? 'negar' : 'negar_feitico';
        } else {
            $k = self::idx($e['lados'][$lado]['mao'], (int) $valor);
            $carta = $e['lados'][$lado]['mao'][$k];
            $e['lados'][$lado]['energia'] -= self::custoDe($e, $lado, $carta['c'], $carta);
            array_splice($e['lados'][$lado]['mao'], $k, 1);
            if (self::olhosCancelam($e, $lado, (int) $carta['i'], $carta['c'])) {
                $e['pilha'] = $p; // a resposta não acontece: os outros ainda podem responder

                return self::pedirResposta($e);
            }
            self::cem($e, $lado, $carta);
            self::brilho($e, 'conjurou', $lado, $carta['c'], (int) $valor);
            self::bravura($e, $lado);
            self::sinal($e, 'feitico', ['lado' => $lado, 'i' => (int) $valor, 'c' => $carta['c']]);
            $nome = self::nome($e, $carta['c']);
            $tipo = $e['cartas'][$carta['c']]['resp'] ?? 'negar';
        }
        $quem = self::quem($e, $lado);
        // corrente: antes de a resposta acontecer, os outros (inclusive quem jogou a carta respondida) podem negá-la
        // com outra negação de feitiço (Tranca amaldiçoada, Painel de Kuros), e essa negação também pode ser negada
        $alvoNome = $p['tipo'] === 'contra' ? $p['nome'] : self::nome($e, $p['c'] ?? '');
        $c = $carta['c'] ?? ($fonteX['c'] ?? null);
        $contra = ['tipo' => 'contra', 'lado' => $lado, 'c' => $c, 'i' => is_int($valor) ? $valor : null, 'nome' => $nome, 'rtipo' => $tipo,
                   'alvo' => $p, 'resp' => ['negar_feitico'], 'campo' => 'painel',
                   'quem' => self::respondentes($e, $lado, ['negar_feitico'], 'painel', $c),
                   'texto' => "$quem respondeu a $alvoNome com $nome: você pode negar $nome"];
        self::log($e, "$quem respondeu a $alvoNome com $nome.");
        $e['pilha'] = $contra;

        return self::pedirResposta($e);
    }

    /** Ninguém negou a resposta: ela acontece (e, se era a negação de outra resposta, o que ela negou volta a valer). */
    private static function aplicarResposta(array $e, array $contra): array
    {
        $p = $contra['alvo'];
        $lado = $contra['lado'];
        $nome = $contra['nome'];
        $tipo = $contra['rtipo'];
        $quem = self::quem($e, $lado);
        if ($p['tipo'] === 'contra') { // negou outra resposta: ela não acontece, e o que ela respondia segue o caminho dele
            self::log($e, "$quem negou {$p['nome']} com $nome.");
            $e['pilha'] = $p['alvo'];

            return self::pedirResposta($e);
        }
        if ($p['tipo'] === 'negar') { // a Lenda não chega a entrar: vai para o cemitério sem efeito nenhum
            $k = self::idx($e['lados'][$p['lado']]['mesa'], $p['i']);
            if ($k >= 0) {
                $m = $e['lados'][$p['lado']]['mesa'][$k];
                array_splice($e['lados'][$p['lado']]['mesa'], $k, 1);
                self::cem($e, $p['lado'], $m);
            }
            self::log($e, "$quem respondeu com $nome: a invocação de " . self::nome($e, $p['c']) . ' foi negada.');

            return $e;
        }
        if ($p['tipo'] === 'feitico') {
            $alvo = self::nome($e, $p['c']);
            if ($tipo === 'negar_feitico') { // Tranca amaldiçoada / Painel de Kuros: o feitiço não acontece
                if (($kf = self::idx($e['lados'][$p['lado']]['feiticos'], $p['i'])) >= 0) {
                    $f = $e['lados'][$p['lado']]['feiticos'][$kf];
                    array_splice($e['lados'][$p['lado']]['feiticos'], $kf, 1);
                    self::cem($e, $p['lado'], $f);
                }
                self::log($e, "$quem respondeu com $nome: $alvo foi negado.");

                return $e;
            }
            $passos = [];
            foreach ($e['cartas'][$p['c']]['hab'] ?? [] as $h) {
                if ($h['g'] === 'conjurar') {
                    array_push($passos, ...$h['passos']);
                }
            }
            if ($tipo === 'intoxicar') { // Intoxicar: o que mirava uma Lenda ou alvo vale para todas as Lendas do campo
                $passos = array_map(fn ($q) => in_array($q['alvo'] ?? '', ['lenda', 'qualquer'], true) ? ['alvo' => 'lendas', 'filtro' => 'qualquer'] + $q : $q, $passos);
                self::log($e, "$quem respondeu com $nome: $alvo agora mira todas as Lendas do campo.");

                return self::conjurado($e, $p['lado'], $p['c'], $p['i'], $passos);
            }
            // Ressonância: o feitiço acontece e quem respondeu conjura uma cópia de graça
            self::log($e, "$quem respondeu com $nome: conjura $alvo de novo, de graça.");
            $e = self::conjurado($e, $p['lado'], $p['c'], $p['i']);
            if ($passos) {
                $e['fila'][] = ['lado' => $lado, 'fonte' => $alvo, 'i' => null, 'c' => $p['c'], 'passos' => $passos, 'jog' => null, 'ctx' => null, 'ult' => null];
            }

            return $e;
        }
        // Imbuir: quem respondeu escolhe o novo alvo do ponto de vista dele: qualquer Lenda em campo (as próprias também,
        // mesmo com Hexo-Imune) e, se o efeito mirava jogadores, qualquer jogador
        self::log($e, "$quem respondeu com $nome: vai trocar o alvo.");
        $passo = $p['original'] + ['imbuido' => true];
        $jogadores = array_map(fn ($j) => 'j:' . $j, self::vivos($e));
        $opcoes = match ($p['esc_tipo']) {
            'jogador'  => $jogadores,
            'qualquer' => array_merge(self::lendasEscolhiveis($e, $lado, 'qualquer'), $jogadores),
            default    => self::lendasEscolhiveis($e, $lado, 'qualquer'),
        };
        $e['escolha'] = ['lado' => $lado, 'tipo' => $p['esc_tipo'], 'opcoes' => $opcoes ?: $p['opcoes'], 'texto' => "$nome: escolha o novo alvo",
                         'fonte' => $nome, 'obrig' => true, 'passo' => $passo];

        return $e;
    }

    /** Lenda que entrou de verdade (ninguém negou): efeitos de entrada e o "quando uma Lenda entrar" dos outros. */
    private static function entrouLenda(array $e, string $lado, string $c, int $inst): array
    {
        $e = self::enfileirar($e, $lado, $c, $inst, 'entrar');
        self::sinal($e, 'entra', ['lado' => $lado, 'i' => $inst, 'c' => $c]);

        return $e;
    }

    /* ================================================================== */
    /*  Efeitos com hora marcada                                           */
    /* ================================================================== */

    /** Troca, nos passos guardados, as referências ao momento ("a mesma Lenda", "o jogador do evento") pelo que eram. */
    private static function fixarRefs(array $passos, array $ef, ?string $quem): array
    {
        foreach ($passos as &$q) {
            if (($q['alvo'] ?? null) === 'ultima' && ($ef['ult'] ?? null) !== null) {
                $q['alvo'] = 'inst:' . $ef['ult'];
            } elseif (($q['alvo'] ?? null) === 'quem' && $quem !== null) {
                $q['alvo'] = 'lado:' . $quem;
            }
        }
        unset($q);

        return $passos;
    }

    /** Efeitos marcados para o início/fim do turno de alguém (ou para daqui a N turnos) que chegaram na hora. */
    private static function atrasados(array $e, string $vez, string $quando): array
    {
        $resta = [];
        foreach ($e['atrasos'] ?? [] as $a) {
            $hora = $a['quando'] === $quando && (isset($a['turno_alvo']) ? $e['turno'] >= $a['turno_alvo'] : ($a['lado'] === $vez && $e['turno'] > $a['depois']));
            if (!$hora) {
                $resta[] = $a;
                continue;
            }
            if (!empty($a['viva']) && !self::emJogo($e, $a['i'])) {
                continue; // "daqui a 6 turnos" de uma Lenda que já saiu
            }
            $e['fila'][] = ['lado' => $a['dono'], 'fonte' => $a['fonte'], 'i' => $a['i'], 'c' => $a['c'] ?? null, 'passos' => $a['passos'],
                            'jog' => $a['jog'] ?? null, 'ctx' => null, 'ult' => null];
        }
        $e['atrasos'] = $resta;

        return $e;
    }

    /* ================================================================== */
    /*  Passos novos                                                       */
    /* ================================================================== */

    /** Insere passos no começo do efeito atual. */
    private static function antes(array &$e, array $passos): void
    {
        if ($passos) {
            array_unshift($e['fila'][0]['passos'], ...array_values($passos));
        }
    }

    /** Para numa escolha entre opções com nome (o "ou" dos textos, a letra do Zequinha, o palpite da Estátua). */
    private static function pedirOpcao(array $e, array $p, string $quem, array $rotulos, string $texto): array
    {
        $e = self::pedir($e, $p, $quem, 'opcao', array_keys($rotulos), $texto);
        if ($e['escolha']) {
            $e['escolha']['opcoes'] = array_map('strval', array_keys($rotulos));
            $e['escolha']['rotulos'] = array_map('strval', $rotulos);
        }

        return $e;
    }

    /**
     * Passos que só os roteiros usam. Devolve o estado ou null se o passo não é daqui (segue para executar()).
     */
    private static function executarRoteiro(array $e, array $p): ?array
    {
        $ef = $e['fila'][0];
        $dono = $ef['lado'];
        $fonte = $ef['fonte'];
        $a = $p['a'];
        $esc = $p['escolhido'] ?? null;
        $quem = self::quem($e, $dono);
        $fx = self::emJogo($e, $ef['i'] ?? null); // [lado, zona, k] da fonte em jogo

        switch ($a) {
            case 'se':
                self::antes($e, self::condicao($e, $ef, $p['c']) ? $p['passos'] : ($p['senao'] ?? []));

                return $e;
            case 'vezes':
                $n = self::quantia($e, $ef, $p['n']);
                self::antes($e, $n > 0 ? array_merge(...array_fill(0, $n, $p['passos'])) : []);

                return $e;
            case 'ou':
                if ($esc === null) {
                    return self::pedirOpcao($e, $p, $dono, array_column($p['ops'], 0), "$fonte: escolha um");
                }
                self::antes($e, $p['ops'][(int) $esc][1] ?? []);

                return $e;
            case 'laco': // "quantas vezes quiser": pergunta de novo depois de cada vez
                $custo = (int) ($p['energia'] ?? 0);
                if ($custo > $e['lados'][$dono]['energia'] || ($p['passos'][0]['a'] === 'descartar' && !$e['lados'][$dono]['mao'])) {
                    return $e;
                }
                if ($esc === null) {
                    return self::pedir($e, $p, $dono, 'confirmar', ['sim'], "$fonte: você pode " . $p['texto'] . ($custo ? " (energia: {$e['lados'][$dono]['energia']})" : ''));
                }
                $e['lados'][$dono]['energia'] -= $custo;
                unset($p['escolhido']);
                self::antes($e, array_merge($p['passos'], [$p]));

                return $e;
            case 'cont':
                $k = $p['k'];
                if ($p['onde'] === 'evento_jog' && ($j = $ef['ctx']['lado'] ?? null) !== null) {
                    $e['lados'][$j]['mk'][$k] = (int) ($e['lados'][$j]['mk'][$k] ?? 0) + (int) $p['d'];
                    self::log($e, "$fonte: " . self::quem($e, $j) . " tem {$e['lados'][$j]['mk'][$k]} $k.");
                } elseif ($fx) {
                    $chave = $p['onde'] === 'fonte_evento' ? $k . '@' . ($ef['ctx']['lado'] ?? '') : $k;
                    $e['lados'][$fx[0]][$fx[1]][$fx[2]]['mk'][$chave] = (int) ($e['lados'][$fx[0]][$fx[1]][$fx[2]]['mk'][$chave] ?? 0) + (int) $p['d'];
                }

                return $e;
            case 'fixar': // a carta guarda o jogador escolhido ("o jogador alvo", enquanto ela estiver em campo)
            case 'alvo_jog':
                $lados = self::ladosDo($e, $p['alvo'], $dono);
                if ($lados === null) {
                    $ops = ($p['filtro'] ?? null) === 'inimigo' ? self::inimigos($e, $dono) : self::vivos($e);

                    return self::pedir($e, $p, $dono, 'jogador', array_map(fn ($l) => 'j:' . $l, $ops), "$fonte: escolha o jogador");
                }
                $j = $lados[0] ?? null;
                $e['fila'][0]['jog'] = $j;
                if ($a === 'fixar' && $fx && $j !== null) {
                    $e['lados'][$fx[0]][$fx[1]][$fx[2]]['jog'] = $j;
                    self::log($e, "$fonte: o alvo é " . self::quem($e, $j) . '.');
                }

                return $e;
            case 'atrasar':
                $de = match ($p['de'] ?? 'eu') {
                    'jog' => $ef['jog'] ?? $dono, 'evento' => $ef['ctx']['lado'] ?? $dono, default => $dono,
                };
                $e['atrasos'][] = ['quando' => $p['quando'], 'lado' => $de, 'depois' => $e['turno'], 'dono' => $dono, 'fonte' => $fonte,
                                   'i' => $ef['i'], 'c' => $ef['c'] ?? null, 'viva' => !empty($p['fonte_viva']), 'jog' => $ef['jog'] ?? null,
                                   'passos' => self::fixarRefs($p['passos'], $ef, $de)]
                    + (isset($p['turnos']) ? ['turno_alvo' => $e['turno'] + (int) $p['turnos']] : []);
                self::log($e, isset($p['turnos']) ? "$fonte: daqui a {$p['turnos']} turnos (no turno " . ($e['turno'] + (int) $p['turnos']) . ').'
                    : "$fonte: no " . ($p['quando'] === 'fim' ? 'fim' : 'início') . ' do próximo turno de ' . self::quem($e, $de) . '.');

                return $e;
            case 'pular_compra':
                $e['lados'][$dono]['pular'] = true;
                self::log($e, "$fonte: $quem pula a próxima compra.");

                return $e;
            case 'vencer':
                foreach (self::inimigos($e, $dono) as $l) {
                    $e = self::eliminar($e, $l, 'efeito de ' . $fonte);
                }

                return $e;
            case 'derrotar':
                foreach (self::ladosDo($e, $p['alvo'], $dono) ?? [] as $l) {
                    $e = self::eliminar($e, $l, 'efeito de ' . $fonte);
                }

                return $e;
            case 'scry':
            case 'ordenar':
                return self::olharTopo($e, $p, $dono, $fonte);
            case 'voltar': // a própria carta volta do cemitério (ou da zona de comando) para o campo
                foreach (['cemiterio', 'comando'] as $zona) {
                    foreach ($e['ordem'] as $l) {
                        $k = self::idx($e['lados'][$l][$zona], (int) $ef['i']);
                        if ($k < 0) {
                            continue;
                        }
                        $carta = $e['lados'][$l][$zona][$k];
                        array_splice($e['lados'][$l][$zona], $k, 1);
                        $e = self::entrar($e, $l, $carta);
                        $km = self::idx($e['lados'][$l]['mesa'], (int) $carta['i']);
                        if ($km >= 0) {
                            $m = &$e['lados'][$l]['mesa'][$km];
                            $m['virada'] = !empty($p['virada']);
                            foreach (['atk' => 'atk', 'vida' => 'vida'] as $campo => $de) {
                                $m[$campo] += (int) ($p[$de] ?? 0);
                            }
                            $m['max'] += (int) ($p['vida'] ?? 0);
                            unset($m);
                        }
                        self::log($e, "$fonte voltou para o campo" . (!empty($p['virada']) ? ' virada' : '') . (isset($p['atk']) ? sprintf(' com %+d/%+d', $p['atk'], $p['vida']) : '') . '.');

                        return $e;
                    }
                }

                return $e;
            case 'reviver_mortas': // Aguinha: as Lendas que ela matou voltam para os donos
                foreach ($ef['ctx']['m']['mortas'] ?? [] as [$l, $i]) {
                    $k = self::idx($e['lados'][$l]['cemiterio'], (int) $i);
                    if ($k >= 0 && empty($e['lados'][$l]['fora'])) {
                        $carta = $e['lados'][$l]['cemiterio'][$k];
                        array_splice($e['lados'][$l]['cemiterio'], $k, 1);
                        $e = self::entrar($e, $l, $carta);
                        self::log($e, "$fonte: " . self::nome($e, $carta['c']) . ' voltou para ' . self::quem($e, $l) . '.');
                    }
                }

                return $e;
            case 'posse': // Aporium: passa para o jogador atacado (e sai do ataque)
                $para = $ef['ctx']['lado'] ?? null;
                if (!$fx || $fx[1] !== 'mesa' || $para === null || $para === $fx[0] || !self::cabeNaMesa($e, $para)) {
                    return $e; // (com a mesa do atacado cheia, Aporium fica onde está)
                }
                $m = $e['lados'][$fx[0]]['mesa'][$fx[2]];
                array_splice($e['lados'][$fx[0]]['mesa'], $fx[2], 1);
                $e = self::soltarEquipamentos($e, (int) $m['i']);
                $m['pode'] = false;
                $m['virada'] = false;
                $e['lados'][$para]['mesa'][] = $m;
                if ($e['ataque']) {
                    $e['ataque']['atacantes'] = array_values(array_filter($e['ataque']['atacantes'], fn ($i) => (int) $i !== (int) $m['i']));
                }
                self::log($e, "$fonte agora é de " . self::quem($e, $para) . '.');

                return $e;
            case 'transformar': // Cara-lata: a Lenda que entrou vira um Cara-lata
                $i = $ef['ctx']['i'] ?? null;
                $o = $i !== null ? self::acharLenda($e, (int) $i) : null;
                $c = $ef['c'] ?? null;
                if (!$o || $c === null) {
                    return $e;
                }
                $antes = self::nomeLenda($e, $e['lados'][$o[0]]['mesa'][$o[1]]);
                $nova = self::novaLenda($e, $c, (int) $i);
                $velha = $e['lados'][$o[0]]['mesa'][$o[1]];
                $nova['pode'] = $velha['pode'];
                $nova['virada'] = $velha['virada'];
                if ($velha['c'] === null || !empty($velha['ficha'])) {
                    $nova['ficha'] = true;
                }
                $e['lados'][$o[0]]['mesa'][$o[1]] = $nova;
                self::log($e, "$fonte: $antes virou " . self::nome($e, $c) . '.');

                return $e;
            case 'lutar': // Rivaldo: duas Lendas escolhidas lutam entre si
                return self::lutar($e, $p, $dono, $fonte);
            case 'absorver':
                foreach (self::ladosDo($e, $p['alvo'], $dono) ?? [] as $l) {
                    $um[] = ['a' => 'absorver1', 'resto' => self::quantia($e, $ef, $p['n']), 'alvo' => 'lado:' . $l, 'obrig' => true];
                }
                self::antes($e, $um ?? []);

                return $e;
            case 'absorver1': // Azazyel: quem sofre o dano põe nas próprias Lendas o que quiser, o resto vai nele
                $l = substr($p['alvo'], 5);
                $resto = (int) $p['resto'];
                if ($resto <= 0 || !empty($e['lados'][$l]['fora'])) {
                    return $e;
                }
                if ($esc === null) {
                    $ops = array_merge(array_map(fn ($m) => (int) $m['i'], $e['lados'][$l]['mesa']), ['j:' . $l]);

                    return self::pedir($e, $p, $l, 'qualquer', $ops, "$fonte: $resto de dano em você — escolha uma Lenda sua para segurar parte (ou você mesmo para receber o resto)");
                }
                if (is_string($esc)) {
                    self::perdeVida($e, $l, $resto);
                    self::log($e, "$fonte: $resto de dano em " . self::quem($e, $l) . '.');

                    return $e;
                }
                $k = self::idx($e['lados'][$l]['mesa'], (int) $esc);
                if ($k >= 0) {
                    $poe = min($resto, max(1, (int) $e['lados'][$l]['mesa'][$k]['vida']));
                    self::danoLenda($e, $l, $k, $poe, self::origemDe($e, $ef));
                    self::log($e, "$fonte: " . self::nomeLenda($e, $e['lados'][$l]['mesa'][$k]) . " segurou $poe de dano.");
                    $resto -= $poe;
                }
                self::antes($e, [['a' => 'absorver1', 'resto' => $resto, 'alvo' => 'lado:' . $l, 'obrig' => true]]);

                return $e;
            case 'fundir': // Estripador: todos os estripadores viram um só, com o dobro do ataque somado
                $c = $ef['c'] ?? null;
                $soma = 0;
                foreach (array_reverse($e['lados'][$dono]['mesa'], true) as $k => $m) {
                    if ($m['c'] === $c) {
                        $soma += max(0, (int) $m['atk']);
                        array_splice($e['lados'][$dono]['mesa'], $k, 1);
                        $e = self::saiDaMesa($e, $dono, $m);
                    }
                }
                if ($soma > 0 && $c !== null) {
                    $nova = ['i' => ++$e['seq'], 'ficha' => true, 'atk' => 2 * $soma, 'vida' => 2 * $soma, 'max' => 2 * $soma] + self::novaLenda($e, $c, 0);
                    $e['lados'][$dono]['mesa'][] = $nova;
                    self::sinal($e, 'entra', ['lado' => $dono, 'i' => $nova['i'], 'c' => $c]);
                    self::log($e, "$fonte: os estripadores viraram um só Estripador " . (2 * $soma) . '/' . (2 * $soma) . '.');
                }

                return $e;
            case 'barato': // Irrestrito: uma carta da mão fica mais barata
                if ($esc === null) {
                    return self::pedir($e, $p, $dono, 'mao', array_map(fn ($c) => (int) $c['i'], $e['lados'][$dono]['mao']),
                        "$fonte: escolha uma carta da sua mão para custar -{$p['d']}");
                }
                $k = self::idx($e['lados'][$dono]['mao'], (int) $esc);
                if ($k >= 0) {
                    $e['lados'][$dono]['mao'][$k]['desc'] = (int) ($e['lados'][$dono]['mao'][$k]['desc'] ?? 0) + (int) $p['d'];
                    self::log($e, "$fonte: " . self::nome($e, $e['lados'][$dono]['mao'][$k]['c']) . " custa -{$p['d']}.");
                }

                return $e;
            case 'letra': // Zequinha: feitiços com essa letra no nome não podem ser conjurados
                if ($esc === null) {
                    $letras = range('A', 'Z');

                    return self::pedirOpcao($e, $p, $dono, array_combine($letras, $letras), "$fonte: escolha uma letra");
                }
                if ($fx) {
                    $e['lados'][$fx[0]][$fx[1]][$fx[2]]['letra'] = (string) $esc;
                }
                self::log($e, "$fonte: feitiços com a letra $esc não podem ser conjurados enquanto ele estiver em campo.");

                return $e;
            case 'ver': // Maria: o topo do próprio baralho ou a mão de um oponente, enquanto estiver no campo
                if ($esc === null) {
                    $ops = ['topo' => 'A carta do topo do seu baralho'];
                    foreach (self::inimigos($e, $dono) as $l) {
                        $ops['j:' . $l] = 'A mão de ' . self::quem($e, $l);
                    }

                    return self::pedirOpcao($e, $p, $dono, $ops, "$fonte: o que você quer ver enquanto ela estiver no campo?");
                }
                if ($fx) {
                    $e['lados'][$fx[0]][$fx[1]][$fx[2]]['ver'] = (string) $esc;
                }

                return $e;
            case 'adivinhar': // Estátua Hexomante: o jogador alvo diz o tipo da carta do topo do baralho dele
                $lados = self::ladosDo($e, $p['alvo'], $dono);
                if ($lados === null) {
                    $ops = self::inimigos($e, $dono);
                    if (count($ops) === 1) {
                        $e['fila'][0]['jog'] = $ops[0];
                        $lados = $ops;
                    } else {
                        return self::pedir($e, $p, $dono, 'jogador', array_map(fn ($l) => 'j:' . $l, $ops), "$fonte: escolha o jogador");
                    }
                }
                $l = $lados[0] ?? null;
                if ($l === null || !$e['lados'][$l]['baralho']) {
                    return $e;
                }
                if ($esc === null) {
                    return self::pedirOpcao($e, ['alvo' => 'lado:' . $l, 'obrig' => true] + $p, $l, ['lenda' => 'Lenda', 'feitico' => 'Feitiço'],
                        "$fonte: qual é o tipo da carta do topo do seu baralho?");
                }
                $topo = $e['lados'][$l]['baralho'][0];
                $tipo = ($e['cartas'][$topo['c']]['tipo'] ?? '') === 'lenda' ? 'lenda' : 'feitico';
                self::log($e, "$fonte: " . self::quem($e, $l) . " disse " . ($esc === 'lenda' ? 'Lenda' : 'Feitiço') . '; o topo era ' . self::nome($e, $topo['c']) . '.');
                if ($tipo !== $esc) {
                    self::antes($e, $p['errou'] ?? []);
                }

                return $e;
            case 'revelar_ate': // Transformação: revela até achar o subtipo, invoca sem pagar e embaralha
                $b = $e['lados'][$dono]['baralho'];
                $achou = null;
                $vistas = [];
                foreach ($b as $k => $c) {
                    if (self::temSub($e, $c['c'], $p['sub'])) {
                        $achou = $c;
                        array_splice($b, $k, 1);
                        break;
                    }
                    $vistas[] = self::nome($e, $c['c']);
                }
                shuffle($b);
                $e['lados'][$dono]['baralho'] = array_values($b);
                self::log($e, "$fonte: revelou " . ($vistas ? implode(', ', $vistas) . ($achou ? ' e ' : '') : '') . ($achou ? self::nome($e, $achou['c']) : ' o baralho todo sem achar') . '.');
                if ($achou) {
                    $e = self::entrar($e, $dono, $achou);
                    if (($e['cartas'][$achou['c']]['tipo'] ?? '') === 'lenda') {
                        self::sinal($e, 'entra', ['lado' => $dono, 'i' => (int) $achou['i'], 'c' => $achou['c']]);
                    }
                }

                return $e;
            case 'ativar_topo': // Makatrakiano Y: os feitiços entre as N do topo são conjurados de graça
                $n = self::quantia($e, $ef, $p['n']);
                $topo = array_slice($e['lados'][$dono]['baralho'], 0, $n);
                $nomes = [];
                foreach ($topo as $c) {
                    if (($e['cartas'][$c['c']]['tipo'] ?? '') !== 'feitico') {
                        continue;
                    }
                    array_splice($e['lados'][$dono]['baralho'], self::idx($e['lados'][$dono]['baralho'], (int) $c['i']), 1);
                    self::cem($e, $dono, $c);
                    $e = self::enfileirar($e, $dono, $c['c'], (int) $c['i'], 'conjurar');
                    self::sinal($e, 'feitico', ['lado' => $dono, 'i' => (int) $c['i'], 'c' => $c['c']]);
                    $nomes[] = self::nome($e, $c['c']);
                }
                self::log($e, "$fonte: " . ($nomes ? 'conjurou de graça ' . implode(', ', $nomes) : "nenhum feitiço entre as $n do topo") . '.');

                return $e;
            case 'ativar_cem': // O Dividido: um feitiço do cemitério é conjurado de graça (e fica lá)
                if ($esc === null) {
                    $ops = array_values(array_map(fn ($c) => (int) $c['i'], array_filter($e['lados'][$dono]['cemiterio'],
                        fn ($c) => ($e['cartas'][$c['c']]['tipo'] ?? '') === 'feitico' && !empty($e['cartas'][$c['c']]['hab']))));

                    return self::pedir($e, $p, $dono, 'cemiterio', $ops, "$fonte: escolha um feitiço do seu cemitério para ativar de graça");
                }
                $k = self::idx($e['lados'][$dono]['cemiterio'], (int) $esc);
                if ($k >= 0) {
                    $c = $e['lados'][$dono]['cemiterio'][$k];
                    $e = self::enfileirar($e, $dono, $c['c'], (int) $c['i'], 'conjurar');
                    self::sinal($e, 'feitico', ['lado' => $dono, 'i' => (int) $c['i'], 'c' => $c['c']]);
                    self::log($e, "$fonte: ativou " . self::nome($e, $c['c']) . ' do cemitério.');
                }

                return $e;
            case 'invocar_comprada': // Lucas: a Lenda comprada entra sem pagar
                $ci = $ef['comprada'] ?? null;
                $k = $ci !== null ? self::idx($e['lados'][$dono]['mao'], (int) $ci) : -1;
                if ($k >= 0) {
                    $c = $e['lados'][$dono]['mao'][$k];
                    array_splice($e['lados'][$dono]['mao'], $k, 1);
                    $e = self::entrar($e, $dono, $c);
                    self::sinal($e, 'entra', ['lado' => $dono, 'i' => (int) $c['i'], 'c' => $c['c']]);
                    self::log($e, "$fonte: $quem invocou " . self::nome($e, $c['c']) . ' sem pagar.');
                }

                return $e;
            case 'equipar_compradas': // Abel: as propriedades compradas entram equipadas nele
                foreach ($ef['compradas'] ?? [] as $ci) {
                    $k = self::idx($e['lados'][$dono]['mao'], (int) $ci);
                    if ($k < 0 || !self::temSub($e, $e['lados'][$dono]['mao'][$k]['c'], $p['sub']) || !$fx || $fx[1] !== 'mesa') {
                        continue;
                    }
                    $c = $e['lados'][$dono]['mao'][$k];
                    array_splice($e['lados'][$dono]['mao'], $k, 1);
                    $e['lados'][$dono]['feiticos'][] = ['i' => $c['i'], 'c' => $c['c'], 'cont' => 0, 'nota' => ''];
                    $e = self::vincular($e, $dono, (int) $c['i'], (int) $ef['i']);
                }

                return $e;
            case 'serrar': // Serrotinho vira equipamento de outra Lenda sua
                if (!$fx || $fx[1] !== 'mesa') {
                    return $e;
                }
                if (!str_starts_with((string) $p['alvo'], 'inst:')) {
                    $ops = array_values(array_filter(array_map(fn ($m) => (int) $m['i'], $e['lados'][$dono]['mesa']), fn ($i) => $i !== (int) $ef['i']));

                    return self::pedir($e, $p, $dono, 'lenda', $ops, "$fonte: escolha a Lenda sua que vai receber o Serrotinho");
                }
                $alvoI = (int) substr($p['alvo'], 5);
                $s = $e['lados'][$dono]['mesa'][$fx[2]];
                array_splice($e['lados'][$dono]['mesa'], $fx[2], 1);
                $e = self::soltarEquipamentos($e, (int) $s['i']);
                $e['lados'][$dono]['feiticos'][] = ['i' => $s['i'], 'c' => $s['c'], 'cont' => 0, 'nota' => '', 'serrote' => true];
                $e = self::vincular($e, $dono, (int) $s['i'], $alvoI, max(0, (int) $s['atk']));

                return $e;
            case 'reembaralhar': // Calamidade: cada um embaralha a mão no baralho e compra N
                foreach (self::vivos($e) as $l) {
                    $b = array_merge($e['lados'][$l]['baralho'], array_map(fn ($c) => ['i' => $c['i'], 'c' => $c['c']], $e['lados'][$l]['mao']));
                    shuffle($b);
                    $e['lados'][$l]['baralho'] = array_values($b);
                    $e['lados'][$l]['mao'] = [];
                    for ($k = 0; $k < (int) $p['n']; $k++) {
                        $e = self::comprarUma($e, $l);
                    }
                }
                self::log($e, "$fonte: todos embaralharam a mão no baralho e compraram {$p['n']}.");

                return $e;
            case 'destruir_campo': // Selo do anticristo: um encantamento ou equipamento em campo vai para o cemitério
                $ops = [];
                foreach (self::vivos($e) as $l) {
                    foreach ($e['lados'][$l]['feiticos'] as $f) {
                        if (in_array($e['cartas'][$f['c']]['tipo'] ?? '', ['encanto', 'equipo'], true) && !self::encantoFixo($e, $l, $f)) {
                            $ops[(string) $f['i']] = self::nome($e, $f['c']) . ' (' . self::quem($e, $l) . ')';
                        }
                    }
                }
                if ($esc === null) {
                    return self::pedirOpcao($e, $p, $dono, $ops, "$fonte: escolha o encantamento ou equipamento a destruir");
                }
                if (($o = self::emJogo($e, (int) $esc)) && $o[1] === 'feiticos'
                    && !self::encantoFixo($e, $o[0], $e['lados'][$o[0]]['feiticos'][$o[2]])) {
                    $e = self::desequipar($e, $o[0], (int) $esc);
                    $f = $e['lados'][$o[0]]['feiticos'][$o[2]];
                    array_splice($e['lados'][$o[0]]['feiticos'], $o[2], 1);
                    self::cem($e, $o[0], $f);
                    self::log($e, "$fonte destruiu " . self::nome($e, $f['c']) . '.');
                }

                return $e;
            case 'clonar': // Clonagem: uma cópia da Lenda escolhida entra do seu lado (ficha)
            case 'demonizar': // Demonização: a Lenda escolhida vira um dos Kuros do seu campo
                if (!str_starts_with((string) $p['alvo'], 'inst:')) {
                    return self::pedir($e, $p, $dono, 'lenda', self::lendasEscolhiveis($e, $dono, 'qualquer'), "$fonte: escolha a Lenda");
                }
                $o = self::acharLenda($e, (int) substr($p['alvo'], 5));
                if (!$o) {
                    return $e;
                }
                $m = $e['lados'][$o[0]]['mesa'][$o[1]];
                if ($a === 'clonar') {
                    if (!self::cabeNaMesa($e, $dono)) {
                        return $e;
                    }
                    $nova = ['i' => ++$e['seq'], 'ficha' => true, 'pode' => false, 'virada' => false, 'est' => null, 'fl' => [], 'st' => [], 'mk' => []] + $m;
                    unset($nova['est'], $nova['tmp'], $nova['combateu'], $nova['usou']);
                    $e['lados'][$dono]['mesa'][] = $nova;
                    self::sinal($e, 'entra', ['lado' => $dono, 'i' => $nova['i'], 'c' => $m['c']]);
                    self::log($e, "$fonte: " . self::quem($e, $dono) . ' clonou ' . self::nomeLenda($e, $m) . '.');

                    return $e;
                }
                if ($esc === null) { // qual Kuro do seu campo ela vira
                    $kuros = [];
                    foreach ($e['lados'][$dono]['mesa'] as $k) {
                        if ($k['c'] !== null && self::temSub($e, $k, 'kuro') && (int) $k['i'] !== (int) $m['i']) {
                            $kuros[(string) $k['c']] = self::nome($e, $k['c']);
                        }
                    }
                    if (!$kuros) {
                        self::log($e, "$fonte: você não tem Kuro no campo.");

                        return $e;
                    }

                    return self::pedirOpcao($e, $p, $dono, $kuros, "$fonte: em qual Kuro do seu campo " . self::nomeLenda($e, $m) . ' se transforma?');
                }
                $nova = self::novaLenda($e, (string) $esc, (int) $m['i']);
                $nova['pode'] = $m['pode'];
                $nova['virada'] = $m['virada'];
                $e['lados'][$o[0]]['mesa'][$o[1]] = $nova;
                self::log($e, "$fonte: " . self::nomeLenda($e, $m) . ' virou ' . self::nome($e, (string) $esc) . '.');

                return $e;
            case 'trocar_forma': // Transformação de maldição: troca uma forma/propriedade em campo por outra da mão
                if (!isset($p['alvoF'])) {
                    $ops = [];
                    foreach (self::vivos($e) as $l) {
                        foreach ($e['lados'][$l]['feiticos'] as $f) {
                            if (self::temSub($e, $f['c'], ['forma', 'propriedade']) && !self::encantoFixo($e, $l, $f)) {
                                $ops[(string) $f['i']] = self::nome($e, $f['c']) . ' (' . self::quem($e, $l) . ')';
                            }
                        }
                    }
                    if ($esc === null) {
                        return self::pedirOpcao($e, $p, $dono, $ops, "$fonte: escolha a forma ou propriedade em campo");
                    }
                    $o = self::emJogo($e, (int) $esc);
                    if (!$o) {
                        return $e;
                    }
                    $sub = self::temSub($e, $e['lados'][$o[0]]['feiticos'][$o[2]]['c'], 'forma') ? 'forma' : 'propriedade';
                    $ops = array_values(array_map(fn ($c) => (int) $c['i'], array_filter($e['lados'][$dono]['mao'], fn ($c) => self::temSub($e, $c['c'], $sub))));

                    return self::pedir($e, ['alvoF' => (int) $esc, 'escolhido' => null] + $p, $dono, 'mao', $ops, "$fonte: escolha a carta da sua mão que entra no lugar");
                }
                $o = self::emJogo($e, (int) $p['alvoF']);
                $k = self::idx($e['lados'][$dono]['mao'], (int) $esc);
                if (!$o || $k < 0) {
                    return $e;
                }
                $e = self::desequipar($e, $o[0], (int) $p['alvoF']);
                $velha = $e['lados'][$o[0]]['feiticos'][$o[2]];
                array_splice($e['lados'][$o[0]]['feiticos'], $o[2], 1);
                self::cem($e, $o[0], $velha);
                $nova = $e['lados'][$dono]['mao'][$k];
                array_splice($e['lados'][$dono]['mao'], $k, 1);
                $tipo = $e['cartas'][$nova['c']]['tipo'] ?? 'feitico';
                if (in_array($tipo, ['encanto', 'equipo'], true)) {
                    $e['lados'][$dono]['feiticos'][] = ['i' => $nova['i'], 'c' => $nova['c'], 'cont' => 0, 'nota' => ''];
                } else {
                    self::cem($e, $dono, $nova);
                }
                $e = self::enfileirar($e, $dono, $nova['c'], (int) $nova['i'], 'conjurar');
                self::log($e, "$fonte: " . self::nome($e, $velha['c']) . ' virou ' . self::nome($e, $nova['c']) . '.');

                return $e;
            case 'intocavel': // Redoma de cidade: até o fim do seu próximo turno, não ataca nem é atacado
                $e['lados'][$dono]['intocavel'] = true;
                $e['atrasos'][] = ['quando' => 'fim', 'lado' => $dono, 'depois' => $e['turno'], 'dono' => $dono, 'fonte' => $fonte, 'i' => null,
                                   'passos' => [['a' => 'fim_intocavel', 'alvo' => 'eu']]];
                self::log($e, "$fonte: $quem não pode atacar nem ser atacado até o fim do próximo turno.");

                return $e;
            case 'fim_intocavel':
                unset($e['lados'][$dono]['intocavel']);

                return $e;
            case 'manter_pagando': // "pague N em aura, caso contrário, perca essa carta"
                $n = (int) $p['energia'];
                if ($esc === null && $e['lados'][$dono]['energia'] >= $n) {
                    return self::pedirOpcao($e, $p + ['obrig' => true], $dono, ['pagar' => "Pagar $n de energia e manter", 'perder' => 'Perder a carta'], "$fonte: pague $n ou perca a carta");
                }
                if ($esc === 'pagar') {
                    $e['lados'][$dono]['energia'] -= $n;
                    self::log($e, "$fonte: $quem pagou $n para manter.");

                    return $e;
                }
                if ($fx) {
                    $e = self::desequipar($e, $fx[0], (int) $ef['i']);
                    $c = $e['lados'][$fx[0]][$fx[1]][$fx[2]];
                    array_splice($e['lados'][$fx[0]][$fx[1]], $fx[2], 1);
                    self::cem($e, $fx[0], $c);
                    self::log($e, "$fonte foi para o cemitério (não pagou).");
                }

                return $e;
            case 'reviver_maldito': // Maldito: volta com +1/+1 a mais a cada vez e uma palavra-chave
                $chave = 'maldito@' . (int) $ef['i'];
                $vezes = (int) ($e['lados'][$dono]['mk'][$chave] ?? 0) + 1;
                $e['lados'][$dono]['mk'][$chave] = $vezes;
                self::antes($e, [['a' => 'voltar', 'atk' => $vezes, 'vida' => $vezes], ['a' => 'palavra', 'pal' => '?', 'temp' => false, 'alvo' => 'fonte', 'filtro' => null]]);

                return $e;
            case 'desafiar': // Desafiador: quem ataca escolhe uma Lenda do atacado, que é obrigada a bloqueá-la
                if (($e['fase'] ?? null) !== 'defesa' || !$e['ataque']) {
                    return $e;
                }
                $defensor = $e['ataque']['defensor'];
                $ka = self::idx($e['lados'][$dono]['mesa'], (int) $p['atk']);
                if ($ka < 0) {
                    return $e;
                }
                $a = $e['lados'][$dono]['mesa'][$ka];
                $forcados = (array) ($e['ataque']['forcados'] ?? []);
                if (!str_starts_with((string) $p['alvo'], 'inst:')) {
                    $ops = [];
                    foreach ($e['lados'][$defensor]['mesa'] as $b) {
                        if (empty($b['virada']) && !isset($forcados[(int) $b['i']]) && !self::naoBloqueia($e, $a, $b)) {
                            $ops[] = (int) $b['i'];
                        }
                    }

                    return self::pedir($e, $p, $dono, 'lenda', $ops, "$fonte (Desafiador): escolha a Lenda de " . self::quem($e, $defensor) . ' que é obrigada a bloqueá-la');
                }
                $bi = (int) substr($p['alvo'], 5);
                $kb = self::idx($e['lados'][$defensor]['mesa'], $bi);
                if ($kb < 0) {
                    return $e;
                }
                $bloqueios = (array) $e['ataque']['bloqueios'];
                $bloqueios[$bi] = (int) $p['atk'];
                $forcados[$bi] = (int) $p['atk'];
                $e['ataque']['bloqueios'] = (object) $bloqueios;
                $e['ataque']['forcados'] = (object) $forcados;
                self::log($e, "$fonte desafiou " . self::nomeLenda($e, $e['lados'][$defensor]['mesa'][$kb]) . ', que é obrigada a bloqueá-la.');

                return $e;
            case 'amir_palavra': // Amir: cada tranqueira que entra dá uma palavra-chave, escolhida pelo dono de cada Amir
                $t = (int) ($p['t'] ?? ($ef['ctx']['i'] ?? 0));
                $to = self::acharLenda($e, $t);
                if (!$to || GladioTexto::normalizar(self::nomeLenda($e, $e['lados'][$to[0]]['mesa'][$to[1]])) !== 'tranqueira') {
                    return $e;
                }
                if (!isset($p['amir'])) { // uma escolha para cada Amir em campo que ainda não ganhou por esta tranqueira
                    foreach (self::vivos($e) as $l) {
                        foreach ($e['lados'][$l]['mesa'] as $x) {
                            if (str_contains(GladioTexto::normalizar(self::nomeLenda($e, $x)), 'amir') && !isset($x['amir_pal'][$t])) {
                                $e['fila'][] = ['lado' => $l, 'fonte' => self::nomeLenda($e, $x), 'i' => (int) $x['i'], 'c' => $x['c'], 'jog' => null, 'ctx' => null,
                                                'ult' => null, 'passos' => [['a' => 'amir_palavra', 'amir' => (int) $x['i'], 't' => $t]]];
                            }
                        }
                    }

                    return $e;
                }
                $ao = self::acharLenda($e, (int) $p['amir']);
                if (!$ao || isset($e['lados'][$ao[0]]['mesa'][$ao[1]]['amir_pal'][$t])) {
                    return $e;
                }
                $amir = $e['lados'][$ao[0]]['mesa'][$ao[1]];
                if (($p['escolhido'] ?? null) === null) {
                    $ops = array_values(array_diff(array_keys(self::PALAVRAS), $amir['pal'] ?? []));

                    return self::pedir($e, $p + ['obrig' => true], $ao[0], 'palavra', $ops, self::nomeLenda($e, $amir) . ': escolha a palavra-chave que ele ganha por esta tranqueira');
                }
                $e['lados'][$ao[0]]['mesa'][$ao[1]]['amir_pal'][$t] = (string) $p['escolhido'];
                self::log($e, self::nomeLenda($e, $amir) . ' ganhou ' . (self::PALAVRAS[$p['escolhido']] ?? $p['escolhido']) . ' pela tranqueira.');

                return $e;
            case 'organizar': // Organizador do torneio: soma o ataque dele a uma das duas Lendas da batalha
                if (!str_starts_with((string) $p['alvo'], 'inst:')) {
                    $ops = array_values(array_filter($p['par'], fn ($i) => self::acharLenda($e, (int) $i) !== null));

                    return self::pedir($e, $p, $dono, 'lenda', $ops, "$fonte: escolha qual Lenda da batalha recebe o ataque dele");
                }
                $o = self::acharLenda($e, (int) substr($p['alvo'], 5));
                $org = $fx && $fx[1] === 'mesa' ? max(0, (int) $e['lados'][$fx[0]]['mesa'][$fx[2]]['atk']) : 0;
                if ($o && $org > 0) {
                    $m = &$e['lados'][$o[0]]['mesa'][$o[1]];
                    $m['atk'] += $org;
                    $m['tmp']['atk'] = ($m['tmp']['atk'] ?? 0) + $org;
                    $nome = self::nomeLenda($e, $m);
                    unset($m);
                    self::log($e, "$fonte: +$org de ataque em $nome nesta batalha.");
                }

                return $e;
        }

        return null;
    }

    /**
     * Cria N fichas para $l: com nome e ataque/vida do texto, ou cópias da carta que está resolvendo (copia: Estripador,
     * Drixopata, Monolisco). Extras: est (efeito contínuo da ficha), tag, atacando (entra no ataque), marca (o efeito
     * lembra quais criou), pal_ctx (palavras-chave da Lenda do evento: Caveirão).
     */
    private static function criarFichas(array $e, string $l, array $p, int $n): array
    {
        $ef = $e['fila'][0];
        $c = !empty($p['copia']) ? ($ef['c'] ?? null) : null;
        $sobra = max(0, self::MESA_MAX - count($e['lados'][$l]['mesa']));
        if ($n > $sobra) { // mesa cheia: as fichas que não cabem não são criadas
            self::log($e, "{$ef['fonte']}: a mesa de " . self::quem($e, $l) . ' chegou ao máximo de ' . self::MESA_MAX . ' Lendas' . ($sobra ? "; só $sobra ficha(s) couberam." : '.'));
            $n = $sobra;
        }
        if ($n <= 0) {
            return $e;
        }
        for ($k = 0; $k < $n; $k++) {
            if (!empty($e['glob']['ficha_moeda']) && !self::coroa()) { // "ele só invoca se sair cara"
                self::log($e, "{$ef['fonte']}: a moeda não deixou a ficha entrar.");
                continue;
            }
            if ($c !== null) {
                $m = ['ficha' => true] + self::novaLenda($e, $c, ++$e['seq']);
                if (isset($p['so'])) {
                    $m['so'] = (int) $p['so'];
                }
            } else {
                $pal = !empty($p['pal_ctx']) ? ($ef['ctx']['m']['pal'] ?? []) : ($p['pal'] ?? []);
                $pal = array_values(array_intersect($pal, array_keys(self::PALAVRAS)));
                $m = ['i' => ++$e['seq'], 'c' => null, 'nome' => $p['nome'] ?? 'Ficha', 'atk' => 0, 'vida' => 1, 'max' => 1,
                      'pode' => in_array('impeto', $pal, true), 'virada' => false, 'pal' => $pal, 'cont' => 0, 'nota' => ''];
            }
            if (array_key_exists('atk', $p)) {
                $m['atk'] = self::quantia($e, $ef, $p['atk']);
            }
            if (array_key_exists('vida', $p)) {
                $m['vida'] = $m['max'] = max(1, self::quantia($e, $ef, $p['vida']));
            }
            foreach (['est' => 'estp', 'tag' => 'tag'] as $de => $para) {
                if (isset($p[$de])) {
                    $m[$para] = $p[$de];
                }
            }
            if (!empty($p['atacando']) && $e['ataque'] && $e['vez'] === $l) { // Amir: as lanças já entram atacando
                $m['virada'] = true;
                $e['ataque']['atacantes'][] = (int) $m['i'];
            }
            $e['lados'][$l]['mesa'][] = $m;
            if (!empty($p['marca'])) {
                $e['fila'][0]['criadas'][] = (int) $m['i'];
            }
            self::sinal($e, 'entra', ['lado' => $l, 'i' => (int) $m['i'], 'c' => $c, 'ficha' => true]);
            self::sinal($e, 'ficha', ['lado' => $l, 'i' => (int) $m['i'], 'fonte_i' => (int) ($ef['i'] ?? 0)]);
        }
        $ultima = end($e['lados'][$l]['mesa']);
        if (!$ultima) {
            return $e;
        }
        self::log($e, "{$ef['fonte']}: " . self::quem($e, $l) . " criou $n ficha" . ($n > 1 ? 's' : '') . ' ' . self::nomeLenda($e, $ultima)
            . " {$ultima['atk']}/{$ultima['vida']}" . (!empty($p['atacando']) ? ' atacando' : '') . '.');

        return $e;
    }

    /** A carta $c achada no baralho de $l vai para a mão, entra em jogo sem pagar, vai para o cemitério ou afeta as fichas. */
    private static function buscada(array $e, string $l, array $c, string $destino, string $fonte): array
    {
        $nome = self::nome($e, $c['c']);
        if ($destino === 'mesa') {
            $e = self::entrar($e, $l, $c);
            if (($e['cartas'][$c['c']]['tipo'] ?? '') === 'lenda') {
                self::sinal($e, 'entra', ['lado' => $l, 'i' => (int) $c['i'], 'c' => $c['c']]);
            }
            self::log($e, "$fonte: " . self::quem($e, $l) . " invocou $nome do baralho sem pagar.");
        } elseif ($destino === 'cemiterio') {
            self::cem($e, $l, $c);
            self::log($e, "$fonte: $nome foi do baralho para o cemitério.");
        } elseif ($destino === 'fichas') { // Fisiomancia animal: a propriedade afeta as fichas criadas pelo efeito
            $b = self::bonusDe($e, $c['c']);
            $mods = $e['cartas'][$c['c']]['mods'] ?? [];
            foreach ($e['fila'][0]['criadas'] ?? [] as $i) {
                $o = self::acharLenda($e, (int) $i);
                if (!$o) {
                    continue;
                }
                $m = &$e['lados'][$o[0]]['mesa'][$o[1]];
                $m['atk'] = max(0, $m['atk'] + $b['atk'] + (!empty($mods['quente']) ? 1 : 0));
                $m['vida'] += $b['vida'];
                $m['max'] = max(1, $m['max'] + $b['vida']);
                $m['pal'] = array_values(array_unique(array_merge($m['pal'], array_intersect($b['pal'], array_keys(self::PALAVRAS)))));
                $m['regen'] = (int) ($m['regen'] ?? 0) + (int) ($mods['regen'] ?? 0);
                if (!empty($mods['fl'])) {
                    $m['estp'] = array_merge($m['estp'] ?? [], [['q' => 'fonte', 'fl' => $mods['fl']]]);
                }
                unset($m);
            }
            self::cem($e, $l, $c);
            self::log($e, "$fonte: $nome afetou as fichas.");
        } else {
            $e['lados'][$l]['mao'][] = $c;
            self::log($e, "$fonte: " . self::quem($e, $l) . ' buscou uma carta no baralho.');
        }

        return $e;
    }

    /** Scry (olhar N do topo e mandar as que quiser para o fundo) e ordenar (olhar N e escolher a ordem). */
    private static function olharTopo(array $e, array $p, string $dono, string $fonte): array
    {
        $esc = $p['escolhido'] ?? null;
        if (!isset($p['vistas'])) {
            $n = self::quantia($e, $e['fila'][0], $p['n']);
            $p['vistas'] = array_map(fn ($c) => (int) $c['i'], array_slice($e['lados'][$dono]['baralho'], 0, $n));
            $p['ja'] = [];
            if ($p['a'] === 'scry') {
                self::sinal($e, 'scry', ['lado' => $dono, 'i' => null]);
                self::log($e, "$fonte: " . self::quem($e, $dono) . ' olhou ' . count($p['vistas']) . ' carta(s) do topo (scry).');
            }
        }
        $b = &$e['lados'][$dono]['baralho'];
        if ($esc !== null) {
            $k = self::idx($b, (int) $esc);
            if ($k >= 0) {
                $c = $b[$k];
                array_splice($b, $k, 1);
                if ($p['a'] === 'scry') { // vai para o fundo
                    $b[] = $c;
                } else { // ordenar: a escolhida fica logo abaixo das que já foram postas por cima
                    array_splice($b, count($p['ja']), 0, [$c]);
                    $p['ja'][] = (int) $esc;
                }
            }
            $p['vistas'] = array_values(array_diff($p['vistas'], [(int) $esc]));
            unset($p['escolhido']);
        }
        unset($b);
        if (count($p['vistas']) <= ($p['a'] === 'ordenar' ? 1 : 0)) {
            return $e;
        }
        $e = self::pedir($e, $p, $dono, 'baralho', $p['vistas'], $p['a'] === 'scry'
            ? "$fonte: escolha uma carta para mandar ao fundo do baralho (Pular mantém as outras no topo)"
            : "$fonte: escolha a " . (count($p['ja']) + 1) . 'ª carta do topo');
        if ($e['escolha']) {
            $e['escolha']['rotulo'] = $p['a'] === 'scry' ? 'Mandar ao fundo' : 'Pôr em cima';
        }

        return $e;
    }

    /** Rivaldo: escolhe duas Lendas e elas trocam golpes. */
    private static function lutar(array $e, array $p, string $dono, string $fonte): array
    {
        $alvo = (string) ($p['alvo'] ?? '');
        if (!str_starts_with($alvo, 'inst:')) {
            $ops = self::lendasEscolhiveis($e, $dono, 'qualquer');
            if (isset($p['primeira'])) {
                $ops = array_values(array_diff($ops, [(int) $p['primeira']]));
            }

            return self::pedir($e, $p, $dono, 'lenda', $ops, "$fonte: escolha a " . (isset($p['primeira']) ? 'segunda' : 'primeira') . ' Lenda da luta');
        }
        $i = (int) substr($alvo, 5);
        if (!isset($p['primeira'])) {
            return self::lutar($e, ['a' => 'lutar', 'primeira' => $i, 'alvo' => 'lenda'], $dono, $fonte);
        }
        $oa = self::acharLenda($e, (int) $p['primeira']);
        $ob = self::acharLenda($e, $i);
        if (!$oa || !$ob) {
            return $e;
        }
        $ma = $e['lados'][$oa[0]]['mesa'][$oa[1]];
        $mb = $e['lados'][$ob[0]]['mesa'][$ob[1]];
        $da = self::danoLenda($e, $ob[0], $ob[1], max(0, (int) $ma['atk']), ['tipo' => 'combate', 'custo' => (int) ($e['cartas'][$ma['c']]['custo'] ?? 0)]);
        $db = self::danoLenda($e, $oa[0], $oa[1], max(0, (int) $mb['atk']), ['tipo' => 'combate', 'custo' => (int) ($e['cartas'][$mb['c']]['custo'] ?? 0)]);
        if ($da > 0 && self::tem($ma, 'toque')) {
            $e['lados'][$ob[0]]['mesa'][$ob[1]]['morto'] = true;
        }
        if ($db > 0 && self::tem($mb, 'toque')) {
            $e['lados'][$oa[0]]['mesa'][$oa[1]]['morto'] = true;
        }
        self::log($e, "$fonte: " . self::nomeLenda($e, $ma) . ' e ' . self::nomeLenda($e, $mb) . " lutaram ($da e $db de dano).");

        return $e;
    }
}
