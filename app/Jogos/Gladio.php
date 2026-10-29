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
 *   só escolhe bloqueadores (cartas, inclusive Lendas, só no próprio turno). Quem atacou fica "virada"
 *   e só volta a bloquear no próximo turno de quem a controla.
 * - Shots: qualquer jogador toma shots a qualquer momento (quando uma carta mandar); no 7º está fora.
 * - Quem fica sem vida, toma o 7º shot ou desiste sai da partida (as cartas dele na mesa vão para o cemitério).
 * - Palavras-chave (PALAVRAS e as dicas da tela): Sobrepujar, Ímpeto, Voar, Alcance, Toque mortal, Vínculo com a vida,
 *   Iniciativa, Golpe duplo, Vigilância, Defensor, Ameaça, Indestrutível e Hexo-Imune. Como aqui o dano nas Lendas
 *   não some no fim do turno, Indestrutível segura a vida em 1.
 * - Ferramentas manuais (efeitos escritos nas cartas), todas no registro da partida:
 *   no próprio turno: jogar sem pagar; a qualquer momento: comprar, embaralhar, reordenar o baralho, recuperar do
 *   cemitério, criar ficha, copiar Lenda, sacrificar, descartar, revelar cartas da mão, ajustar ataque/vida de qualquer
 *   Lenda, contadores e anotações em qualquer carta da mesa, mandar o próprio feitiço ao cemitério e tomar shots.
 *
 * - Comandante: cada um escolhe uma Lenda do deck como comandante (deck sorteado: uma Lenda sorteada). Ela começa na
 *   zona de comando, fora do baralho, e pode ser invocada de lá pagando o custo + 2 por vez que já saiu de lá. Quando
 *   cai ou é sacrificada, volta para a zona de comando (se parar no cemitério, dá para devolvê-la ao comando).
 * - Mesa livre: como as regras das cartas variam, cada um mexe à vontade, a qualquer momento, nas próprias coisas:
 *   energia e energia máxima, vida (até o máximo), buscar carta do baralho, invocar sem custo da mão, do cemitério
 *   ou da zona de comando, além das ferramentas acima, que também deixam de depender do turno.
 *
 * - Fases do turno, como no Magic: Compra (as Lendas de quem joga desviram, a energia recarrega, os gatilhos de início
 *   de turno disparam e ele compra 1), Principal (jogar cartas e atacar; o ataque abre a fase de Combate, que volta para
 *   a Principal) e Final (gatilhos de fim de turno, descarte até MAO_FIM cartas e limpeza: o dano nas Lendas some e os
 *   efeitos "até o fim do turno" acabam). Compra e Final andam sozinhas; só param quando alguém precisa escolher algo.
 * - Textos das cartas (GladioTexto): os efeitos entendidos entram numa fila e são aplicados sozinhos (ao entrar, ao
 *   conjurar, ao cair, ao atacar, no início/fim de turno). Quando um efeito pede alvo ou carta, a partida espera a
 *   escolha (estado 'escolha') de quem decide; dá para pular. Feitiço resolvido sozinho vai para o cemitério (fica no
 *   campo se tiver gatilho de início/fim de turno); o que não for entendido continua com as ferramentas manuais.
 *
 * Estado: { lados: { a…f }, ordem [lados em roda], nomes, vez, turno, fase ('compra' | 'principal' | 'defesa' | 'final'),
 *           ataque, cartas (cada uma com hab/parcial/auto do texto), hv, fila [efeitos], escolha, seq, log, fim, total }
 * Efeito na fila: { lado (quem controla), fonte (nome), i (instância da carta), passos [...], jog (jogador escolhido) }.
 * Escolha: { lado (quem escolhe), tipo: jogador | lenda | qualquer | mao | baralho | cemiterio, opcoes [inst | 'j:<lado>'],
 *            texto, fonte, obrig, passo }.
 * Lado: { vida, max, energia, energiaMax, mao[], baralho[], mesa[], feiticos[], cemiterio[], fadiga, shots, fora,
 *         comando[] (zona de comando), cmd (instância do comandante ou null), taxa (vezes que ele saiu do comando) }
 * Carta na mão: { i, c, rev? (revelada) }; no baralho/cemitério: { i, c }; feitiço no campo: { i, c, cont, nota }.
 * Lenda na mesa: { i, c (null na ficha), nome? (ficha), atk, vida, max, pode, virada, pal[], cont, nota,
 *                 tmp? { atk, vida, pal[] } (o que acaba no fim do turno) }.
 * Ataque em andamento: { atacantes: [i…], defensor: lado, bloqueios: { bloqueador i: atacante i } }.
 */
class Gladio
{
    /**
     * Tipos de carta: Lenda (vai para a mesa e luta), Feitiço (efeito de uso único: resolve e vai para o cemitério),
     * Encantamento (fica no campo com o efeito ativo: gatilhos de turno, de morte…) e Equipamento (fica preso a uma
     * Lenda sua, com o bônus de ataque/vida e as palavras-chave da carta e do texto; cai junto com ela).
     * As chaves cabem na coluna tipo (10 letras).
     */
    public const TIPOS = ['lenda', 'feitico', 'encanto', 'equipo'];
    public const NOMES_TIPOS = ['lenda' => 'Lenda', 'feitico' => 'Feitiço', 'encanto' => 'Encantamento', 'equipo' => 'Equipamento'];
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
    public const MAO_MAX = 10;      // comprando com a mão cheia, a carta vai para o cemitério
    public const MAO_FIM = 7;       // no fim do turno, quem joga descarta até ficar com isso
    public const ENERGIA_MAX = 10;
    public const VIDA_AMISTOSO = 30;
    public const TAXA_COMANDANTE = 2; // custo extra por vez que o comandante já saiu da zona de comando
    public const ENERGIA_LIMITE = 99; // teto da energia ajustada à mão

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
     * $comandantes: [lado => id da carta]; sem escolha (ou se ela não for uma Lenda do deck), uma Lenda do deck sorteada.
     */
    public static function iniciar(array $cartas, int $aposta, array $decks = [], ?callable $embaralhar = null, array $nomes = [],
                                   array $comandantes = []): array
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
              'cartas' => self::lerTextos($cartas), 'hv' => GladioTexto::VERSAO, 'fila' => [], 'escolha' => null,
              'seq' => 0, 'log' => [], 'fim' => null, 'total' => $vida * $n,
              'atacou' => false, 'desde' => time(), 'apressado' => null];

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
            // comandante: sai do baralho e vai para a zona de comando
            $lendas = array_values(array_filter($ids, fn ($id) => ($cartas[$id]['tipo'] ?? '') === 'lenda'));
            $cmd = isset($comandantes[$lado]) ? (string) $comandantes[$lado] : null;
            if ($cmd === null || !in_array($cmd, $lendas, true)) {
                $sorteio = $lendas ? $embaralhar($lendas) : [];
                $cmd = $sorteio ? end($sorteio) : null;
            }
            $comando = [];
            if ($cmd !== null) {
                $onde = array_keys($ids, $cmd, true);
                array_splice($ids, end($onde), 1);
                $comando[] = ['i' => ++$e['seq'], 'c' => $cmd];
            }
            $baralho = [];
            foreach ($embaralhar($ids) as $id) {
                $baralho[] = ['i' => ++$e['seq'], 'c' => (string) $id];
            }
            $e['lados'][$lado] = [
                'vida' => $vida, 'max' => $vida, 'energia' => 0, 'energiaMax' => 0,
                'mao' => [], 'baralho' => $baralho, 'mesa' => [], 'feiticos' => [], 'cemiterio' => [],
                'fadiga' => 0, 'shots' => 0, 'fora' => false,
                'comando' => $comando, 'cmd' => $comando ? $comando[0]['i'] : null, 'taxa' => 0,
            ];
            for ($k = 0; $k < self::MAO_INICIAL; $k++) {
                $e = self::comprarUma($e, $lado);
            }
        }
        $e['lados']['a']['energiaMax'] = 1;
        $e['lados']['a']['energia'] = 1;
        $e['log'] = [];
        self::log($e, 'A partida começou com ' . $n . ' jogadores.');
        $e['iniciado'] = true; // daqui em diante compras e mortes disparam gatilhos

        return $e;
    }

    /** Estado salvo por uma versão antiga do jogo (2 jogadores, sem shots nem feitiços no campo): completa o que falta. */
    public static function completar(array $e): array
    {
        $e['fase'] ??= 'principal';
        $e['ataque'] ??= null;
        $e['fila'] ??= [];
        $e['escolha'] ??= null;
        $e['atacou'] ??= false;
        $e['desde'] ??= time();
        $e['apressado'] ??= null;
        $e['iniciado'] ??= true;
        if (($e['hv'] ?? 0) !== GladioTexto::VERSAO) { // cartas lidas por uma versão antiga do leitor (ou nunca lidas)
            $e['cartas'] = self::lerTextos($e['cartas']);
            $e['hv'] = GladioTexto::VERSAO;
        }
        $e['ordem'] ??= array_values(array_intersect(self::LADOS, array_keys($e['lados'])));
        $e['nomes'] ??= [];
        foreach ($e['ordem'] as $lado) {
            $e['nomes'][$lado] ??= strtoupper($lado);
            $l = &$e['lados'][$lado];
            $l['cemiterio'] ??= [];
            $l['feiticos'] ??= [];
            $l['shots'] ??= 0;
            $l['fora'] ??= false;
            $l['comando'] ??= [];
            $l['cmd'] ??= null;
            $l['taxa'] ??= 0;
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

    /** Lê o texto de cada carta: hab (efeitos automáticos), parcial (sobrou texto para resolver à mão) e auto (resumo). */
    private static function lerTextos(array $cartas): array
    {
        $nomes = array_values(array_unique(array_map(fn ($c) => GladioTexto::normalizar((string) ($c['nome'] ?? '')), $cartas)));
        foreach ($cartas as $id => $c) {
            // efeito fixo do criador antigo: só vale para carta sem texto (com texto, quem manda é o texto; várias
            // cartas antigas ficaram com "dano 1" guardado e o texto nunca era aplicado)
            if (!empty($c['efeito']) && trim((string) ($c['descricao'] ?? '')) === '') {
                $cartas[$id]['hab'] = [];
                $cartas[$id]['parcial'] = false;
                $cartas[$id]['auto'] = '';
                continue;
            }
            $r = GladioTexto::ler($c['descricao'] ?? '', (string) ($c['tipo'] ?? 'lenda'), (string) ($c['nome'] ?? ''), $nomes);
            if (($c['tipo'] ?? '') === 'equipo' && !$r['hab']) { // equipamento sem texto: o bônus vem dos campos e palavras-chave
                $r['hab'] = [['g' => 'conjurar', 'passos' => [['a' => 'equipar', 'alvo' => 'lenda', 'filtro' => 'meu', 'atk' => 0, 'vida' => 0, 'pal' => []]]]];
            }
            $cartas[$id]['hab'] = $r['hab'];
            $cartas[$id]['parcial'] = $r['parcial'];
            $cartas[$id]['auto'] = GladioTexto::resumo($r['hab']);
        }

        return $cartas;
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
        unset($l);

        return self::evento($e, 'comprar', ['lado' => $lado, 'i' => (int) $carta['i']]);
    }

    /** Tira um jogador da partida: as cartas dele na mesa vão para o cemitério e, se só sobrar um, ele vence. */
    private static function eliminar(array $e, string $lado, string $motivo): array
    {
        if (!empty($e['lados'][$lado]['fora']) || $e['fim']) {
            return $e;
        }
        $l = &$e['lados'][$lado];
        $l['fora'] = true;
        $mesa = $l['mesa'];
        foreach ($l['feiticos'] as $f) {
            $l['cemiterio'][] = ['i' => $f['i'], 'c' => $f['c']];
        }
        $l['mesa'] = [];
        $l['feiticos'] = [];
        unset($l);
        foreach ($mesa as $m) {
            $e = self::saiDaMesa($e, $lado, $m, false);
        }
        self::log($e, self::quem($e, $lado) . ' está fora da partida (' . $motivo . ').');

        // efeitos de quem saiu (e a escolha que ele devia fazer) não acontecem mais
        $e['fila'] = array_values(array_filter($e['fila'] ?? [], fn ($ef) => $ef['lado'] !== $lado));
        if (($e['escolha']['lado'] ?? null) === $lado) {
            $e['escolha'] = null;
        }

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
            $e['fila'] = [];
            $e['escolha'] = null;
            self::log($e, self::quem($e, $vivos[0]) . ' venceu a partida.');
        } elseif ($e['vez'] === $lado) {
            $e = self::comecarTurno($e, self::proximo($e, $lado));
        }

        return $e;
    }

    /**
     * Uma Lenda que saiu da mesa: o comandante volta para a zona de comando, fichas somem e as outras vão ao cemitério.
     * $avisar: ela caiu ou foi sacrificada (dispara o "quando morrer"); false quando o dono sai da partida.
     */
    private static function saiDaMesa(array $e, string $lado, array $m, bool $avisar = true): array
    {
        $e = self::soltarEquipamentos($e, (int) $m['i']);
        if ($avisar) { // "toda vez que uma Lenda (sua) morrer" das outras cartas em jogo
            $e = self::evento($e, 'morre', ['lado' => $lado, 'i' => (int) $m['i']]);
        }
        if ($m['c'] === null) {
            return $e;
        }
        if ($avisar) {
            $e = self::enfileirar($e, $lado, $m['c'], (int) $m['i'], 'morrer');
        }
        $carta = ['i' => $m['i'], 'c' => $m['c']];
        if ($m['i'] === ($e['lados'][$lado]['cmd'] ?? null)) {
            $e['lados'][$lado]['comando'][] = $carta;
            if ($avisar) {
                self::log($e, self::nome($e, $m['c']) . ' é o comandante de ' . self::quem($e, $lado) . ' e voltou para a zona de comando.');
            }
        } else {
            $e['lados'][$lado]['cemiterio'][] = $carta;
        }

        return $e;
    }

    /**
     * Lendas sem vida (ou marcadas por Toque mortal) vão para o cemitério (fichas somem) e quem ficou sem vida sai.
     * Indestrutível não cai: a vida fica em pelo menos 1. Se todos caírem juntos, vence quem agiu.
     */
    private static function conferir(array $e, string $quem): array
    {
        $caidas = [];
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
                $caidas[] = [$lado, $m];
            }
            $e['lados'][$lado]['mesa'] = $vivas;
        }
        foreach ($caidas as [$lado, $m]) {
            $e = self::saiDaMesa($e, $lado, $m);
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
        self::exigirSemEscolha($e);
        if ($e['fase'] === 'defesa') {
            throw new \InvalidArgumentException('Espere o adversário escolher a defesa.');
        }
        if ($e['fase'] !== 'principal') {
            throw new \InvalidArgumentException('Agora não é a fase principal.');
        }
    }

    /** Com uma escolha pendente (alvo de um efeito, descarte do fim do turno), o jogo espera por ela. */
    private static function exigirSemEscolha(array $e): void
    {
        if (!empty($e['escolha'])) {
            throw new \InvalidArgumentException('Espere: ' . self::quem($e, $e['escolha']['lado']) . ' precisa escolher (' . $e['escolha']['texto'] . ').');
        }
    }

    private static function exigirDefesa(array $e, string $lado): void
    {
        self::exigirAtivo($e, $lado);
        self::exigirSemEscolha($e);
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
     * Só no próprio turno, na fase principal (nem Lendas no turno dos outros). $gratis: sem pagar energia.
     * Feitiço sem efeito próprio: $efeitoEscolhido/$valorEscolhido são o que a descrição pede, escolhidos por quem joga
     * (fica registrado); sem escolha, ele só é conjurado. Depois o feitiço fica no campo do dono.
     */
    public static function jogar(array $e, string $lado, int $inst, mixed $alvo = null, bool $gratis = false,
                                 ?string $efeitoEscolhido = null, int $valorEscolhido = 0): array
    {
        self::exigirTurno($e, $lado); // só no próprio turno: nem Lendas no turno dos outros
        $defendendo = false;
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
        $custo = (int) $carta['custo']; // sempre pagando: não existe jogar de graça à mão ($gratis é ignorado)
        if ($custo > $l['energia']) {
            throw new \InvalidArgumentException('Energia insuficiente.');
        }
        $nome = $carta['nome'];
        $quem = self::quem($e, $lado);
        $semCusto = '';

        if ($carta['tipo'] === 'lenda') {
            $pal = self::palavrasDe($e, $id);
            $l['mesa'][] = ['i' => $l['mao'][$k]['i'], 'c' => $id, 'atk' => (int) $carta['ataque'], 'vida' => (int) $carta['vida'],
                            'max' => (int) $carta['vida'], 'pode' => !$defendendo && in_array('impeto', $pal, true), 'virada' => false,
                            'pal' => $pal, 'cont' => 0, 'nota' => ''];
            $inst = (int) $l['mao'][$k]['i'];
            array_splice($l['mao'], $k, 1);
            $l['energia'] -= $custo;
            unset($l);
            self::log($e, ($defendendo ? "Na defesa, $quem invocou $nome" : "$quem invocou $nome") . "$semCusto.");

            return self::seguir(self::enfileirar($e, $lado, $id, $inst, 'entrar'));
        }

        // feitiço com texto entendido pelo motor: os efeitos vão para a fila e ele vai para o cemitério
        // (fica no campo se ainda tiver gatilho de início/fim de turno)
        $hab = $e['cartas'][$id]['hab'] ?? [];
        if ($hab) { // o texto manda (o efeito fixo antigo só vale para carta sem texto)
            $inst = (int) $l['mao'][$k]['i'];
            // Encantamento e Equipamento ficam no campo; Feitiço só fica se tiver gatilho de turno/morte (senão, cemitério)
            $fica = in_array($carta['tipo'], ['encanto', 'equipo'], true)
                || (bool) array_filter($hab, fn ($h) => $h['g'] !== 'conjurar' || in_array('equipar', array_column($h['passos'], 'a'), true));
            if ($fica) {
                $l['feiticos'][] = ['i' => $inst, 'c' => $id, 'cont' => 0, 'nota' => ''];
            } else {
                $l['cemiterio'][] = ['i' => $inst, 'c' => $id];
            }
            array_splice($l['mao'], $k, 1);
            $l['energia'] -= $custo;
            unset($l);
            self::log($e, "$quem " . self::verbo($carta['tipo']) . " $nome$semCusto.");
            $e = self::enfileirar($e, $lado, $id, $inst, 'conjurar');

            // "sempre que um feitiço for conjurado" das outras cartas em jogo
            return self::seguir(self::evento($e, 'feitico', ['lado' => $lado, 'i' => $inst]));
        }

        // feitiço sem texto entendido: só o efeito fixo antigo de uma carta sem texto; quem joga não escolhe efeito à mão
        $efeito = trim((string) ($carta['descricao'] ?? '')) === '' ? ($carta['efeito'] ?? null) : null;
        $valor = max(0, (int) ($carta['valor'] ?? 0));
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

        // Feitiço resolve e vai para o cemitério; Encantamento e Equipamento ficam no campo
        $inst = (int) $l['mao'][$k]['i'];
        if ($carta['tipo'] === 'feitico') {
            $l['cemiterio'][] = ['i' => $inst, 'c' => $id];
        } else {
            $l['feiticos'][] = ['i' => $inst, 'c' => $id, 'cont' => 0, 'nota' => ''];
        }
        array_splice($l['mao'], $k, 1);
        $l['energia'] -= $custo;
        unset($l);
        if (!$efeito) { // o jogo ainda não sabe aplicar o texto desta carta
            self::log($e, "$quem " . self::verbo($carta['tipo']) . " $nome$semCusto (o efeito do texto ainda não é aplicado pelo jogo).");

            return self::seguir(self::evento($e, 'feitico', ['lado' => $lado, 'i' => $inst]));
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

        return self::seguir($e);
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
        if (!empty($e['atacou'])) {
            throw new \InvalidArgumentException('Você já atacou neste turno: é uma onda de ataque por turno.');
        }
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
            if (!empty($m['preso'])) {
                throw new \InvalidArgumentException(self::nomeLenda($e, $m) . ' não pode mais atacar.');
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
        $e['atacou'] = true; // uma onda de ataque por turno: declarou, atacou, acabou
        $e['desde'] = time(); // o cronômetro passa a contar a defesa
        $e['ataque'] = ['atacantes' => $insts, 'defensor' => $alvo, 'bloqueios' => (object) []];
        self::log($e, self::quem($e, $lado) . ' atacou ' . self::quem($e, $alvo) . ' com ' . implode(', ', $nomes) . '.');
        foreach ($insts as $i) { // "quando atacar"
            $m = $e['lados'][$lado]['mesa'][self::idx($e['lados'][$lado]['mesa'], $i)];
            if ($m['c'] !== null) {
                $e = self::enfileirar($e, $lado, $m['c'], $i, 'atacar');
            }
        }

        return self::seguir($e);
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

    /**
     * Recupera vida (Dinheiro): sai do pote enquanto houver; com o pote vazio, o Dinheiro é dado mesmo assim (entra
     * dinheiro novo na mesa: o total sobe) e a vida pode passar do que foi apostado.
     */
    private static function curar(array &$e, string $lado, int $quanto): void
    {
        if ($quanto <= 0) {
            return;
        }
        $falta = max(0, $quanto - self::pote($e));
        $e['total'] += $falta;
        $l = &$e['lados'][$lado];
        $l['vida'] += $quanto;
        $l['max'] = max($l['max'], $l['vida']);
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
                foreach ($bloq as $kb) { // "quando bloquear": o evento é o atacante
                    $b = $e['lados'][$lado]['mesa'][$kb];
                    $e = self::enfileirar($e, $lado, $b['c'] ?? null, (int) $b['i'], 'bloquear', ['lado' => $at, 'i' => $ai]);
                }
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
                        if (empty($diretoFeito[$ai])) { // "quando atacar um jogador diretamente": o evento é o jogador atacado
                            $diretoFeito[$ai] = true;
                            $e = self::enfileirar($e, $at, $a['c'] ?? null, $ai, 'direto', ['lado' => $lado, 'i' => null]);
                        }
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
            // "quando matar uma Lenda": quem derrubou o adversário no combate (o evento é a Lenda morta)
            $caiu = fn ($m) => !self::tem($m, 'indestrutivel') && ($m['vida'] <= 0 || !empty($m['morto']));
            $atacante = $e['lados'][$at]['mesa'][$ka];
            foreach ($bloq as $kb) {
                $b = $e['lados'][$lado]['mesa'][$kb];
                if ($caiu($b) && !$caiu($atacante)) {
                    $e = self::enfileirar($e, $at, $atacante['c'] ?? null, $ai, 'matar', ['lado' => $lado, 'i' => (int) $b['i']]);
                }
                if ($caiu($atacante) && !$caiu($b) && $b['atk'] > 0) {
                    $e = self::enfileirar($e, $lado, $b['c'] ?? null, (int) $b['i'], 'matar', ['lado' => $at, 'i' => $ai]);
                }
            }
        }

        $e['fase'] = 'principal';
        $e['ataque'] = null;
        $e['desde'] = time(); // volta a contar a vez de quem atacou

        return self::seguir($e);
    }

    /**
     * Começa o turno de $lado na fase de Compra: +1 de energia máxima (até 10), recarrega, as Lendas dele desviram e os
     * gatilhos de início de turno entram na fila. A compra em si acontece em seguir(), depois desses gatilhos.
     */
    private static function comecarTurno(array $e, string $lado): array
    {
        $e['vez'] = $lado;
        $e['turno']++;
        $e['fase'] = 'compra';
        $e['ataque'] = null;
        $e['atacou'] = false;
        $e['desde'] = time(); // cronômetro da vez
        $e['apressado'] = null;
        $l = &$e['lados'][$lado];
        if ($l['energiaMax'] < self::ENERGIA_MAX) { // energia máxima ajustada à mão acima de 10 não cai
            $l['energiaMax']++;
        }
        $l['energia'] = $l['energiaMax'];
        foreach ($l['mesa'] as $i => $m) {
            $l['mesa'][$i]['pode'] = empty($m['preso']); // presa ("não pode atacar até morrer") não volta a atacar
            $l['mesa'][$i]['virada'] = false;
        }
        unset($l);

        return self::gatilhosDeTurno($e, $lado, 'inicio');
    }

    /**
     * Termina a fase principal: vai para a Final (gatilhos de fim de turno, descarte até MAO_FIM e limpeza) e, sem
     * nada para escolher, passa a vez para o próximo jogador de pé, na roda.
     */
    public static function passar(array $e, string $lado): array
    {
        self::exigirTurno($e, $lado);
        $e['fase'] = 'final';

        return self::seguir(self::gatilhosDeTurno($e, $lado, 'fim'));
    }

    /** Desiste: sai da partida (se sobrar um só, ele vence). */
    public static function desistir(array $e, string $lado): array
    {
        self::exigirAtivo($e, $lado);

        return self::seguir(self::eliminar($e, $lado, 'desistência'));
    }

    /** Segundos de vez antes de os outros poderem apressar quem está demorando. */
    public const APRESSAR_APOS = 30;
    /** Tempo de cada vez (turno, defesa ou escolha): acabou, a mesa segue sozinha (ver tempo()). */
    public const TEMPO_VEZ = 90;

    /**
     * O tempo da vez acabou: quem está jogando passa o turno, quem defende confirma a defesa como está e quem precisava
     * escolher pula (ou, se não dá para pular, fica com a primeira opção). Qualquer jogador da mesa pode pedir isso.
     */
    public static function tempo(array $e, string $lado, ?int $agora = null): array
    {
        self::exigirAtivo($e, $lado);
        $agora ??= time();
        if ($agora - (int) ($e['desde'] ?? $agora) < self::TEMPO_VEZ) {
            throw new \InvalidArgumentException('Ainda há tempo nesta vez.');
        }
        $quem = self::esperando($e);
        self::log($e, '⏰ Acabou o tempo de ' . self::quem($e, $quem) . '.');
        if ($e['escolha']) {
            $es = $e['escolha'];
            $e = self::escolher($e, $es['lado'], $es['obrig'] ? $es['opcoes'][0] : null);
        } elseif ($e['fase'] === 'defesa') {
            $e = self::defender($e, $e['ataque']['defensor']);
        } elseif ($e['fase'] === 'principal') {
            $e = self::passar($e, $e['vez']);
        }
        $e['desde'] = $agora; // o relógio volta a contar para quem a mesa espera agora

        return $e;
    }

    /** De quem a mesa está esperando agora: quem escolhe um efeito, quem defende ou quem tem a vez. */
    public static function esperando(array $e): string
    {
        return $e['escolha']['lado'] ?? ($e['fase'] === 'defesa' ? ($e['ataque']['defensor'] ?? $e['vez']) : $e['vez']);
    }

    /** Outro jogador apressa quem está demorando (depois de APRESSAR_APOS segundos; de novo só a cada 20 s). */
    public static function apressar(array $e, string $lado, ?int $agora = null): array
    {
        self::exigirAtivo($e, $lado);
        $agora ??= time();
        $alvo = self::esperando($e);
        if ($alvo === $lado) {
            throw new \InvalidArgumentException('A mesa está esperando você.');
        }
        if ($agora - (int) ($e['desde'] ?? $agora) < self::APRESSAR_APOS) {
            throw new \InvalidArgumentException('Ainda está no tempo: dá para apressar depois de ' . self::APRESSAR_APOS . ' segundos.');
        }
        if (!empty($e['apressado']) && $agora - (int) $e['apressado']['em'] < 20) {
            throw new \InvalidArgumentException(self::quem($e, $alvo) . ' acabou de ser apressado.');
        }
        $e['apressado'] = ['por' => $lado, 'alvo' => $alvo, 'em' => $agora];
        self::log($e, self::quem($e, $lado) . ' está apressando ' . self::quem($e, $alvo) . ': ⏳ anda logo!');

        return $e;
    }

    /** Toma (ou devolve, com $quanto negativo) shots, a qualquer momento. No 7º shot o jogador está fora. */
    public static function shot(array $e, string $lado, int $quanto = 1): array
    {
        self::exigirAtivo($e, $lado);

        return self::seguir(self::beber($e, $lado, $quanto));
    }

    private static function beber(array $e, string $lado, int $quanto): array
    {
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
        self::exigirAtivo($e, $lado);
        $e = self::comprarUma($e, $lado);
        self::log($e, self::quem($e, $lado) . ' comprou uma carta (ação manual).');

        return self::seguir($e);
    }

    public static function embaralhar(array $e, string $lado, ?callable $embaralhar = null): array
    {
        self::exigirAtivo($e, $lado);
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
        self::exigirAtivo($e, $lado);
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
        self::exigirAtivo($e, $lado);
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
        self::exigirAtivo($e, $lado);
        $nome = trim($nome) !== '' ? mb_substr(trim($nome), 0, 40) : 'Ficha';
        if ($vida < 1) {
            throw new \InvalidArgumentException('A ficha precisa de pelo menos 1 de vida.');
        }
        $atk = max(0, min(9999, $atk));
        $vida = min(9999, $vida);
        $e['lados'][$lado]['mesa'][] = ['i' => ++$e['seq'], 'c' => null, 'nome' => $nome, 'atk' => $atk, 'vida' => $vida,
                                        'max' => $vida, 'pode' => false, 'virada' => false, 'pal' => [], 'cont' => 0, 'nota' => ''];
        self::log($e, self::quem($e, $lado) . " criou a ficha $nome ($atk/$vida).");

        return $e;
    }

    /** Ajusta ataque e vida de qualquer Lenda na mesa (sua ou de um adversário), a qualquer momento. Vida 0: ela cai. */
    /** Dá (ou tira, se ela já tiver) uma palavra-chave a qualquer Lenda na mesa, a qualquer momento (mesa livre). */
    public static function palavra(array $e, string $lado, int $inst, string $pal): array
    {
        self::exigirAtivo($e, $lado);
        if (!isset(self::PALAVRAS[$pal])) {
            throw new \InvalidArgumentException('Palavra-chave desconhecida.');
        }
        $onde = self::acharLenda($e, $inst);
        if (!$onde) {
            throw new \InvalidArgumentException('Essa Lenda não está na mesa.');
        }
        [$dono, $k] = $onde;
        $m = &$e['lados'][$dono]['mesa'][$k];
        $tinha = in_array($pal, $m['pal'] ?? [], true);
        $m['pal'] = $tinha ? array_values(array_diff($m['pal'], [$pal])) : array_merge($m['pal'] ?? [], [$pal]);
        if (!$tinha && $pal === 'impeto' && $dono === $e['vez'] && empty($m['virada'])) {
            $m['pode'] = true; // Ímpeto recebido no turno: já pode atacar
        }
        $nome = self::nomeLenda($e, $m) . ($dono === $lado ? '' : ' de ' . self::quem($e, $dono));
        unset($m);
        self::log($e, self::quem($e, $lado) . ($tinha ? ' tirou ' : ' deu ') . self::PALAVRAS[$pal] . ($tinha ? ' de ' : ' a ') . "$nome.");

        return self::seguir($e);
    }

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

        return self::seguir($e);
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
        self::exigirAtivo($e, $lado);
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

    /** Manda uma Lenda sua para o cemitério (fichas somem; o comandante volta para a zona de comando). */
    public static function sacrificar(array $e, string $lado, int $inst): array
    {
        self::exigirAtivo($e, $lado);
        $k = self::idx($e['lados'][$lado]['mesa'], $inst);
        if ($k < 0) {
            throw new \InvalidArgumentException('Essa Lenda não está na sua mesa.');
        }
        $m = $e['lados'][$lado]['mesa'][$k];
        array_splice($e['lados'][$lado]['mesa'], $k, 1);
        self::log($e, self::quem($e, $lado) . ' sacrificou ' . self::nomeLenda($e, $m) . '.');

        return self::seguir(self::saiDaMesa($e, $lado, $m));
    }

    /** Manda um feitiço seu que está no campo para o cemitério (a qualquer momento). */
    public static function encerrarFeitico(array $e, string $lado, int $inst): array
    {
        self::exigirAtivo($e, $lado);
        $k = self::idx($e['lados'][$lado]['feiticos'], $inst);
        if ($k < 0) {
            throw new \InvalidArgumentException('Esse feitiço não está no seu campo.');
        }
        $e = self::desequipar($e, $lado, $inst); // o bônus do equipamento sai da Lenda
        $f = $e['lados'][$lado]['feiticos'][$k];
        array_splice($e['lados'][$lado]['feiticos'], $k, 1);
        $e['lados'][$lado]['cemiterio'][] = ['i' => $f['i'], 'c' => $f['c']];
        self::log($e, self::quem($e, $lado) . ' mandou ' . self::nome($e, $f['c']) . ' para o cemitério.');

        return self::seguir($e);
    }

    /* ================================================================== */
    /*  Equipamentos (feitiço vinculado a uma Lenda)                       */
    /* ================================================================== */

    private static function verbo(string $tipo): string
    {
        return ['encanto' => 'pôs em jogo o encantamento', 'equipo' => 'pôs em jogo o equipamento'][$tipo] ?? 'conjurou';
    }

    /** Bônus que o texto do feitiço dá à Lenda equipada: { atk, vida, pal[] } (vazio para feitiço sem texto de equipar). */
    private static function bonusDe(array $e, ?string $c): array
    {
        $b = ['atk' => 0, 'vida' => 0, 'pal' => self::palavrasDe($e, $c)];
        if (($e['cartas'][$c]['tipo'] ?? '') === 'equipo') { // os campos de ataque/vida do Equipamento são o bônus
            $b['atk'] += (int) ($e['cartas'][$c]['ataque'] ?? 0);
            $b['vida'] += (int) ($e['cartas'][$c]['vida'] ?? 0);
        }
        foreach ($e['cartas'][$c]['hab'] ?? [] as $h) {
            foreach ($h['passos'] as $p) {
                if ($p['a'] === 'equipar') {
                    $b['atk'] += (int) ($p['atk'] ?? 0);
                    $b['vida'] += (int) ($p['vida'] ?? 0);
                    $b['pal'] = array_values(array_unique(array_merge($b['pal'], (array) ($p['pal'] ?? []))));

                    return $b;
                }
            }
        }

        return $b;
    }

    /**
     * Vincula o feitiço $fi (no campo de $lado) à Lenda $li de $lado: ela recebe o bônus do equipamento e, quando sai da
     * mesa, o equipamento vai junto para o cemitério. Já equipado em outra, troca de Lenda.
     */
    private static function vincular(array $e, string $lado, int $fi, int $li): array
    {
        $kf = self::idx($e['lados'][$lado]['feiticos'], $fi);
        $kl = self::idx($e['lados'][$lado]['mesa'], $li);
        if ($kf < 0 || $kl < 0) {
            throw new \InvalidArgumentException('Equipe um feitiço do seu campo numa Lenda sua.');
        }
        $e = self::desequipar($e, $lado, $fi);
        $f = &$e['lados'][$lado]['feiticos'][$kf];
        $m = &$e['lados'][$lado]['mesa'][$kl];
        $b = self::bonusDe($e, $f['c']);
        $novas = array_values(array_diff(array_intersect($b['pal'], array_keys(self::PALAVRAS)), $m['pal'] ?? []));
        $m['atk'] = max(0, $m['atk'] + $b['atk']);
        $m['vida'] += $b['vida'];
        $m['max'] = max(1, $m['max'] + $b['vida']);
        $m['pal'] = array_values(array_merge($m['pal'] ?? [], $novas));
        $f['em'] = $li;
        $f['bonus'] = ['atk' => $b['atk'], 'vida' => $b['vida'], 'pal' => $novas];
        $nomeF = self::nome($e, $f['c']);
        $nomeL = self::nomeLenda($e, $m);
        unset($f, $m);
        $txt = ($b['atk'] || $b['vida'] ? sprintf(' (%+d/%+d', $b['atk'], $b['vida']) : '')
            . ($novas ? ($b['atk'] || $b['vida'] ? ', ' : ' (') . implode(', ', array_map(fn ($p) => self::PALAVRAS[$p], $novas)) : '')
            . ($b['atk'] || $b['vida'] || $novas ? ')' : '');
        self::log($e, self::quem($e, $lado) . " equipou $nomeF em $nomeL$txt.");

        return $e;
    }

    /** Tira o feitiço $fi de $lado da Lenda em que estava equipado (o bônus some). */
    private static function desequipar(array $e, string $lado, int $fi): array
    {
        $kf = self::idx($e['lados'][$lado]['feiticos'], $fi);
        if ($kf < 0 || empty($e['lados'][$lado]['feiticos'][$kf]['em'])) {
            return $e;
        }
        $f = $e['lados'][$lado]['feiticos'][$kf];
        if ($onde = self::acharLenda($e, (int) $f['em'])) {
            $m = &$e['lados'][$onde[0]]['mesa'][$onde[1]];
            $b = $f['bonus'] ?? ['atk' => 0, 'vida' => 0, 'pal' => []];
            $m['atk'] = max(0, $m['atk'] - (int) $b['atk']);
            $m['max'] = max(1, $m['max'] - (int) $b['vida']);
            $m['vida'] = min($m['vida'] - (int) $b['vida'], $m['max']); // perder a vida extra pode derrubá-la
            $m['pal'] = array_values(array_diff($m['pal'] ?? [], (array) ($b['pal'] ?? [])));
            unset($m);
        }
        unset($e['lados'][$lado]['feiticos'][$kf]['em'], $e['lados'][$lado]['feiticos'][$kf]['bonus']);

        return $e;
    }

    /** A Lenda $li saiu da mesa: os equipamentos vinculados a ela vão para o cemitério de quem os controla. */
    private static function soltarEquipamentos(array $e, int $li): array
    {
        foreach ($e['ordem'] as $lado) {
            foreach ($e['lados'][$lado]['feiticos'] as $k => $f) {
                if ((int) ($f['em'] ?? 0) === $li) {
                    unset($e['lados'][$lado]['feiticos'][$k]);
                    $e['lados'][$lado]['cemiterio'][] = ['i' => $f['i'], 'c' => $f['c']];
                    self::log($e, self::nome($e, $f['c']) . ' estava equipado nela e foi para o cemitério.');
                }
            }
            $e['lados'][$lado]['feiticos'] = array_values($e['lados'][$lado]['feiticos']);
        }

        return $e;
    }

    /** Equipa (ou troca de Lenda) um feitiço seu do campo numa Lenda sua, no seu turno. */
    public static function equipar(array $e, string $lado, int $feitico, int $lenda): array
    {
        self::exigirTurno($e, $lado);

        return self::seguir(self::vincular($e, $lado, $feitico, $lenda));
    }

    /* ================================================================== */
    /*  Comandante e mesa livre                                            */
    /* ================================================================== */

    /** Custo para invocar o comandante da zona de comando agora: o da carta + 2 por vez que ele já saiu de lá. */
    public static function custoComandante(array $e, string $lado): ?int
    {
        $c = $e['lados'][$lado]['comando'][0]['c'] ?? null;

        return $c === null ? null : (int) ($e['cartas'][$c]['custo'] ?? 0) + self::TAXA_COMANDANTE * (int) ($e['lados'][$lado]['taxa'] ?? 0);
    }

    /** Põe uma carta {i, c} em jogo: Lenda na mesa, feitiço no campo. */
    private static function entrar(array $e, string $lado, array $carta): array
    {
        $def = $e['cartas'][$carta['c']] ?? null;
        if (!$def) {
            throw new \InvalidArgumentException('Carta desconhecida.');
        }
        if ($def['tipo'] !== 'lenda') {
            $e['lados'][$lado]['feiticos'][] = ['i' => $carta['i'], 'c' => $carta['c'], 'cont' => 0, 'nota' => ''];

            return $e;
        }
        $pal = self::palavrasDe($e, $carta['c']);
        $e['lados'][$lado]['mesa'][] = ['i' => $carta['i'], 'c' => $carta['c'], 'atk' => (int) $def['ataque'], 'vida' => (int) $def['vida'],
            'max' => (int) $def['vida'], 'pode' => in_array('impeto', $pal, true), 'virada' => false, 'pal' => $pal, 'cont' => 0, 'nota' => ''];

        return self::enfileirar($e, $lado, $carta['c'], (int) $carta['i'], 'entrar');
    }

    /** Invoca o comandante da zona de comando no próprio turno, pagando o custo com a taxa (+2 a cada vez). */
    public static function comandar(array $e, string $lado): array
    {
        self::exigirTurno($e, $lado);
        $custo = self::custoComandante($e, $lado);
        if ($custo === null) {
            throw new \InvalidArgumentException('Seu comandante não está na zona de comando.');
        }
        if ($custo > $e['lados'][$lado]['energia']) {
            throw new \InvalidArgumentException("Energia insuficiente: o comandante custa $custo agora.");
        }
        $carta = $e['lados'][$lado]['comando'][0];
        $e = self::entrar($e, $lado, $carta);
        array_shift($e['lados'][$lado]['comando']);
        $e['lados'][$lado]['energia'] -= $custo;
        $e['lados'][$lado]['taxa']++;
        self::log($e, self::quem($e, $lado) . ' invocou o comandante ' . self::nome($e, $carta['c']) . " da zona de comando (custo $custo).");

        return self::seguir($e);
    }

    /**
     * Invoca sem pagar, a qualquer momento, uma carta da própria mão, do cemitério ou da zona de comando
     * (do comando, a taxa do comandante sobe do mesmo jeito). Lendas entram na mesa; feitiços, no campo, sem efeito automático.
     */
    public static function invocar(array $e, string $lado, int $inst, string $origem): array
    {
        self::exigirAtivo($e, $lado);
        $zonas = ['mao' => 'da mão', 'cemiterio' => 'do cemitério', 'comando' => 'da zona de comando'];
        if (!isset($zonas[$origem])) {
            throw new \InvalidArgumentException('De onde vem a carta?');
        }
        $k = self::idx($e['lados'][$lado][$origem], $inst);
        if ($k < 0) {
            throw new \InvalidArgumentException('Essa carta não está aí.');
        }
        $carta = $e['lados'][$lado][$origem][$k];
        if (($e['cartas'][$carta['c']]['tipo'] ?? '') === 'lenda' && $e['vez'] !== $lado) {
            throw new \InvalidArgumentException('Lendas só entram em jogo no seu turno.');
        }
        $e = self::entrar($e, $lado, ['i' => $carta['i'], 'c' => $carta['c']]);
        array_splice($e['lados'][$lado][$origem], $k, 1);
        if ($origem === 'comando') {
            $e['lados'][$lado]['taxa']++;
        }
        self::log($e, self::quem($e, $lado) . ' invocou ' . self::nome($e, $carta['c']) . ' ' . $zonas[$origem] . ' sem pagar energia.');

        return self::seguir($e);
    }

    /** Busca uma carta escolhida no próprio baralho e põe na mão (a qualquer momento). */
    public static function buscar(array $e, string $lado, int $inst): array
    {
        self::exigirAtivo($e, $lado);
        $k = self::idx($e['lados'][$lado]['baralho'], $inst);
        if ($k < 0) {
            throw new \InvalidArgumentException('Essa carta não está no seu baralho.');
        }
        if (count($e['lados'][$lado]['mao']) >= self::MAO_MAX) {
            throw new \InvalidArgumentException('Sua mão está cheia.');
        }
        $c = $e['lados'][$lado]['baralho'][$k];
        array_splice($e['lados'][$lado]['baralho'], $k, 1);
        $e['lados'][$lado]['mao'][] = $c;
        self::log($e, self::quem($e, $lado) . ' buscou ' . self::nome($e, $c['c']) . ' no baralho e pôs na mão.');

        return $e;
    }

    /** Devolve o comandante do próprio cemitério para a zona de comando. */
    public static function paraComando(array $e, string $lado, int $inst): array
    {
        self::exigirAtivo($e, $lado);
        $k = self::idx($e['lados'][$lado]['cemiterio'], $inst);
        if ($k < 0 || $inst !== ($e['lados'][$lado]['cmd'] ?? null)) {
            throw new \InvalidArgumentException('Só o comandante volta para a zona de comando.');
        }
        $c = $e['lados'][$lado]['cemiterio'][$k];
        array_splice($e['lados'][$lado]['cemiterio'], $k, 1);
        $e['lados'][$lado]['comando'][] = $c;
        self::log($e, self::quem($e, $lado) . ' devolveu o comandante ' . self::nome($e, $c['c']) . ' para a zona de comando.');

        return $e;
    }

    /** Ajusta a própria energia (a de agora e a máxima), a qualquer momento. */
    public static function energia(array $e, string $lado, int $dAtual, int $dMax): array
    {
        self::exigirAtivo($e, $lado);
        $l = &$e['lados'][$lado];
        $l['energiaMax'] = max(0, min(self::ENERGIA_LIMITE, $l['energiaMax'] + $dMax));
        $l['energia'] = max(0, min(self::ENERGIA_LIMITE, $l['energia'] + $dAtual));
        $txt = "{$l['energia']}/{$l['energiaMax']}";
        unset($l);
        self::log($e, self::quem($e, $lado) . " ajustou a energia para $txt.");

        return $e;
    }

    /** Ajusta a própria vida (cura até o máximo; o que se perde vai para o pote). Sem vida, está fora. */
    public static function vida(array $e, string $lado, int $quanto): array
    {
        self::exigirAtivo($e, $lado);
        // ganhar sai do pote (ou é dinheiro novo, com o pote vazio) e pode passar do que foi apostado; perder vai para o pote
        $antes = $e['lados'][$lado]['vida'];
        if ($quanto > 0) {
            self::curar($e, $lado, $quanto);
        } else {
            $e['lados'][$lado]['vida'] += $quanto;
        }
        $agora = $e['lados'][$lado]['vida'];
        if ($agora === $antes) {
            return $e;
        }
        self::log($e, self::quem($e, $lado) . ($agora > $antes ? ' recuperou ' . ($agora - $antes) : ' perdeu ' . ($antes - $agora)) . " de vida ($agora).");

        return self::seguir($e);
    }

    /* ================================================================== */
    /*  Fases e efeitos automáticos dos textos                             */
    /* ================================================================== */

    /** Põe na fila os efeitos da carta $c (controlada por $lado) com o gatilho $g. */
    private static function enfileirar(array $e, string $lado, ?string $c, ?int $inst, string $g, ?array $ctx = null): array
    {
        if ($c === null) {
            return $e;
        }
        foreach ($e['cartas'][$c]['hab'] ?? [] as $h) {
            if ($h['g'] === $g && $h['passos']) {
                // ctx: quem causou o evento ({lado, i}): o jogador que conjurou, a Lenda que morreu, o atacante…
                $e['fila'][] = ['lado' => $lado, 'fonte' => self::nome($e, $c), 'i' => $inst, 'passos' => $h['passos'], 'jog' => null,
                                'ctx' => $ctx, 'ult' => null];
            }
        }

        return $e;
    }

    /**
     * Um evento aconteceu: dispara os gatilhos de todas as cartas em jogo que reagem a ele.
     * $tipo: morre | feitico | comprar. $ctx: { lado (de quem é o evento), i (Lenda/carta do evento) }.
     * Gatilhos: morre (qualquer Lenda), morre_minha (Lenda do mesmo dono), feitico (qualquer conjuração),
     * feitico_meu (o dono conjurou), feitico_outro (outro jogador conjurou), comprar_meu (o dono comprou).
     */
    private static function evento(array $e, string $tipo, array $ctx): array
    {
        if (empty($e['iniciado'])) { // as cartas da mão inicial não disparam nada
            return $e;
        }
        $mapa = [
            'morre'   => fn ($dono) => array_merge(['morre'], $dono === $ctx['lado'] ? ['morre_minha'] : []),
            'feitico' => fn ($dono) => array_merge(['feitico'], $dono === $ctx['lado'] ? ['feitico_meu'] : ['feitico_outro']),
            'comprar' => fn ($dono) => $dono === $ctx['lado'] ? ['comprar_meu'] : [],
        ][$tipo];
        foreach (self::vivos($e) as $dono) {
            foreach (array_merge($e['lados'][$dono]['mesa'], $e['lados'][$dono]['feiticos']) as $x) {
                if ((int) $x['i'] === (int) ($ctx['i'] ?? -1)) {
                    continue; // a carta do evento não reage a si mesma (ex.: o feitiço que acabou de ser conjurado)
                }
                foreach ($mapa($dono) as $g) {
                    $e = self::enfileirar($e, $dono, $x['c'] ?? null, (int) $x['i'], $g, $ctx);
                }
            }
        }

        return $e;
    }

    /**
     * Gatilhos de início ou fim do turno de $vez. "Todo início de turno" vale só no início do turno do dono da carta
     * (como "no início do seu turno"); os de fim de turno "todo turno" disparam para todas as cartas em jogo.
     */
    private static function gatilhosDeTurno(array $e, string $vez, string $quando): array
    {
        $k = array_search($vez, $e['ordem'], true);
        $roda = array_merge(array_slice($e['ordem'], $k), array_slice($e['ordem'], 0, $k)); // quem joga primeiro
        foreach ($roda as $lado) {
            if (!empty($e['lados'][$lado]['fora'])) {
                continue;
            }
            foreach (array_merge($e['lados'][$lado]['mesa'], $e['lados'][$lado]['feiticos']) as $x) {
                if ($quando !== 'inicio' || $lado === $vez) {
                    $e = self::enfileirar($e, $lado, $x['c'] ?? null, (int) $x['i'], $quando);
                }
                if ($lado === $vez) {
                    $e = self::enfileirar($e, $lado, $x['c'] ?? null, (int) $x['i'], $quando . '_meu');
                }
            }
        }

        return $e;
    }

    /**
     * Faz o jogo andar depois de cada ação: confere quem caiu, resolve a fila de efeitos (parando numa escolha) e passa
     * sozinho pelas fases de Compra (compra 1 e vai para a Principal) e Final (descarte até MAO_FIM, limpeza e próximo turno).
     */
    private static function seguir(array $e): array
    {
        for ($volta = 0; !$e['fim']; $volta++) {
            if ($volta > 300) { // efeitos que se disparam sem fim
                $e['fila'] = [];
                self::log($e, 'Efeitos demais em sequência: o resto foi cancelado.');
                break;
            }
            $e = self::conferir($e, $e['vez']);
            if ($e['fim'] || $e['escolha']) {
                break;
            }
            if ($e['fila']) {
                $e = self::umPasso($e);
                continue;
            }
            $vez = $e['vez'];
            if ($e['fase'] === 'compra') {
                $e['fase'] = 'principal';
                $antes = count($e['lados'][$vez]['mao']);
                $e = self::comprarUma($e, $vez);
                if (count($e['lados'][$vez]['mao']) > $antes) {
                    self::log($e, 'Turno ' . $e['turno'] . ' de ' . self::quem($e, $vez) . ': comprou uma carta.');
                }
                continue;
            }
            if ($e['fase'] === 'final') {
                $sobra = count($e['lados'][$vez]['mao']) - self::MAO_FIM;
                if ($sobra > 0) {
                    $e['fila'][] = ['lado' => $vez, 'fonte' => 'Fim do turno', 'i' => null, 'jog' => null,
                                    'passos' => [['a' => 'descartar', 'n' => $sobra, 'alvo' => 'eu', 'obrig' => true]]];
                    continue;
                }
                $e = self::comecarTurno(self::limpeza($e), self::proximo($e, $vez));
                continue;
            }
            break;
        }

        return $e;
    }

    /** Limpeza do fim do turno: o dano nas Lendas some e o que valia "até o fim do turno" acaba. */
    private static function limpeza(array $e): array
    {
        foreach ($e['ordem'] as $lado) {
            foreach ($e['lados'][$lado]['mesa'] as $k => $m) {
                if (!empty($m['tmp'])) {
                    $base = self::palavrasDe($e, $m['c']);
                    $m['atk'] = max(0, $m['atk'] - (int) ($m['tmp']['atk'] ?? 0));
                    $m['max'] = max(1, $m['max'] - (int) ($m['tmp']['vida'] ?? 0));
                    $m['pal'] = array_values(array_filter($m['pal'] ?? [], fn ($p) => in_array($p, $base, true) || !in_array($p, $m['tmp']['pal'] ?? [], true)));
                    unset($m['tmp']);
                }
                $m['vida'] = $m['max'];
                $e['lados'][$lado]['mesa'][$k] = $m;
            }
        }

        return $e;
    }

    /** Resolve o próximo passo do primeiro efeito da fila. */
    private static function umPasso(array $e): array
    {
        $ef = $e['fila'][0];
        if (!$ef['passos'] || !empty($e['lados'][$ef['lado']]['fora'])) {
            array_shift($e['fila']);

            return $e;
        }
        $p = array_shift($ef['passos']);
        $e['fila'][0] = $ef;

        return self::executar($e, $p);
    }

    /** Para a partida até $quem escolher. Sem opção válida, o passo é pulado. */
    private static function pedir(array $e, array $p, string $quem, string $tipo, array $opcoes, string $texto): array
    {
        $fonte = $e['fila'][0]['fonte'] ?? 'Efeito';
        if (!$opcoes) {
            self::log($e, "$fonte: sem alvo válido ($texto).");

            return $e;
        }
        $e['escolha'] = ['lado' => $quem, 'tipo' => $tipo, 'opcoes' => array_values($opcoes), 'texto' => $texto,
                         'fonte' => $fonte, 'obrig' => !empty($p['obrig']), 'passo' => $p];

        return $e;
    }

    /** Lados afetados por um passo de jogador; null quando ainda falta escolher o jogador. */
    private static function ladosDo(array $e, string $alvo, string $dono): ?array
    {
        if (str_starts_with($alvo, 'lado:')) {
            $l = substr($alvo, 5);

            return empty($e['lados'][$l]['fora']) ? [$l] : [];
        }

        return match ($alvo) {
            'todos'    => self::vivos($e),
            'inimigos' => self::inimigos($e, $dono),
            // quem causou o evento (o jogador que conjurou, o dono da Lenda que morreu, o jogador atacado)
            'evento'   => ($j = $e['fila'][0]['ctx']['lado'] ?? null) !== null && empty($e['lados'][$j]['fora']) ? [$j] : [],
            'jogador'  => ($j = $e['fila'][0]['jog'] ?? null) !== null && empty($e['lados'][$j]['fora']) ? [$j] : null,
            default    => [$dono],
        };
    }

    /** Lendas que $dono pode escolher (filtro meu | inimigo | qualquer); Hexo-Imune protege das escolhas dos adversários. */
    private static function lendasEscolhiveis(array $e, string $dono, ?string $filtro): array
    {
        $ops = [];
        foreach (self::vivos($e) as $lado) {
            if (($filtro === 'meu' && $lado !== $dono) || ($filtro === 'inimigo' && $lado === $dono)) {
                continue;
            }
            foreach ($e['lados'][$lado]['mesa'] as $m) {
                if ($lado !== $dono && self::tem($m, 'resistencia')) {
                    continue;
                }
                $ops[] = (int) $m['i'];
            }
        }

        return $ops;
    }

    private static function cartasDe(array $e, array $lista, ?string $tipo): array
    {
        return array_values(array_map(fn ($c) => (int) $c['i'], array_filter($lista,
            fn ($c) => $tipo === null || ($tipo === 'feitico' ? ($e['cartas'][$c['c']]['tipo'] ?? '') !== 'lenda' : ($e['cartas'][$c['c']]['tipo'] ?? '') === $tipo))));
    }

    /** Aplica um passo de efeito (pode pedir uma escolha antes). */
    private static function executar(array $e, array $p): array
    {
        $ef = $e['fila'][0];
        $dono = $ef['lado'];
        $fonte = $ef['fonte'];
        $a = $p['a'];
        $esc = $p['escolhido'] ?? null;
        if (($p['n'] ?? null) === 'poder') { // "X, onde X é o poder dessa Lenda": o ataque atual da carta
            $onde = $ef['i'] !== null ? self::acharLenda($e, (int) $ef['i']) : null;
            $p['n'] = $onde ? (int) $e['lados'][$onde[0]]['mesa'][$onde[1]]['atk'] : 0;
        }
        $n = max(0, (int) ($p['n'] ?? 1));
        $alvo = (string) ($p['alvo'] ?? 'eu');

        // "você pode…": quem controla a carta confirma (recusar pula o resto da frase, em escolher())
        if ($a === 'confirmar') {
            if ($esc !== null) {
                return $e;
            }
            $prox = array_slice($ef['passos'], 0, (int) ($p['resto'] ?? 0));

            return self::pedir($e, $p, $dono, 'confirmar', ['sim'],
                "$fonte: você pode fazer isto: " . implode(', ', array_map([GladioTexto::class, 'textoPasso'], $prox)));
        }
        // jogador escolhido: com um só possível, a escolha é automática
        if ($alvo === 'jogador' && self::ladosDo($e, $alvo, $dono) === null) {
            $ops = ($p['filtro'] ?? null) === 'inimigo' ? self::inimigos($e, $dono) : self::vivos($e);
            if (count($ops) === 1 && ($p['filtro'] ?? null) === 'inimigo') {
                $e['fila'][0]['jog'] = $ops[0];
                $ef = $e['fila'][0];
            }
        }

        // Ruína: todas as Lendas caem e o que está no campo vai para o cemitério
        if ($a === 'destruir' && $alvo === 'tudo') {
            foreach (self::vivos($e) as $l) {
                foreach ($e['lados'][$l]['mesa'] as $k => $m) {
                    $e['lados'][$l]['mesa'][$k]['morto'] = true;
                }
                foreach ($e['lados'][$l]['feiticos'] as $f) {
                    $e['lados'][$l]['cemiterio'][] = ['i' => $f['i'], 'c' => $f['c']];
                }
                $e['lados'][$l]['feiticos'] = [];
            }
            self::log($e, "$fonte destruiu todas as cartas em campo.");

            return $e;
        }

        // ----- ações em Lendas (ou dano em jogadores): primeiro, quem é o alvo -----
        if (in_array($a, ['dano', 'destruir', 'devolver', 'buff', 'palavra', 'equipar', 'copia', 'exilar', 'prender', 'zerar', 'tirapal'], true)) {
            $lendas = [];  // [lado, inst]
            $jogadores = [];
            if ($alvo === 'lenda') {
                $ops = self::lendasEscolhiveis($e, $dono, $p['filtro'] ?? null);
                if ($a === 'copia') { // Diego não copia a si mesmo
                    $ops = array_values(array_filter($ops, fn ($i) => $i !== (int) $ef['i']));
                }

                return self::pedir($e, $p, $dono, 'lenda', $ops, "$fonte: escolha " . GladioTexto::textoPasso($p));
            }
            if ($alvo === 'ultima' || $alvo === 'evento') { // a Lenda escolhida antes no mesmo efeito / a Lenda do evento
                $i = $alvo === 'ultima' ? ($ef['ult'] ?? null) : ($ef['ctx']['i'] ?? null);
                $alvo = $i !== null ? 'inst:' . $i : 'nada';
                $p['alvo'] = $alvo;
            }
            if ($alvo === 'qualquer') {
                $ops = self::lendasEscolhiveis($e, $dono, $p['filtro'] ?? null);
                foreach (($p['filtro'] ?? null) === 'inimigo' ? self::inimigos($e, $dono) : self::vivos($e) as $l) {
                    $ops[] = 'j:' . $l;
                }

                return self::pedir($e, $p, $dono, 'qualquer', $ops, "$fonte: escolha o alvo (" . GladioTexto::textoPasso($p) . ')');
            }
            if ($alvo === 'jogador' && self::ladosDo($e, $alvo, $dono) === null) {
                $ops = array_map(fn ($l) => 'j:' . $l, ($p['filtro'] ?? null) === 'inimigo' ? self::inimigos($e, $dono) : self::vivos($e));

                return self::pedir($e, $p, $dono, 'jogador', $ops, "$fonte: escolha o jogador");
            }
            if ($alvo === 'nada') {
                return $e;
            }
            if (str_starts_with($alvo, 'inst:')) {
                if ($onde = self::acharLenda($e, (int) substr($alvo, 5))) {
                    $lendas[] = $onde;
                    $e['fila'][0]['ult'] = (int) substr($alvo, 5); // "e sofre 3 de dano": a mesma Lenda
                }
            } elseif ($alvo === 'fonte') {
                if ($ef['i'] !== null && ($onde = self::acharLenda($e, (int) $ef['i']))) {
                    $lendas[] = $onde;
                }
            } elseif ($alvo === 'lendas') {
                foreach (self::lendasEscolhiveis($e, $dono, $p['filtro'] ?? null) as $i) {
                    $lendas[] = self::acharLenda($e, $i);
                }
                // efeitos "em todas" não miram: Hexo-Imune não protege
                foreach (self::vivos($e) as $l) {
                    if ((($p['filtro'] ?? null) === 'meu' && $l !== $dono) || (($p['filtro'] ?? null) === 'inimigo' && $l === $dono)) {
                        continue;
                    }
                    foreach ($e['lados'][$l]['mesa'] as $k => $m) {
                        if ($l !== $dono && self::tem($m, 'resistencia')) {
                            $lendas[] = [$l, $k];
                        }
                    }
                }
            } else {
                $jogadores = self::ladosDo($e, $alvo, $dono) ?? [];
            }

            if ($a === 'equipar') { // o feitiço que está resolvendo fica vinculado à Lenda escolhida
                [$l, $k] = $lendas[0] ?? [null, null];
                if ($l === $dono && $ef['i'] !== null && self::idx($e['lados'][$dono]['feiticos'], (int) $ef['i']) >= 0) {
                    $e = self::vincular($e, $dono, (int) $ef['i'], (int) $e['lados'][$l]['mesa'][$k]['i']);
                }

                return $e;
            }
            if ($a === 'copia') { // Diego: a própria Lenda vira cópia da escolhida
                [$l, $k] = $lendas[0] ?? [null, null];
                if ($l === $dono && $ef['i'] !== null && (int) $e['lados'][$l]['mesa'][$k]['i'] !== (int) $ef['i']
                    && self::idx($e['lados'][$dono]['mesa'], (int) $ef['i']) >= 0) {
                    $e = self::copiar($e, $dono, (int) $ef['i'], (int) $e['lados'][$l]['mesa'][$k]['i']);
                }

                return $e;
            }
            if ($a === 'exilar') { // sai do jogo: não vai para o cemitério (o comandante volta para a zona de comando)
                foreach ($lendas as [$l, $k]) {
                    $m = $e['lados'][$l]['mesa'][$k];
                    array_splice($e['lados'][$l]['mesa'], $k, 1);
                    $e = self::soltarEquipamentos($e, (int) $m['i']);
                    if ($m['c'] !== null && $m['i'] === ($e['lados'][$l]['cmd'] ?? null)) {
                        $e['lados'][$l]['comando'][] = ['i' => $m['i'], 'c' => $m['c']];
                    }
                    self::log($e, "$fonte exilou " . self::nomeLenda($e, $m) . '.');
                }

                return $e;
            }
            foreach ($lendas as [$l, $k]) {
                $m = &$e['lados'][$l]['mesa'][$k];
                $nome = self::nomeLenda($e, $m) . ($l === $dono ? '' : ' de ' . self::quem($e, $l));
                if (($a === 'palavra' && ($p['pal'] ?? '') === '?') || $a === 'tirapal') {
                    // palavra-chave à escolha de quem controla o efeito
                    if ($esc === null) {
                        $ops = $a === 'tirapal' ? array_values($m['pal'] ?? [])
                            : array_values(array_diff(array_keys(self::PALAVRAS), $m['pal'] ?? []));
                        $inst = (int) $m['i'];
                        unset($m);

                        return self::pedir($e, ['alvo' => 'inst:' . $inst] + $p, $dono, 'palavra', $ops,
                            "$fonte: escolha a palavra-chave que $nome " . ($a === 'tirapal' ? 'perde' : 'ganha'));
                    }
                    if ($a === 'tirapal') {
                        $m['pal'] = array_values(array_diff($m['pal'] ?? [], [$esc]));
                        self::log($e, "$fonte: $nome perdeu " . (self::PALAVRAS[$esc] ?? $esc) . '.');
                        unset($m);
                        continue;
                    }
                    $p['pal'] = $esc;
                }
                switch ($a) {
                    case 'prender':
                        $m['preso'] = true;
                        $m['pode'] = false;
                        self::log($e, "$fonte: $nome não pode mais atacar.");
                        break;
                    case 'zerar':
                        $m['atk'] = 0;
                        self::log($e, "$fonte: $nome não causa mais dano.");
                        break;
                    case 'dano':
                        $m['vida'] -= $n;
                        self::log($e, "$fonte: $n de dano em $nome.");
                        break;
                    case 'destruir':
                        $m['morto'] = true;
                        self::log($e, "$fonte destruiu $nome.");
                        break;
                    case 'buff':
                        $m['atk'] = max(0, $m['atk'] + (int) $p['atk']);
                        $m['vida'] += (int) $p['vida'];
                        $m['max'] += (int) $p['vida'];
                        if (!empty($p['temp'])) {
                            $m['tmp']['atk'] = ($m['tmp']['atk'] ?? 0) + (int) $p['atk'];
                            $m['tmp']['vida'] = ($m['tmp']['vida'] ?? 0) + (int) $p['vida'];
                        }
                        self::log($e, sprintf('%s: %+d/%+d em %s%s.', $fonte, $p['atk'], $p['vida'], $nome, empty($p['temp']) ? '' : ' até o fim do turno'));
                        break;
                    case 'palavra':
                        if (!in_array($p['pal'], $m['pal'] ?? [], true)) {
                            $m['pal'][] = $p['pal'];
                            if (!empty($p['temp'])) {
                                $m['tmp']['pal'][] = $p['pal'];
                            }
                        }
                        self::log($e, "$fonte: $nome ganhou " . (self::PALAVRAS[$p['pal']] ?? $p['pal']) . (empty($p['temp']) ? '' : ' até o fim do turno') . '.');
                        break;
                }
                unset($m);
            }
            if ($a === 'devolver') { // de trás para frente: os índices das outras não mudam
                usort($lendas, fn ($x, $y) => $y[1] <=> $x[1]);
                foreach ($lendas as [$l, $k]) {
                    $m = $e['lados'][$l]['mesa'][$k];
                    array_splice($e['lados'][$l]['mesa'], $k, 1);
                    $e = self::soltarEquipamentos($e, (int) $m['i']);
                    if ($m['c'] !== null) {
                        $zona = count($e['lados'][$l]['mao']) < self::MAO_MAX ? 'mao' : 'cemiterio';
                        $e['lados'][$l][$zona][] = ['i' => $m['i'], 'c' => $m['c']];
                    }
                    self::log($e, "$fonte devolveu " . self::nomeLenda($e, $m) . ' para a mão de ' . self::quem($e, $l) . '.');
                }
            }
            if ($a === 'dano') {
                foreach ($jogadores as $l) {
                    $e['lados'][$l]['vida'] -= $n;
                    self::log($e, "$fonte: $n de dano em " . self::quem($e, $l) . '.');
                }
            }

            return $e;
        }

        // ----- ações de jogador -----
        $lados = self::ladosDo($e, $alvo, $dono);
        if ($lados === null) {
            $ops = ($p['filtro'] ?? null) === 'inimigo' ? self::inimigos($e, $dono) : self::vivos($e);

            return self::pedir($e, $p, $dono, 'jogador', array_map(fn ($l) => 'j:' . $l, $ops), "$fonte: escolha o jogador");
        }
        // roubar e trocar de mão: o escolhido é a vítima; quem controla a carta é quem leva
        if ($a === 'roubar' || $a === 'trocamao') {
            foreach ($lados as $l) {
                if ($l === $dono) {
                    continue;
                }
                if ($a === 'roubar') {
                    $tira = min($n, max(0, (int) $e['lados'][$l]['vida']));
                    $e['lados'][$l]['vida'] -= $tira;
                    $e['lados'][$dono]['vida'] += $tira;
                    $e['lados'][$dono]['max'] = max($e['lados'][$dono]['max'], $e['lados'][$dono]['vida']);
                    self::log($e, "$fonte: " . self::quem($e, $dono) . " roubou $tira de " . self::quem($e, $l) . '.');
                } else {
                    $limpa = fn ($mao) => array_map(fn ($c) => ['i' => $c['i'], 'c' => $c['c']], $mao);
                    [$e['lados'][$dono]['mao'], $e['lados'][$l]['mao']] = [$limpa($e['lados'][$l]['mao']), $limpa($e['lados'][$dono]['mao'])];
                    self::log($e, "$fonte: " . self::quem($e, $dono) . ' trocou de mão com ' . self::quem($e, $l) . '.');
                }
            }

            return $e;
        }
        // descarte da mão inteira ou ao acaso: sem escolha (sempre da mão de cada um, nunca do campo)
        if ($a === 'descartar' && (!empty($p['tudo']) || !empty($p['aleatorio']))) {
            foreach ($lados as $l) {
                $ops = self::cartasDe($e, $e['lados'][$l]['mao'], $p['filtro'] ?? null);
                if (empty($p['tudo'])) {
                    shuffle($ops);
                    $ops = array_slice($ops, 0, $n);
                }
                $nomes = [];
                foreach ($ops as $i) {
                    $k = self::idx($e['lados'][$l]['mao'], $i);
                    $c = $e['lados'][$l]['mao'][$k];
                    array_splice($e['lados'][$l]['mao'], $k, 1);
                    $e['lados'][$l]['cemiterio'][] = ['i' => $c['i'], 'c' => $c['c']];
                    $nomes[] = self::nome($e, $c['c']);
                }
                self::log($e, "$fonte: " . self::quem($e, $l) . ($nomes ? ' descartou ' . implode(', ', $nomes) : ' não tinha carta para descartar') . '.');
            }

            return $e;
        }
        // escolhas de carta, uma por vez: cada jogador afetado escolhe as dele
        if (in_array($a, ['descartar', 'sacrificar', 'buscar', 'recuperar'], true) && $esc === null && (count($lados) > 1 || $n > 1)) {
            $um = [];
            foreach ($lados as $l) {
                for ($k = 0; $k < max(1, $n); $k++) {
                    $um[] = ['n' => 1, 'alvo' => 'lado:' . $l] + $p;
                }
            }
            array_unshift($e['fila'][0]['passos'], ...$um);

            return $e;
        }

        foreach ($lados as $l) {
            $quem = self::quem($e, $l);
            $L = &$e['lados'][$l];
            switch ($a) {
                case 'shot':
                    unset($L);
                    self::log($e, "$fonte: $quem toma $n shot" . ($n > 1 ? 's' : '') . '.');
                    $e = self::beber($e, $l, $n);
                    break;
                case 'comprar':
                    unset($L);
                    for ($k = 0; $k < $n; $k++) {
                        $e = self::comprarUma($e, $l);
                    }
                    self::log($e, "$fonte: $quem comprou $n carta" . ($n > 1 ? 's' : '') . '.');
                    break;
                case 'perder':
                    $L['vida'] -= $n;
                    unset($L);
                    self::log($e, "$fonte: $quem perdeu $n de vida.");
                    break;
                case 'ganhar':
                    unset($L);
                    self::curar($e, $l, $n);
                    self::log($e, "$fonte: $quem recuperou $n de vida.");
                    break;
                case 'energia':
                    $L['energia'] = min(self::ENERGIA_LIMITE, $L['energia'] + $n);
                    if (!empty($p['max'])) { // "ganhe 1 aura total": a máxima também sobe
                        $L['energiaMax'] = min(self::ENERGIA_LIMITE, $L['energiaMax'] + $n);
                    }
                    unset($L);
                    self::log($e, "$fonte: $quem ganhou $n de energia" . (!empty($p['max']) ? ' máxima' : '') . '.');
                    break;
                case 'ficha':
                    unset($L);
                    $pal = array_values(array_intersect($p['pal'] ?? [], array_keys(self::PALAVRAS)));
                    for ($k = 0; $k < max(1, $n); $k++) {
                        $e['lados'][$l]['mesa'][] = ['i' => ++$e['seq'], 'c' => null, 'nome' => $p['nome'] ?? 'Ficha', 'atk' => (int) $p['atk'],
                            'vida' => max(1, (int) $p['vida']), 'max' => max(1, (int) $p['vida']), 'pode' => in_array('impeto', $pal, true),
                            'virada' => false, 'pal' => $pal, 'cont' => 0, 'nota' => ''];
                    }
                    self::log($e, "$fonte: $quem criou " . GladioTexto::textoPasso(['alvo' => 'eu'] + $p) . '.');
                    break;
                case 'moer':
                    unset($L);
                    $foram = array_splice($e['lados'][$l]['baralho'], 0, $n);
                    array_push($e['lados'][$l]['cemiterio'], ...$foram);
                    self::log($e, "$fonte: $quem jogou " . count($foram) . ' carta(s) do topo do baralho no cemitério.');
                    break;
                case 'invocar': // outra carta do catálogo, pelo nome (cópia nova, fora do baralho)
                    unset($L);
                    $id = null;
                    foreach ($e['cartas'] as $cid => $def) {
                        if (($def['tipo'] ?? '') === 'lenda' && GladioTexto::normalizar((string) $def['nome']) === ($p['carta'] ?? '')) {
                            $id = (string) $cid;
                            break;
                        }
                    }
                    for ($k = 0; $id !== null && $k < max(1, $n); $k++) {
                        $nova = ['i' => ++$e['seq'], 'c' => $id]; // antes de passar $e adiante: senão a cópia leva o seq antigo
                        $e = self::entrar($e, $l, $nova);
                        self::log($e, "$fonte: $quem invocou " . self::nome($e, $id) . '.');
                    }
                    break;
                case 'descartar':
                    if ($esc === null) {
                        unset($L);

                        $o = ['lenda' => 'uma Lenda', 'feitico' => 'um feitiço'][$p['filtro'] ?? ''] ?? 'uma carta';

                        return self::pedir($e, $p, $l, 'mao', self::cartasDe($e, $e['lados'][$l]['mao'], $p['filtro'] ?? null), "$fonte: descarte $o da sua mão");
                    }
                    $k = self::idx($L['mao'], (int) $esc);
                    if ($k >= 0) {
                        $c = $L['mao'][$k];
                        array_splice($L['mao'], $k, 1);
                        $L['cemiterio'][] = ['i' => $c['i'], 'c' => $c['c']];
                        unset($L);
                        self::log($e, "$fonte: $quem descartou " . self::nome($e, $c['c']) . '.');
                    }
                    break;
                case 'sacrificar':
                    if ($esc === null) {
                        unset($L);

                        // a própria carta que pede o sacrifício não entra na conta
                        $ops = array_values(array_filter(array_map(fn ($m) => (int) $m['i'], $e['lados'][$l]['mesa']), fn ($i) => $i !== (int) $ef['i']));

                        return self::pedir($e, $p, $l, 'lenda', $ops, "$fonte: sacrifique uma Lenda sua");
                    }
                    $k = self::idx($L['mesa'], (int) $esc);
                    if ($k >= 0) {
                        $m = $L['mesa'][$k];
                        array_splice($L['mesa'], $k, 1);
                        unset($L);
                        self::log($e, "$fonte: $quem sacrificou " . self::nomeLenda($e, $m) . '.');
                        $e = self::saiDaMesa($e, $l, $m);
                    }
                    break;
                case 'buscar':
                    if ($esc === null) {
                        unset($L);
                        if (count($e['lados'][$l]['mao']) >= self::MAO_MAX) {
                            self::log($e, "$fonte: a mão de $quem está cheia.");
                            break;
                        }
                        $ops = self::cartasDe($e, $e['lados'][$l]['baralho'], $p['filtro'] ?? null);
                        // a dica do texto ("uma propriedade fisiomante") filtra pelo nome das cartas: ficam as que
                        // combinam com mais palavras dela (sem plural); se nenhuma combinar, vale qualquer uma
                        $palavras = array_filter(array_map(fn ($w) => rtrim($w, 's'),
                            preg_split('/\s+/', GladioTexto::normalizar((string) ($p['dica'] ?? '')))), fn ($w) => mb_strlen($w) > 3);
                        $nota = [];
                        foreach ($ops as $i) {
                            $nomeC = GladioTexto::normalizar((string) ($e['cartas'][$e['lados'][$l]['baralho'][self::idx($e['lados'][$l]['baralho'], $i)]['c']]['nome'] ?? ''));
                            $nota[$i] = count(array_filter($palavras, fn ($w) => str_contains($nomeC, $w)));
                        }
                        if ($nota && max($nota) > 0) {
                            $ops = array_keys(array_filter($nota, fn ($v) => $v === max($nota)));
                        }

                        return self::pedir($e, $p, $l, 'baralho', $ops, "$fonte: escolha " . ($p['dica'] ?? 'uma carta') . ' no seu baralho');
                    }
                    $k = self::idx($L['baralho'], (int) $esc);
                    if ($k >= 0) {
                        $c = $L['baralho'][$k];
                        array_splice($L['baralho'], $k, 1);
                        $L['mao'][] = $c;
                        unset($L);
                        self::log($e, "$fonte: $quem buscou uma carta no baralho.");
                    }
                    break;
                case 'recuperar':
                    if ($esc === null) {
                        unset($L);
                        $tipo = ($p['destino'] ?? 'mao') === 'mesa' ? ($p['filtro'] ?? 'lenda') : ($p['filtro'] ?? null);

                        return self::pedir($e, $p, $l, 'cemiterio', self::cartasDe($e, $e['lados'][$l]['cemiterio'], $tipo),
                            "$fonte: escolha uma carta do seu cemitério");
                    }
                    $k = self::idx($L['cemiterio'], (int) $esc);
                    if ($k < 0) {
                        unset($L);
                        break;
                    }
                    $c = $L['cemiterio'][$k];
                    unset($L);
                    if (($p['destino'] ?? 'mao') === 'mesa') {
                        try {
                            $e = self::entrar($e, $l, $c);
                        } catch (\InvalidArgumentException $x) {
                            self::log($e, "$fonte: " . $x->getMessage());
                            break;
                        }
                        array_splice($e['lados'][$l]['cemiterio'], $k, 1);
                        self::log($e, "$fonte: $quem trouxe " . self::nome($e, $c['c']) . ' do cemitério para a mesa.');
                    } elseif (count($e['lados'][$l]['mao']) < self::MAO_MAX) {
                        array_splice($e['lados'][$l]['cemiterio'], $k, 1);
                        $e['lados'][$l]['mao'][] = $c;
                        self::log($e, "$fonte: $quem recuperou " . self::nome($e, $c['c']) . ' do cemitério para a mão.');
                    }
                    break;
                default:
                    unset($L);
            }
            unset($L);
            if ($e['fim']) {
                break;
            }
        }

        return $e;
    }

    /**
     * Responde à escolha pendente. $valor: instância da carta/Lenda, 'j:<lado>' para um jogador ou null para pular
     * (o descarte do fim do turno não dá para pular).
     */
    public static function escolher(array $e, string $lado, mixed $valor): array
    {
        self::exigirAtivo($e, $lado);
        $es = $e['escolha'];
        if (!$es || $es['lado'] !== $lado) {
            throw new \InvalidArgumentException('Não há nada para você escolher agora.');
        }
        $p = $es['passo'];
        if ($valor === null || $valor === '') {
            if ($es['obrig']) {
                throw new \InvalidArgumentException('Essa escolha não dá para pular.');
            }
            $e['escolha'] = null;
            if ($es['tipo'] === 'confirmar' && $e['fila']) { // recusou o "você pode…": o resto da frase não acontece
                array_splice($e['fila'][0]['passos'], 0, (int) ($p['resto'] ?? 0));
            }
            self::log($e, self::quem($e, $lado) . ($es['tipo'] === 'confirmar' ? ' recusou' : ' pulou') . ": {$es['texto']}");

            return self::seguir($e);
        }
        $valor = is_numeric($valor) ? (int) $valor : (string) $valor;
        if (!in_array($valor, $es['opcoes'], true)) {
            throw new \InvalidArgumentException('Essa escolha não vale aqui.');
        }
        $e['escolha'] = null;
        if (in_array($es['tipo'], ['confirmar', 'palavra'], true)) { // "sim" ou a palavra-chave escolhida
            $p['escolhido'] = $valor;
        } elseif (is_string($valor)) { // jogador escolhido: vale também para as próximas ações do mesmo efeito na 3ª pessoa
            $j = substr($valor, 2);
            if ($p['alvo'] === 'jogador' || ($e['fila'][0]['jog'] ?? null) === null) {
                $e['fila'][0]['jog'] = $j;
            }
            $p['alvo'] = 'lado:' . $j;
        } elseif (in_array($es['tipo'], ['lenda', 'qualquer'], true) && $p['a'] !== 'sacrificar') {
            $p['alvo'] = 'inst:' . $valor;
        } else {
            $p['escolhido'] = $valor;
        }

        return self::seguir(self::executar($e, $p));
    }

    /** Usa de novo, à mão, o texto automático de uma carta sua em jogo (Lenda na mesa ou feitiço no campo). */
    public static function ativar(array $e, string $lado, int $inst): array
    {
        self::exigirAtivo($e, $lado);
        self::exigirSemEscolha($e);
        $x = null;
        foreach (['mesa', 'feiticos'] as $zona) {
            $k = self::idx($e['lados'][$lado][$zona], $inst);
            if ($k >= 0) {
                $x = $e['lados'][$lado][$zona][$k];
            }
        }
        $passos = [];
        foreach ($x && $x['c'] !== null ? ($e['cartas'][$x['c']]['hab'] ?? []) : [] as $h) {
            array_push($passos, ...$h['passos']);
        }
        if (!$passos) {
            throw new \InvalidArgumentException('Essa carta não tem efeito automático.');
        }
        $e['fila'][] = ['lado' => $lado, 'fonte' => self::nome($e, $x['c']), 'i' => $inst, 'passos' => $passos, 'jog' => null];
        self::log($e, self::quem($e, $lado) . ' usou o texto de ' . self::nome($e, $x['c']) . '.');

        return self::seguir($e);
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
        $e['agora'] = time(); // a tela acerta o relógio dela por este (cronômetro da vez)

        return $e;
    }
}
