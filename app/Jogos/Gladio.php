<?php

namespace App\Jogos;

/**
 * Gladio: duelo de cartas criadas pelo mestre, com regras parecidas com Magic: The Gathering e apostas.
 * De 2 a 6 jogadores, todos contra todos, em roda; o último de pé vence e leva o pote.
 *
 * - A vida de cada jogador é o Dinheiro apostado (amistoso, sem aposta: 30 de vida). Toda vida perdida vai para o
 *   pote no centro da mesa (pote = total apostado − vida que ainda resta a quem está de pé).
 * - Cada um joga com o próprio deck (ou 20 cartas sorteadas do catálogo) e começa com 5 cartas na mão.
 * - Turno: a energia máxima de quem joga sobe 1 (até 10) e recarrega, as Lendas dele ficam prontas e ele compra 1
 *   (quem começa não compra no primeiro turno). Sem cartas para comprar: dano de fadiga (1, 2, 3…).
 * - Lenda: entra na mesa e só ataca a partir do próximo turno de quem a controla (com Ímpeto, ataca já).
 * - Feitiço: o efeito é o da carta (feitiços antigos) ou o que quem jogou escolheu conforme a descrição. Depois de
 *   conjurado ele fica no campo até o dono mandá-lo para o cemitério.
 * - Combate: quem tem a vez escolhe um adversário e ataca com uma ou mais Lendas prontas. Só o atacado defende: ele
 *   pode jogar Lendas da mão (pagando com a energia que sobrou) e escolher bloqueadores. Quem atacou fica "virada"
 *   e só volta a bloquear no próximo turno de quem a controla.
 * - Shots: qualquer jogador toma shots a qualquer momento (quando uma carta mandar); no 7º está fora.
 * - Quem fica sem vida, toma o 7º shot ou desiste sai da partida (as cartas dele na mesa vão para o cemitério).
 * - Palavras-chave (PALAVRAS e as dicas da tela): Sobrepujar, Ímpeto, Voar, Alcance, Toque mortal, Vínculo com a vida,
 *   Iniciativa, Golpe duplo, Vigilância, Defensor, Ameaça, Indestrutível e Hexo-Imune. Como aqui o dano nas Lendas
 *   não some no fim do turno, Indestrutível segura a vida em 1.
 * - Ferramentas manuais (efeitos escritos nas cartas), todas no registro da partida:
 *   no próprio turno: comprar, embaralhar, reordenar o baralho, recuperar do cemitério, jogar sem pagar, criar ficha,
 *   copiar Lenda e sacrificar; a qualquer momento: descartar, revelar cartas da mão, ajustar ataque/vida de qualquer
 *   Lenda, contadores e anotações em qualquer carta da mesa, mandar o próprio feitiço ao cemitério e tomar shots.
 *
 * Estado: { lados: { a…f }, ordem [lados em roda], nomes, vez, turno, fase ('principal' | 'defesa'), ataque,
 *           cartas, seq, log, fim, total (soma da vida inicial) }
 * Lado: { vida, max, energia, energiaMax, mao[], baralho[], mesa[], feiticos[], cemiterio[], fadiga, shots, fora }
 * Carta na mão: { i, c, rev? (revelada) }; no baralho/cemitério: { i, c }; feitiço no campo: { i, c, cont, nota }.
 * Lenda na mesa: { i, c (null na ficha), nome? (ficha), atk, vida, max, pode, virada, pal[], cont, nota }.
 * Ataque em andamento: { atacantes: [i…], defensor: lado, bloqueios: { bloqueador i: atacante i } }.
 */
class Gladio
{
    public const TIPOS = ['lenda', 'feitico'];
    public const EFEITOS = ['dano', 'dano_todos', 'cura', 'comprar', 'fortalecer', 'energia'];
    /** Palavras-chave (chave curta => nome). As dicas com o efeito de cada uma ficam em public/js/jogos-comum.js. */
    public const PALAVRAS = [
        'sobrepujar'    => 'Sobrepujar',
        'impeto'        => 'Ímpeto',
        'voar'          => 'Voar',
        'alcance'       => 'Alcance',
        'toque'         => 'Toque mortal',
        'vinculo'       => 'Vínculo com a vida',
        'iniciativa'    => 'Iniciativa',
        'duplo'         => 'Golpe duplo',
        'vigilancia'    => 'Vigilância',
        'defensor'      => 'Defensor',
        'ameaca'        => 'Ameaça',
        'indestrutivel' => 'Indestrutível',
        'resistencia'   => 'Hexo-Imune',
    ];
    public const LADOS = ['a', 'b', 'c', 'd', 'e', 'f'];
    public const MAX_JOGADORES = 6;
    public const SHOTS_MAX = 7;
    public const BARALHO = 20;      // deck sorteado (sem deck montado)
    public const MAO_INICIAL = 5;
    public const MAO_MAX = 10;
    public const MESA_MAX = 7;
    public const ENERGIA_MAX = 10;
    public const VIDA_AMISTOSO = 30;

    /** Quais efeitos precisam de alvo e de que tipo. */
    public const ALVOS = [
        'dano'       => 'inimigo',  // Lenda inimiga ou um adversário
        'cura'       => 'aliado',   // Lenda sua ou você mesmo
        'fortalecer' => 'lenda',    // Lenda sua
    ];

    /* ================================================================== */
    /*  Início e estado                                                    */
    /* ================================================================== */

    /**
     * Monta a partida. $cartas: catálogo [id => carta]. $decks: [lado => [ids…] | null] na ordem da roda
     * (null: 20 sorteadas); a quantidade de jogadores é a de decks (mínimo 2). $nomes: [lado => nome].
     * $embaralhar recebe e devolve um array (nos testes dá para passar uma função sem sorte).
     */
    public static function iniciar(array $cartas, int $aposta, array $decks = [], ?callable $embaralhar = null, array $nomes = []): array
    {
        if (!$cartas) {
            throw new \InvalidArgumentException('O mestre ainda não criou nenhuma carta de Gladio.');
        }
        $embaralhar ??= function (array $a): array {
            shuffle($a);

            return $a;
        };
        $n = max(2, min(self::MAX_JOGADORES, count($decks)));
        $ordem = array_slice(self::LADOS, 0, $n);
        $vida = $aposta > 0 ? $aposta : self::VIDA_AMISTOSO;
        $e = ['lados' => [], 'ordem' => $ordem, 'nomes' => [], 'vez' => 'a', 'turno' => 1, 'fase' => 'principal', 'ataque' => null,
              'cartas' => $cartas, 'seq' => 0, 'log' => [], 'fim' => null, 'total' => $vida * $n];

        foreach ($ordem as $lado) {
            $e['nomes'][$lado] = (string) ($nomes[$lado] ?? strtoupper($lado));
            $ids = array_values(array_filter(array_map('strval', $decks[$lado] ?? []), fn ($id) => isset($cartas[$id])));
            if (!$ids) { // sem deck: as cartas do mestre embaralhadas e repetidas até completar 20
                while (count($ids) < self::BARALHO) {
                    foreach ($embaralhar(array_keys($cartas)) as $id) {
                        if (count($ids) < self::BARALHO) {
                            $ids[] = (string) $id;
                        }
                    }
                }
            }
            $baralho = [];
            foreach ($embaralhar($ids) as $id) {
                $baralho[] = ['i' => ++$e['seq'], 'c' => (string) $id];
            }
            $e['lados'][$lado] = [
                'vida' => $vida, 'max' => $vida, 'energia' => 0, 'energiaMax' => 0,
                'mao' => [], 'baralho' => $baralho, 'mesa' => [], 'feiticos' => [], 'cemiterio' => [],
                'fadiga' => 0, 'shots' => 0, 'fora' => false,
            ];
            for ($k = 0; $k < self::MAO_INICIAL; $k++) {
                $e = self::comprarUma($e, $lado);
            }
        }
        $e['lados']['a']['energiaMax'] = 1;
        $e['lados']['a']['energia'] = 1;
        $e['log'] = [];
        self::log($e, 'A partida começou com ' . $n . ' jogadores.');

        return $e;
    }

    /** Estado salvo por uma versão antiga do jogo (2 jogadores, sem shots nem feitiços no campo): completa o que falta. */
    public static function completar(array $e): array
    {
        $e['fase'] ??= 'principal';
        $e['ataque'] ??= null;
        $e['ordem'] ??= array_values(array_intersect(self::LADOS, array_keys($e['lados'])));
        $e['nomes'] ??= [];
        foreach ($e['ordem'] as $lado) {
            $e['nomes'][$lado] ??= strtoupper($lado);
            $l = &$e['lados'][$lado];
            $l['cemiterio'] ??= [];
            $l['feiticos'] ??= [];
            $l['shots'] ??= 0;
            $l['fora'] ??= false;
            foreach ($l['mesa'] as $k => $m) {
                $l['mesa'][$k]['pal'] ??= self::palavrasDe($e, $m['c'] ?? null);
                $l['mesa'][$k]['virada'] ??= false;
                $l['mesa'][$k]['cont'] ??= 0;
                $l['mesa'][$k]['nota'] ??= '';
            }
            unset($l);
        }
        $e['total'] ??= array_sum(array_map(fn ($l) => (int) $l['max'], $e['lados']));
        if ($e['ataque'] && !isset($e['ataque']['defensor'])) {
            $e['ataque']['defensor'] = self::outro($e['vez']);
        }

        return $e;
    }

    /** Em 2 jogadores, o outro lado. */
    public static function outro(string $lado): string
    {
        return $lado === 'a' ? 'b' : 'a';
    }

    /** Lados ainda na partida, na ordem da roda. */
    public static function vivos(array $e): array
    {
        return array_values(array_filter($e['ordem'], fn ($l) => empty($e['lados'][$l]['fora'])));
    }

    private static function inimigos(array $e, string $lado): array
    {
        return array_values(array_filter(self::vivos($e), fn ($l) => $l !== $lado));
    }

    /** Próximo lado vivo depois de $lado, na roda. */
    private static function proximo(array $e, string $lado): string
    {
        $ordem = $e['ordem'];
        $k = array_search($lado, $ordem, true);
        for ($n = 1; $n <= count($ordem); $n++) {
            $l = $ordem[($k + $n) % count($ordem)];
            if (empty($e['lados'][$l]['fora'])) {
                return $l;
            }
        }

        return $lado;
    }

    /** Pote no centro da mesa: toda vida já perdida (o total apostado menos a vida que resta a quem está de pé). */
    public static function pote(array $e): int
    {
        $resta = 0;
        foreach ($e['lados'] as $l) {
            if (empty($l['fora'])) {
                $resta += max(0, (int) $l['vida']);
            }
        }

        return max(0, (int) $e['total'] - $resta);
    }

    private static function log(array &$e, string $t): void
    {
        $e['log'][] = $t;
        $e['log'] = array_slice($e['log'], -60);
    }

    private static function quem(array $e, string $lado): string
    {
        return (string) ($e['nomes'][$lado] ?? strtoupper($lado));
    }

    private static function nome(array $e, ?string $c): string
    {
        return (string) ($e['cartas'][$c]['nome'] ?? 'Carta');
    }

    private static function nomeLenda(array $e, array $m): string
    {
        return $m['c'] === null ? (string) ($m['nome'] ?? 'Ficha') : self::nome($e, $m['c']);
    }

    private static function palavrasDe(array $e, ?string $c): array
    {
        if ($c === null) {
            return [];
        }
        $p = $e['cartas'][$c]['palavras'] ?? [];
        $p = is_string($p) ? explode(',', $p) : (array) $p;

        return array_values(array_intersect(array_keys(self::PALAVRAS), array_map('trim', $p)));
    }

    private static function tem(array $m, string $palavra): bool
    {
        return in_array($palavra, $m['pal'] ?? [], true);
    }

    private static function idx(array $lista, int $i): int
    {
        foreach ($lista as $k => $c) {
            if ((int) $c['i'] === $i) {
                return $k;
            }
        }

        return -1;
    }

    /** Onde está a Lenda $i: [lado, índice] ou null. */
    private static function acharLenda(array $e, int $i): ?array
    {
        foreach ($e['ordem'] as $lado) {
            $k = self::idx($e['lados'][$lado]['mesa'], $i);
            if ($k >= 0) {
                return [$lado, $k];
            }
        }

        return null;
    }

    private static function comprarUma(array $e, string $lado): array
    {
        $l = &$e['lados'][$lado];
        if (!$l['baralho']) {
            $l['fadiga']++;
            $l['vida'] -= $l['fadiga'];
            self::log($e, self::quem($e, $lado) . " está sem cartas no baralho: fadiga de {$l['fadiga']}.");

            return $e;
        }
        $carta = array_shift($l['baralho']);
        if (count($l['mao']) >= self::MAO_MAX) {
            $l['cemiterio'][] = $carta;
            self::log($e, 'Mão cheia: ' . self::nome($e, $carta['c']) . ' foi para o cemitério.');

            return $e;
        }
        $l['mao'][] = $carta;

        return $e;
    }

    /** Tira um jogador da partida: as cartas dele na mesa vão para o cemitério e, se só sobrar um, ele vence. */
    private static function eliminar(array $e, string $lado, string $motivo): array
    {
        if (!empty($e['lados'][$lado]['fora']) || $e['fim']) {
            return $e;
        }
        $l = &$e['lados'][$lado];
        $l['fora'] = true;
        foreach ($l['mesa'] as $m) {
            if ($m['c'] !== null) {
                $l['cemiterio'][] = ['i' => $m['i'], 'c' => $m['c']];
            }
        }
        foreach ($l['feiticos'] as $f) {
            $l['cemiterio'][] = ['i' => $f['i'], 'c' => $f['c']];
        }
        $l['mesa'] = [];
        $l['feiticos'] = [];
        unset($l);
        self::log($e, self::quem($e, $lado) . ' está fora da partida (' . $motivo . ').');

        // ataque que envolvia quem saiu é desfeito
        if ($e['ataque'] && ($e['ataque']['defensor'] === $lado || $e['vez'] === $lado)) {
            $e['ataque'] = null;
            $e['fase'] = 'principal';
        }
        $vivos = self::vivos($e);
        if (count($vivos) === 1) {
            $e['fim'] = ['vencedor' => $vivos[0], 'motivo' => $motivo === 'desistência' ? 'desistencia' : ($motivo === '7 shots' ? 'shots' : 'vida')];
            $e['fase'] = 'principal';
            $e['ataque'] = null;
            self::log($e, self::quem($e, $vivos[0]) . ' venceu a partida.');
        } elseif ($e['vez'] === $lado) {
            $e = self::comecarTurno($e, self::proximo($e, $lado));
        }

        return $e;
    }

    /**
     * Lendas sem vida (ou marcadas por Toque mortal) vão para o cemitério (fichas somem) e quem ficou sem vida sai.
     * Indestrutível não cai: a vida fica em pelo menos 1. Se todos caírem juntos, vence quem agiu.
     */
    private static function conferir(array $e, string $quem): array
    {
        foreach ($e['ordem'] as $lado) {
            $vivas = [];
            foreach ($e['lados'][$lado]['mesa'] as $m) {
                $cai = $m['vida'] <= 0 || !empty($m['morto']);
                unset($m['morto']);
                if ($cai && self::tem($m, 'indestrutivel')) {
                    $m['vida'] = max(1, $m['vida']);
                    self::log($e, self::nomeLenda($e, $m) . ' é indestrutível e segue de pé.');
                    $cai = false;
                }
                if (!$cai) {
                    $vivas[] = $m;
                    continue;
                }
                self::log($e, self::nomeLenda($e, $m) . ' caiu.');
                if ($m['c'] !== null) {
                    $e['lados'][$lado]['cemiterio'][] = ['i' => $m['i'], 'c' => $m['c']];
                }
            }
            $e['lados'][$lado]['mesa'] = $vivas;
        }
        $mortos = array_values(array_filter(self::vivos($e), fn ($l) => $e['lados'][$l]['vida'] <= 0));
        if ($mortos && count($mortos) === count(self::vivos($e))) {
            // todos zeraram na mesma ação: vence quem agiu (se ainda estiver na partida)
            $vencedor = in_array($quem, $mortos, true) ? $quem : $mortos[0];
            foreach ($mortos as $l) {
                if ($l !== $vencedor) {
                    $e = self::eliminar($e, $l, 'sem vida');
                }
            }

            return $e;
        }
        foreach ($mortos as $l) {
            $e = self::eliminar($e, $l, 'sem vida');
        }

        return $e;
    }

    private static function exigirAtivo(array $e, string $lado): void
    {
        if ($e['fim']) {
            throw new \InvalidArgumentException('A partida já acabou.');
        }
        if (!empty($e['lados'][$lado]['fora'])) {
            throw new \InvalidArgumentException('Você já está fora da partida.');
        }
    }

    private static function exigirTurno(array $e, string $lado): void
    {
        self::exigirAtivo($e, $lado);
        if ($e['vez'] !== $lado) {
            throw new \InvalidArgumentException('Não é a sua vez.');
        }
        if ($e['fase'] !== 'principal') {
            throw new \InvalidArgumentException('Espere o adversário escolher a defesa.');
        }
    }

    private static function exigirDefesa(array $e, string $lado): void
    {
        self::exigirAtivo($e, $lado);
        if ($e['fase'] !== 'defesa' || ($e['ataque']['defensor'] ?? null) !== $lado) {
            throw new \InvalidArgumentException('Não há ataque para você defender agora.');
        }
    }

    /* ================================================================== */
    /*  Jogar cartas                                                       */
    /* ================================================================== */

    /** Alvo de jogador: 'eu', 'j:<lado>' ou, em 2 jogadores, 'inimigo'. Devolve o lado ou null. */
    private static function ladoAlvo(array $e, string $lado, mixed $alvo): ?string
    {
        if ($alvo === 'eu') {
            return $lado;
        }
        if ($alvo === 'inimigo') {
            $ini = self::inimigos($e, $lado);

            return count($ini) === 1 ? $ini[0] : null;
        }
        if (is_string($alvo) && str_starts_with($alvo, 'j:')) {
            $l = substr($alvo, 2);

            return isset($e['lados'][$l]) && empty($e['lados'][$l]['fora']) ? $l : null;
        }

        return null;
    }

    /**
     * Joga uma carta da mão. $alvo: 'eu' | 'j:<lado>' | 'inimigo' (2 jogadores) | id de uma Lenda na mesa.
     * Fora do próprio turno só dá para jogar Lendas, e só quando se está sendo atacado. $gratis: sem pagar energia.
     * Feitiço sem efeito próprio: $efeitoEscolhido/$valorEscolhido são o que a descrição pede, escolhidos por quem joga
     * (fica registrado); sem escolha, ele só é conjurado. Depois o feitiço fica no campo do dono.
     */
    public static function jogar(array $e, string $lado, int $inst, mixed $alvo = null, bool $gratis = false,
                                 ?string $efeitoEscolhido = null, int $valorEscolhido = 0): array
    {
        $defendendo = !$e['fim'] && $e['fase'] === 'defesa' && ($e['ataque']['defensor'] ?? null) === $lado;
        if ($defendendo) {
            self::exigirDefesa($e, $lado);
        } else {
            self::exigirTurno($e, $lado);
        }
        $l = &$e['lados'][$lado];
        $k = self::idx($l['mao'], $inst);
        if ($k < 0) {
            throw new \InvalidArgumentException('Essa carta não está na sua mão.');
        }
        $id = $l['mao'][$k]['c'];
        $carta = $e['cartas'][$id] ?? null;
        if (!$carta) {
            throw new \InvalidArgumentException('Carta desconhecida.');
        }
        if ($defendendo && $carta['tipo'] !== 'lenda') {
            throw new \InvalidArgumentException('Na defesa só dá para jogar Lendas.');
        }
        if ($defendendo && $gratis) {
            throw new \InvalidArgumentException('Na defesa as Lendas são pagas com energia.');
        }
        $custo = $gratis ? 0 : (int) $carta['custo'];
        if ($custo > $l['energia']) {
            throw new \InvalidArgumentException('Energia insuficiente.');
        }
        $nome = $carta['nome'];
        $quem = self::quem($e, $lado);
        $semCusto = $gratis ? ' (sem pagar energia)' : '';

        if ($carta['tipo'] === 'lenda') {
            if (count($l['mesa']) >= self::MESA_MAX) {
                throw new \InvalidArgumentException('Sua mesa já tem ' . self::MESA_MAX . ' Lendas.');
            }
            $pal = self::palavrasDe($e, $id);
            $l['mesa'][] = ['i' => $l['mao'][$k]['i'], 'c' => $id, 'atk' => (int) $carta['ataque'], 'vida' => (int) $carta['vida'],
                            'max' => (int) $carta['vida'], 'pode' => !$defendendo && in_array('impeto', $pal, true), 'virada' => false,
                            'pal' => $pal, 'cont' => 0, 'nota' => ''];
            array_splice($l['mao'], $k, 1);
            $l['energia'] -= $custo;
            unset($l);
            self::log($e, ($defendendo ? "Na defesa, $quem invocou $nome" : "$quem invocou $nome") . "$semCusto.");

            return $e;
        }

        // feitiço: o efeito é o da carta (feitiços antigos) ou o que quem jogou escolheu conforme a descrição
        $efeito = $carta['efeito'] ?? null;
        $valor = max(0, (int) ($carta['valor'] ?? 0));
        if (!$efeito && $efeitoEscolhido) {
            if (!in_array($efeitoEscolhido, self::EFEITOS, true)) {
                throw new \InvalidArgumentException('Efeito desconhecido.');
            }
            $efeito = $efeitoEscolhido;
            $valor = max(0, min(9999, $valorEscolhido));
            $semCusto .= ' (efeito escolhido por quem jogou)';
        }
        $precisa = $efeito ? (self::ALVOS[$efeito] ?? null) : null;
        $alvoLenda = null; // [lado, índice]
        $alvoHeroi = null; // lado
        if ($precisa) {
            $heroi = self::ladoAlvo($e, $lado, $alvo);
            if ($heroi !== null && ($precisa === 'inimigo' ? $heroi !== $lado : ($precisa === 'aliado' && $heroi === $lado))) {
                $alvoHeroi = $heroi;
            } elseif (is_numeric($alvo) && ($onde = self::acharLenda($e, (int) $alvo))) {
                [$dono, $ki] = $onde;
                $ok = $precisa === 'inimigo' ? $dono !== $lado : $dono === $lado;
                if ($ok && $dono !== $lado && self::tem($e['lados'][$dono]['mesa'][$ki], 'resistencia')) {
                    throw new \InvalidArgumentException(self::nomeLenda($e, $e['lados'][$dono]['mesa'][$ki]) . ' tem Hexo-Imune: não pode ser alvo dos seus feitiços.');
                }
                if ($ok) {
                    $alvoLenda = [$dono, $ki];
                }
            }
            if (!$alvoLenda && !$alvoHeroi) {
                throw new \InvalidArgumentException(match ($precisa) {
                    'inimigo' => 'Escolha uma Lenda inimiga ou um adversário.',
                    'aliado'  => 'Escolha uma Lenda sua ou você mesmo.',
                    default   => 'Escolha uma Lenda sua.',
                });
            }
        }

        // o feitiço fica no campo até o dono mandá-lo para o cemitério
        $l['feiticos'][] = ['i' => $l['mao'][$k]['i'], 'c' => $id, 'cont' => 0, 'nota' => ''];
        array_splice($l['mao'], $k, 1);
        $l['energia'] -= $custo;
        unset($l);
        if (!$efeito) { // sem efeito automático: o texto da carta é resolvido à mão
            self::log($e, "$quem conjurou $nome$semCusto.");

            return self::conferir($e, $lado);
        }

        switch ($efeito) {
            case 'dano':
                if ($alvoLenda) {
                    $e['lados'][$alvoLenda[0]]['mesa'][$alvoLenda[1]]['vida'] -= $valor;
                    self::log($e, "$quem conjurou $nome: $valor de dano em " . self::nomeLenda($e, $e['lados'][$alvoLenda[0]]['mesa'][$alvoLenda[1]]) . "$semCusto.");
                } else {
                    $e['lados'][$alvoHeroi]['vida'] -= $valor;
                    self::log($e, "$quem conjurou $nome: $valor de dano em " . self::quem($e, $alvoHeroi) . "$semCusto.");
                }
                break;
            case 'dano_todos':
                foreach (self::inimigos($e, $lado) as $ini) {
                    foreach ($e['lados'][$ini]['mesa'] as $i => $m) {
                        $e['lados'][$ini]['mesa'][$i]['vida'] -= $valor;
                    }
                }
                self::log($e, "$quem conjurou $nome: $valor de dano em todas as Lendas inimigas$semCusto.");
                break;
            case 'cura':
                if ($alvoLenda) {
                    $m = &$e['lados'][$alvoLenda[0]]['mesa'][$alvoLenda[1]];
                    $m['vida'] = min($m['max'], $m['vida'] + $valor);
                    unset($m);
                } else {
                    self::curar($e, $alvoHeroi, $valor);
                }
                self::log($e, "$quem conjurou $nome: curou $valor$semCusto.");
                break;
            case 'comprar':
                for ($n = 0; $n < $valor; $n++) {
                    $e = self::comprarUma($e, $lado);
                }
                self::log($e, "$quem conjurou $nome: comprou $valor carta(s)$semCusto.");
                break;
            case 'fortalecer':
                $e['lados'][$lado]['mesa'][$alvoLenda[1]]['atk'] += $valor;
                self::log($e, "$quem conjurou $nome: +$valor de ataque a " . self::nomeLenda($e, $e['lados'][$lado]['mesa'][$alvoLenda[1]]) . "$semCusto.");
                break;
            case 'energia':
                $e['lados'][$lado]['energia'] += $valor;
                self::log($e, "$quem conjurou $nome: +$valor de energia$semCusto.");
                break;
        }

        return self::conferir($e, $lado);
    }

    /* ================================================================== */
    /*  Combate                                                            */
    /* ================================================================== */

    /**
     * Declara o ataque contra um adversário ($alvo: lado; em 2 jogadores pode ficar vazio) com uma ou mais Lendas
     * prontas. A partida espera a defesa de quem foi atacado.
     */
    public static function atacar(array $e, string $lado, array $insts, ?string $alvo = null): array
    {
        self::exigirTurno($e, $lado);
        $inimigos = self::inimigos($e, $lado);
        if ($alvo === null && count($inimigos) === 1) {
            $alvo = $inimigos[0];
        }
        if (!in_array($alvo, $inimigos, true)) {
            throw new \InvalidArgumentException('Escolha qual adversário atacar.');
        }
        $insts = array_values(array_unique(array_map('intval', $insts)));
        if (!$insts) {
            throw new \InvalidArgumentException('Escolha quem vai atacar.');
        }
        $nomes = [];
        foreach ($insts as $i) {
            $k = self::idx($e['lados'][$lado]['mesa'], $i);
            if ($k < 0) {
                throw new \InvalidArgumentException('Essa Lenda não está na sua mesa.');
            }
            $m = $e['lados'][$lado]['mesa'][$k];
            if (self::tem($m, 'defensor')) {
                throw new \InvalidArgumentException(self::nomeLenda($e, $m) . ' tem Defensor e não pode atacar.');
            }
            if (!$m['pode']) {
                throw new \InvalidArgumentException(self::nomeLenda($e, $m) . ' ainda não pode atacar.');
            }
            if ($m['atk'] <= 0) {
                throw new \InvalidArgumentException(self::nomeLenda($e, $m) . ' não tem ataque.');
            }
            $nomes[] = self::nomeLenda($e, $m);
        }
        foreach ($insts as $i) {
            $k = self::idx($e['lados'][$lado]['mesa'], $i);
            $e['lados'][$lado]['mesa'][$k]['pode'] = false;
            // atacou: fica virada (não bloqueia) até o próximo turno de quem a controla, a não ser com Vigilância
            $e['lados'][$lado]['mesa'][$k]['virada'] = !self::tem($e['lados'][$lado]['mesa'][$k], 'vigilancia');
        }
        $e['fase'] = 'defesa';
        $e['ataque'] = ['atacantes' => $insts, 'defensor' => $alvo, 'bloqueios' => (object) []];
        self::log($e, self::quem($e, $lado) . ' atacou ' . self::quem($e, $alvo) . ' com ' . implode(', ', $nomes) . '.');

        return $e;
    }

    /** Defensor escolhe (ou tira, com $atacante null) quem a Lenda dele bloqueia. */
    public static function bloquear(array $e, string $lado, int $bloqueador, ?int $atacante): array
    {
        self::exigirDefesa($e, $lado);
        $kb = self::idx($e['lados'][$lado]['mesa'], $bloqueador);
        if ($kb < 0) {
            throw new \InvalidArgumentException('Escolha uma Lenda sua para bloquear.');
        }
        $b = $e['lados'][$lado]['mesa'][$kb];
        $bloqueios = (array) $e['ataque']['bloqueios'];
        if ($atacante === null) {
            unset($bloqueios[$bloqueador]);
        } else {
            if (!in_array($atacante, $e['ataque']['atacantes'], true)) {
                throw new \InvalidArgumentException('Essa Lenda não está atacando.');
            }
            if (!empty($b['virada'])) {
                throw new \InvalidArgumentException(self::nomeLenda($e, $b) . ' está virada (atacou no seu último turno) e não pode bloquear.');
            }
            $ka = self::idx($e['lados'][$e['vez']]['mesa'], $atacante);
            $a = $ka >= 0 ? $e['lados'][$e['vez']]['mesa'][$ka] : null;
            if ($a && self::tem($a, 'voar') && !self::tem($b, 'voar') && !self::tem($b, 'alcance')) {
                throw new \InvalidArgumentException(self::nomeLenda($e, $a) . ' tem Voar: só Lendas com Voar ou Alcance podem bloqueá-la.');
            }
            $bloqueios[$bloqueador] = $atacante;
        }
        $e['ataque']['bloqueios'] = (object) $bloqueios;

        return $e;
    }

    /** A Lenda ainda está de pé para causar dano neste combate? */
    private static function dePe(array $m): bool
    {
        return self::tem($m, 'indestrutivel') || ($m['vida'] > 0 && empty($m['morto']));
    }

    /** Causa dano neste passo? Iniciativa e Golpe duplo no primeiro; sem Iniciativa (ou com Golpe duplo) no normal. */
    private static function age(array $m, string $passo): bool
    {
        return $passo === 'primeiro'
            ? self::tem($m, 'iniciativa') || self::tem($m, 'duplo')
            : !self::tem($m, 'iniciativa') || self::tem($m, 'duplo');
    }

    private static function curar(array &$e, string $lado, int $quanto): void
    {
        if ($quanto > 0) {
            $l = &$e['lados'][$lado];
            $l['vida'] = min($l['max'], $l['vida'] + $quanto);
        }
    }

    /**
     * Defensor confirma a defesa: o combate é resolvido e o turno volta para quem atacou.
     * Cada atacante luta em dois passos de dano (Iniciativa/Golpe duplo primeiro, depois o normal); no mesmo passo,
     * todos causam dano ao mesmo tempo. O atacante distribui o dano pelos bloqueadores na ordem, o suficiente para
     * derrubar cada um (com Toque mortal basta 1); o resto vai no último ou, com Sobrepujar, no jogador.
     */
    public static function defender(array $e, string $lado): array
    {
        self::exigirDefesa($e, $lado);
        $at = $e['vez'];
        $bloqueios = (array) $e['ataque']['bloqueios'];

        // Ameaça: nenhum bloqueador ou dois ou mais
        foreach ($e['ataque']['atacantes'] as $ai) {
            $ka = self::idx($e['lados'][$at]['mesa'], $ai);
            $n = count(array_filter($bloqueios, fn ($alvo) => (int) $alvo === $ai));
            if ($ka >= 0 && $n === 1 && self::tem($e['lados'][$at]['mesa'][$ka], 'ameaca')) {
                throw new \InvalidArgumentException(self::nomeLenda($e, $e['lados'][$at]['mesa'][$ka]) . ' tem Ameaça: bloqueie com duas ou mais Lendas, ou com nenhuma.');
            }
        }

        foreach ($e['ataque']['atacantes'] as $ai) {
            $ka = self::idx($e['lados'][$at]['mesa'], $ai);
            if ($ka < 0) {
                continue; // saiu da mesa antes do combate
            }
            $bloq = [];
            foreach ($bloqueios as $bi => $alvo) {
                $kb = self::idx($e['lados'][$lado]['mesa'], (int) $bi);
                if ((int) $alvo === $ai && $kb >= 0) {
                    $bloq[] = $kb;
                }
            }
            $nomeA = self::nomeLenda($e, $e['lados'][$at]['mesa'][$ka]);
            if ($bloq) {
                $nomesB = implode(', ', array_map(fn ($kb) => self::nomeLenda($e, $e['lados'][$lado]['mesa'][$kb]), $bloq));
                self::log($e, "$nomeA foi bloqueado por $nomesB.");
            }

            foreach (['primeiro', 'normal'] as $passo) {
                $a = $e['lados'][$at]['mesa'][$ka];
                $danoNoJogador = 0;
                $danoNosBloq = [];    // índice => dano
                $danoNoAtacante = 0;
                $toqueNoAtacante = false;
                $curaDefensor = 0;

                if (self::age($a, $passo) && self::dePe($a) && $a['atk'] > 0) {
                    if (!$bloq) {
                        $danoNoJogador = $a['atk'];
                    } else {
                        $resto = $a['atk'];
                        $vivos = array_values(array_filter($bloq, fn ($kb) => self::dePe($e['lados'][$lado]['mesa'][$kb])));
                        foreach ($vivos as $kb) {
                            $b = $e['lados'][$lado]['mesa'][$kb];
                            $letal = self::tem($a, 'toque') ? min(1, max(0, $b['vida'])) : max(0, $b['vida']);
                            $d = min($resto, $letal);
                            $danoNosBloq[$kb] = ($danoNosBloq[$kb] ?? 0) + $d;
                            $resto -= $d;
                        }
                        if ($resto > 0) {
                            if (self::tem($a, 'sobrepujar')) {
                                $danoNoJogador = $resto;
                                self::log($e, "$nomeA sobrepujou: $resto de dano passou para o jogador.");
                            } elseif ($vivos) {
                                $ult = end($vivos);
                                $danoNosBloq[$ult] = ($danoNosBloq[$ult] ?? 0) + $resto;
                            }
                        }
                    }
                }
                foreach ($bloq as $kb) {
                    $b = $e['lados'][$lado]['mesa'][$kb];
                    if (self::age($b, $passo) && self::dePe($b) && $b['atk'] > 0) {
                        $danoNoAtacante += $b['atk'];
                        $toqueNoAtacante = $toqueNoAtacante || self::tem($b, 'toque');
                        if (self::tem($b, 'vinculo')) {
                            $curaDefensor += $b['atk'];
                        }
                    }
                }

                // tudo do mesmo passo acontece junto
                $causado = $danoNoJogador + array_sum($danoNosBloq);
                foreach ($danoNosBloq as $kb => $d) {
                    $e['lados'][$lado]['mesa'][$kb]['vida'] -= $d;
                    if ($d > 0 && self::tem($a, 'toque')) {
                        $e['lados'][$lado]['mesa'][$kb]['morto'] = true;
                    }
                }
                if ($danoNoJogador > 0) {
                    $e['lados'][$lado]['vida'] -= $danoNoJogador;
                    if (!$bloq) {
                        self::log($e, "$nomeA passou sem bloqueio: $danoNoJogador de dano" . ($passo === 'primeiro' ? ' (Iniciativa).' : '.'));
                    }
                }
                if ($danoNoAtacante > 0) {
                    $e['lados'][$at]['mesa'][$ka]['vida'] -= $danoNoAtacante;
                    if ($toqueNoAtacante) {
                        $e['lados'][$at]['mesa'][$ka]['morto'] = true;
                    }
                }
                if ($causado > 0 && self::tem($a, 'vinculo')) {
                    self::curar($e, $at, $causado);
                    self::log($e, "$nomeA (Vínculo com a vida) curou $causado.");
                }
                if ($curaDefensor > 0) {
                    self::curar($e, $lado, $curaDefensor);
                    self::log($e, "Vínculo com a vida: " . self::quem($e, $lado) . " curou $curaDefensor.");
                }
            }
        }

        $e['fase'] = 'principal';
        $e['ataque'] = null;

        return self::conferir($e, $at);
    }

    /** Começa o turno de $lado: +1 de energia máxima (até 10), recarrega, as Lendas dele ficam prontas e ele compra 1. */
    private static function comecarTurno(array $e, string $lado): array
    {
        $e['vez'] = $lado;
        $e['turno']++;
        $e['fase'] = 'principal';
        $e['ataque'] = null;
        $l = &$e['lados'][$lado];
        $l['energiaMax'] = min(self::ENERGIA_MAX, $l['energiaMax'] + 1);
        $l['energia'] = $l['energiaMax'];
        foreach ($l['mesa'] as $i => $m) {
            $l['mesa'][$i]['pode'] = true;
            $l['mesa'][$i]['virada'] = false;
        }
        unset($l);
        $e = self::comprarUma($e, $lado);

        return self::conferir($e, $lado);
    }

    /** Termina o turno: passa a vez para o próximo jogador de pé, na roda. */
    public static function passar(array $e, string $lado): array
    {
        self::exigirTurno($e, $lado);

        return self::comecarTurno($e, self::proximo($e, $lado));
    }

    /** Desiste: sai da partida (se sobrar um só, ele vence). */
    public static function desistir(array $e, string $lado): array
    {
        self::exigirAtivo($e, $lado);

        return self::eliminar($e, $lado, 'desistência');
    }

    /** Toma (ou devolve, com $quanto negativo) shots, a qualquer momento. No 7º shot o jogador está fora. */
    public static function shot(array $e, string $lado, int $quanto = 1): array
    {
        self::exigirAtivo($e, $lado);
        $l = &$e['lados'][$lado];
        $antes = $l['shots'];
        $l['shots'] = max(0, min(self::SHOTS_MAX, $l['shots'] + $quanto));
        $agora = $l['shots'];
        unset($l);
        if ($agora === $antes) {
            return $e;
        }
        self::log($e, self::quem($e, $lado) . ($agora > $antes ? ' tomou ' . ($agora - $antes) . ' shot' . ($agora - $antes > 1 ? 's' : '') : ' desfez ' . ($antes - $agora) . ' shot')
            . " ($agora/" . self::SHOTS_MAX . ').');

        return $agora >= self::SHOTS_MAX ? self::eliminar($e, $lado, self::SHOTS_MAX . ' shots') : $e;
    }

    /* ================================================================== */
    /*  Ferramentas manuais (efeitos escritos nas cartas)                  */
    /* ================================================================== */

    public static function comprar(array $e, string $lado): array
    {
        self::exigirTurno($e, $lado);
        $e = self::comprarUma($e, $lado);
        self::log($e, self::quem($e, $lado) . ' comprou uma carta (ação manual).');

        return self::conferir($e, $lado);
    }

    public static function embaralhar(array $e, string $lado, ?callable $embaralhar = null): array
    {
        self::exigirTurno($e, $lado);
        $b = $e['lados'][$lado]['baralho'];
        if ($embaralhar) {
            $b = $embaralhar($b);
        } else {
            shuffle($b);
        }
        $e['lados'][$lado]['baralho'] = array_values($b);
        self::log($e, self::quem($e, $lado) . ' embaralhou o baralho.');

        return $e;
    }

    /** Nova ordem do baralho (do topo para o fundo), com exatamente as mesmas cartas. */
    public static function reordenar(array $e, string $lado, array $ordem): array
    {
        self::exigirTurno($e, $lado);
        $atual = [];
        foreach ($e['lados'][$lado]['baralho'] as $c) {
            $atual[(int) $c['i']] = $c;
        }
        $ordem = array_map('intval', $ordem);
        $ids = $ordem;
        sort($ids);
        $tem = array_keys($atual);
        sort($tem);
        if ($ids !== $tem) {
            throw new \InvalidArgumentException('A nova ordem precisa ter exatamente as cartas do baralho.');
        }
        $e['lados'][$lado]['baralho'] = array_map(fn ($i) => $atual[$i], $ordem);
        self::log($e, self::quem($e, $lado) . ' mudou a ordem das cartas do baralho.');

        return $e;
    }

    /** Descarta uma carta da própria mão (a qualquer momento). */
    public static function descartar(array $e, string $lado, int $inst): array
    {
        self::exigirAtivo($e, $lado);
        $k = self::idx($e['lados'][$lado]['mao'], $inst);
        if ($k < 0) {
            throw new \InvalidArgumentException('Essa carta não está na sua mão.');
        }
        $c = $e['lados'][$lado]['mao'][$k];
        array_splice($e['lados'][$lado]['mao'], $k, 1);
        $e['lados'][$lado]['cemiterio'][] = ['i' => $c['i'], 'c' => $c['c']];
        self::log($e, self::quem($e, $lado) . ' descartou ' . self::nome($e, $c['c']) . '.');

        return $e;
    }

    /** Revela (ou esconde de novo) uma carta da própria mão para os outros jogadores, a qualquer momento. */
    public static function revelar(array $e, string $lado, int $inst): array
    {
        self::exigirAtivo($e, $lado);
        $k = self::idx($e['lados'][$lado]['mao'], $inst);
        if ($k < 0) {
            throw new \InvalidArgumentException('Essa carta não está na sua mão.');
        }
        $rev = empty($e['lados'][$lado]['mao'][$k]['rev']);
        $e['lados'][$lado]['mao'][$k]['rev'] = $rev;
        $nome = self::nome($e, $e['lados'][$lado]['mao'][$k]['c']);
        self::log($e, self::quem($e, $lado) . ($rev ? " revelou $nome da mão." : ' escondeu de novo uma carta da mão.'));

        return $e;
    }

    /** Traz uma carta do próprio cemitério de volta para a mão. */
    public static function recuperar(array $e, string $lado, int $inst): array
    {
        self::exigirTurno($e, $lado);
        $k = self::idx($e['lados'][$lado]['cemiterio'], $inst);
        if ($k < 0) {
            throw new \InvalidArgumentException('Essa carta não está no seu cemitério.');
        }
        if (count($e['lados'][$lado]['mao']) >= self::MAO_MAX) {
            throw new \InvalidArgumentException('Sua mão está cheia.');
        }
        $c = $e['lados'][$lado]['cemiterio'][$k];
        array_splice($e['lados'][$lado]['cemiterio'], $k, 1);
        $e['lados'][$lado]['mao'][] = $c;
        self::log($e, self::quem($e, $lado) . ' recuperou ' . self::nome($e, $c['c']) . ' do cemitério para a mão.');

        return $e;
    }

    /** Cria uma ficha (Lenda sem carta) na própria mesa. */
    public static function ficha(array $e, string $lado, string $nome, int $atk, int $vida): array
    {
        self::exigirTurno($e, $lado);
        $nome = trim($nome) !== '' ? mb_substr(trim($nome), 0, 40) : 'Ficha';
        if ($vida < 1) {
            throw new \InvalidArgumentException('A ficha precisa de pelo menos 1 de vida.');
        }
        if (count($e['lados'][$lado]['mesa']) >= self::MESA_MAX) {
            throw new \InvalidArgumentException('Sua mesa já tem ' . self::MESA_MAX . ' Lendas.');
        }
        $atk = max(0, min(9999, $atk));
        $vida = min(9999, $vida);
        $e['lados'][$lado]['mesa'][] = ['i' => ++$e['seq'], 'c' => null, 'nome' => $nome, 'atk' => $atk, 'vida' => $vida,
                                        'max' => $vida, 'pode' => false, 'virada' => false, 'pal' => [], 'cont' => 0, 'nota' => ''];
        self::log($e, self::quem($e, $lado) . " criou a ficha $nome ($atk/$vida).");

        return $e;
    }

    /** Ajusta ataque e vida de qualquer Lenda na mesa (sua ou de um adversário), a qualquer momento. Vida 0: ela cai. */
    public static function ajustar(array $e, string $lado, int $inst, int $datk, int $dvida): array
    {
        self::exigirAtivo($e, $lado);
        $onde = self::acharLenda($e, $inst);
        if (!$onde) {
            throw new \InvalidArgumentException('Essa Lenda não está na mesa.');
        }
        [$dono, $k] = $onde;
        $m = &$e['lados'][$dono]['mesa'][$k];
        $m['atk'] = max(0, $m['atk'] + $datk);
        $m['vida'] += $dvida;
        if ($dvida > 0) {
            $m['max'] += $dvida;
        }
        $txt = self::nomeLenda($e, $m) . ($dono === $lado ? '' : ' de ' . self::quem($e, $dono)) . ' ficou ' . $m['atk'] . '/' . $m['vida'];
        unset($m);
        self::log($e, self::quem($e, $lado) . sprintf(' ajustou %+d/%+d: %s.', $datk, $dvida, $txt));

        return self::conferir($e, $lado);
    }

    /**
     * Contador e anotação de uma carta na mesa (Lenda ou feitiço no campo, de qualquer jogador), a qualquer momento.
     * $cont: quanto somar ao contador; $nota: texto curto (null mantém, '' apaga).
     */
    public static function marcar(array $e, string $lado, int $inst, int $cont = 0, ?string $nota = null): array
    {
        self::exigirAtivo($e, $lado);
        $achou = null;
        foreach ($e['ordem'] as $dono) {
            foreach (['mesa', 'feiticos'] as $zona) {
                $k = self::idx($e['lados'][$dono][$zona], $inst);
                if ($k >= 0) {
                    $achou = [$dono, $zona, $k];
                    break 2;
                }
            }
        }
        if (!$achou) {
            throw new \InvalidArgumentException('Essa carta não está na mesa.');
        }
        [$dono, $zona, $k] = $achou;
        $c = &$e['lados'][$dono][$zona][$k];
        $nomeCarta = $zona === 'mesa' ? self::nomeLenda($e, $c) : self::nome($e, $c['c']);
        if ($cont !== 0) {
            $c['cont'] = max(-999, min(999, (int) ($c['cont'] ?? 0) + $cont));
            self::log($e, self::quem($e, $lado) . sprintf(' mudou o contador de %s para %d.', $nomeCarta, $c['cont']));
        }
        if ($nota !== null) {
            $c['nota'] = mb_substr(trim($nota), 0, 60);
            self::log($e, self::quem($e, $lado) . ($c['nota'] === '' ? " apagou a anotação de $nomeCarta." : " anotou em $nomeCarta: \"{$c['nota']}\"."));
        }
        unset($c);

        return $e;
    }

    /** Uma Lenda sua vira cópia de outra Lenda sua (carta, ataque, vida e palavras-chave de base). */
    public static function copiar(array $e, string $lado, int $inst, int $modelo): array
    {
        self::exigirTurno($e, $lado);
        $mesa = $e['lados'][$lado]['mesa'];
        $k = self::idx($mesa, $inst);
        $km = self::idx($mesa, $modelo);
        if ($k < 0 || $km < 0 || $k === $km) {
            throw new \InvalidArgumentException('Escolha outra Lenda sua como modelo.');
        }
        $antes = self::nomeLenda($e, $mesa[$k]);
        $mod = $mesa[$km];
        if ($mod['c'] !== null) {
            $carta = $e['cartas'][$mod['c']];
            $atk = (int) $carta['ataque'];
            $vida = (int) $carta['vida'];
        } else {
            $atk = $mod['atk'];
            $vida = $mod['max'];
        }
        $e['lados'][$lado]['mesa'][$k] = ['i' => $mesa[$k]['i'], 'c' => $mod['c'], 'atk' => $atk, 'vida' => $vida, 'max' => $vida,
            'pode' => $mesa[$k]['pode'], 'virada' => $mesa[$k]['virada'] ?? false, 'pal' => $mod['pal'] ?? [],
            'cont' => $mesa[$k]['cont'] ?? 0, 'nota' => $mesa[$k]['nota'] ?? ''];
        if ($mod['c'] === null) {
            $e['lados'][$lado]['mesa'][$k]['nome'] = $mod['nome'] ?? 'Ficha';
        }
        self::log($e, "$antes virou uma cópia de " . self::nomeLenda($e, $mod) . '.');

        return $e;
    }

    /** Manda uma Lenda sua para o cemitério (fichas somem). */
    public static function sacrificar(array $e, string $lado, int $inst): array
    {
        self::exigirTurno($e, $lado);
        $k = self::idx($e['lados'][$lado]['mesa'], $inst);
        if ($k < 0) {
            throw new \InvalidArgumentException('Essa Lenda não está na sua mesa.');
        }
        $m = $e['lados'][$lado]['mesa'][$k];
        array_splice($e['lados'][$lado]['mesa'], $k, 1);
        if ($m['c'] !== null) {
            $e['lados'][$lado]['cemiterio'][] = ['i' => $m['i'], 'c' => $m['c']];
        }
        self::log($e, self::quem($e, $lado) . ' sacrificou ' . self::nomeLenda($e, $m) . '.');

        return $e;
    }

    /** Manda um feitiço seu que está no campo para o cemitério (a qualquer momento). */
    public static function encerrarFeitico(array $e, string $lado, int $inst): array
    {
        self::exigirAtivo($e, $lado);
        $k = self::idx($e['lados'][$lado]['feiticos'], $inst);
        if ($k < 0) {
            throw new \InvalidArgumentException('Esse feitiço não está no seu campo.');
        }
        $f = $e['lados'][$lado]['feiticos'][$k];
        array_splice($e['lados'][$lado]['feiticos'], $k, 1);
        $e['lados'][$lado]['cemiterio'][] = ['i' => $f['i'], 'c' => $f['c']];
        self::log($e, self::quem($e, $lado) . ' mandou ' . self::nome($e, $f['c']) . ' para o cemitério.');

        return $e;
    }

    /**
     * O que um lado pode ver: dos outros, a mão vira cartas viradas (menos as reveladas) e o baralho, só a quantidade;
     * cada um vê a própria mão e o próprio baralho. Vai junto o pote do centro da mesa.
     */
    public static function visao(array $e, ?string $eu): array
    {
        foreach ($e['ordem'] as $lado) {
            if ($lado !== $eu) {
                $e['lados'][$lado]['baralho'] = count($e['lados'][$lado]['baralho']);
                $e['lados'][$lado]['mao'] = array_map(
                    fn ($c) => empty($c['rev']) ? ['oculta' => true] : ['i' => $c['i'], 'c' => $c['c'], 'rev' => true],
                    $e['lados'][$lado]['mao']
                );
            }
        }
        $e['pote'] = self::pote($e);

        return $e;
    }
}
