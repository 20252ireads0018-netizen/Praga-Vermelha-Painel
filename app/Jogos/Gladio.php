<?php

namespace App\Jogos;

/**
 * Gladio: duelo de cartas criadas pelo mestre, com regras parecidas com Magic: The Gathering e apostas.
 *
 * - A vida de cada jogador é o Dinheiro apostado (amistoso, sem aposta: 30 de vida). Quem zerar o outro leva a aposta.
 * - Cada um joga com o próprio deck (ou 20 cartas sorteadas do catálogo) e começa com 5 cartas na mão.
 * - Rodada: no início do seu turno a energia máxima sobe 1 (até 10) e recarrega, as suas Lendas ficam prontas
 *   e você compra 1 carta (quem começa não compra no primeiro turno). Sem cartas para comprar: dano de fadiga (1, 2, 3…).
 * - Lenda: entra na mesa e só ataca a partir do seu próximo turno (com Ímpeto, ataca já).
 * - Feitiço: efeito de uso único (dano, dano em todas as Lendas inimigas, cura, comprar, fortalecer, energia) e vai ao cemitério.
 * - Combate: quem tem a vez declara um ataque com uma ou mais Lendas prontas contra o adversário. O defensor pode
 *   jogar Lendas da mão (pagando com a energia que sobrou) e escolher bloqueadores; cada bloqueador segura um atacante.
 *   Atacante sem bloqueio fere o jogador; bloqueado, troca dano com quem bloqueou. Lendas sem vida vão para o
 *   cemitério (fichas somem). Quem atacou fica "virada" e só volta a bloquear no próximo turno de quem a controla.
 * - Palavras-chave (as regras de cada uma estão em PALAVRAS e nas dicas da tela): Sobrepujar, Ímpeto, Voar, Alcance,
 *   Toque mortal, Vínculo com a vida, Iniciativa, Golpe duplo, Vigilância, Defensor, Ameaça, Indestrutível e
 *   Resistência a magia. Como aqui o dano nas Lendas não some no fim do turno, Indestrutível segura a vida em 1.
 * - Ferramentas manuais (para os efeitos escritos nas cartas): comprar, embaralhar, reordenar o baralho, descartar,
 *   recuperar do cemitério, jogar sem pagar, criar ficha, ajustar ataque/vida, copiar Lenda e sacrificar.
 *   Só valem para as próprias cartas, no próprio turno, e ficam todas no registro da partida.
 *
 * Estado: { lados: { a, b }, vez, turno, fase ('principal' | 'defesa'), ataque, cartas, seq, log, fim }
 * Lado: { vida, max, energia, energiaMax, mao[], baralho[], mesa[], cemiterio[], fadiga }
 * Carta na mão/baralho/cemitério: { i, c }.
 * Lenda na mesa: { i, c (null na ficha), nome? (ficha), atk, vida, max, pode (pode atacar), virada (atacou: não bloqueia), pal[] }.
 * Ataque em andamento: { atacantes: [i…], bloqueios: { bloqueador i: atacante i } }.
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
        'resistencia'   => 'Resistência a magia',
    ];
    public const BARALHO = 20;      // deck sorteado (sem deck montado)
    public const MAO_INICIAL = 5;
    public const MAO_MAX = 10;
    public const MESA_MAX = 7;
    public const ENERGIA_MAX = 10;
    public const VIDA_AMISTOSO = 30;

    /** Quais efeitos precisam de alvo e de que tipo. */
    public const ALVOS = [
        'dano'       => 'inimigo',  // Lenda inimiga ou o jogador inimigo
        'cura'       => 'aliado',   // Lenda sua ou você mesmo
        'fortalecer' => 'lenda',    // Lenda sua
    ];

    /**
     * Monta a partida. $cartas: catálogo [id => carta]. $decks: ['a' => [ids…] | null, 'b' => …] (null: 20 sorteadas).
     * $embaralhar recebe e devolve um array (nos testes dá para passar uma função sem sorte).
     */
    public static function iniciar(array $cartas, int $aposta, array $decks = [], ?callable $embaralhar = null): array
    {
        if (!$cartas) {
            throw new \InvalidArgumentException('O mestre ainda não criou nenhuma carta de Gladio.');
        }
        $embaralhar ??= function (array $a): array {
            shuffle($a);

            return $a;
        };
        $vida = $aposta > 0 ? $aposta : self::VIDA_AMISTOSO;
        $e = ['lados' => [], 'vez' => 'a', 'turno' => 1, 'fase' => 'principal', 'ataque' => null,
              'cartas' => $cartas, 'seq' => 0, 'log' => [], 'fim' => null];

        foreach (['a', 'b'] as $lado) {
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
                'mao' => [], 'baralho' => $baralho, 'mesa' => [], 'cemiterio' => [], 'fadiga' => 0,
            ];
            for ($k = 0; $k < self::MAO_INICIAL; $k++) {
                $e = self::comprarUma($e, $lado);
            }
        }
        $e['lados']['a']['energiaMax'] = 1;
        $e['lados']['a']['energia'] = 1;
        $e['log'] = [];
        self::log($e, 'A partida começou.');

        return $e;
    }

    /** Estado salvo por uma versão antiga do jogo: completa o que falta. */
    public static function completar(array $e): array
    {
        $e['fase'] ??= 'principal';
        $e['ataque'] ??= null;
        foreach (['a', 'b'] as $lado) {
            $e['lados'][$lado]['cemiterio'] ??= [];
            foreach ($e['lados'][$lado]['mesa'] as $k => $m) {
                $e['lados'][$lado]['mesa'][$k]['pal'] ??= self::palavrasDe($e, $m['c'] ?? null);
                $e['lados'][$lado]['mesa'][$k]['virada'] ??= false;
            }
        }

        return $e;
    }

    public static function outro(string $lado): string
    {
        return $lado === 'a' ? 'b' : 'a';
    }

    private static function log(array &$e, string $t): void
    {
        $e['log'][] = $t;
        $e['log'] = array_slice($e['log'], -40);
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

    private static function comprarUma(array $e, string $lado): array
    {
        $l = &$e['lados'][$lado];
        if (!$l['baralho']) {
            $l['fadiga']++;
            $l['vida'] -= $l['fadiga'];
            self::log($e, "Sem cartas no baralho: fadiga de {$l['fadiga']}.");

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

    private static function idx(array $lista, int $i): int
    {
        foreach ($lista as $k => $c) {
            if ((int) $c['i'] === $i) {
                return $k;
            }
        }

        return -1;
    }

    /**
     * Lendas sem vida (ou marcadas por Toque mortal) vão para o cemitério (fichas somem) e vê se alguém perdeu.
     * Indestrutível não cai: a vida fica em pelo menos 1.
     */
    private static function conferir(array $e, string $quem): array
    {
        foreach (['a', 'b'] as $lado) {
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
        $aMorto = $e['lados']['a']['vida'] <= 0;
        $bMorto = $e['lados']['b']['vida'] <= 0;
        if ($aMorto || $bMorto) {
            // os dois zeraram na mesma ação: vence quem agiu
            $vencedor = $aMorto && $bMorto ? $quem : ($aMorto ? 'b' : 'a');
            $e['fim'] = ['vencedor' => $vencedor, 'motivo' => 'vida'];
            $e['fase'] = 'principal';
            $e['ataque'] = null;
        }

        return $e;
    }

    private static function exigirTurno(array $e, string $lado): void
    {
        if ($e['fim']) {
            throw new \InvalidArgumentException('A partida já acabou.');
        }
        if ($e['vez'] !== $lado) {
            throw new \InvalidArgumentException('Não é a sua vez.');
        }
        if ($e['fase'] !== 'principal') {
            throw new \InvalidArgumentException('Espere o adversário escolher a defesa.');
        }
    }

    private static function exigirDefesa(array $e, string $lado): void
    {
        if ($e['fim']) {
            throw new \InvalidArgumentException('A partida já acabou.');
        }
        if ($e['fase'] !== 'defesa' || $e['vez'] === $lado) {
            throw new \InvalidArgumentException('Não há ataque para você defender agora.');
        }
    }

    /* ================================================================== */
    /*  Jogar cartas                                                       */
    /* ================================================================== */

    /**
     * Joga uma carta da mão. $alvo: 'eu' | 'inimigo' | id de instância de uma Lenda na mesa.
     * No turno do adversário só dá para jogar Lendas, e só durante a defesa. $gratis: sem pagar energia (efeito de carta).
     */
    public static function jogar(array $e, string $lado, int $inst, mixed $alvo = null, bool $gratis = false): array
    {
        $defendendo = !$e['fim'] && $e['fase'] === 'defesa' && $e['vez'] !== $lado;
        if ($defendendo) {
            self::exigirDefesa($e, $lado);
        } else {
            self::exigirTurno($e, $lado);
        }
        $op = self::outro($lado);
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
        $semCusto = $gratis ? ' (sem pagar energia)' : '';

        if ($carta['tipo'] === 'lenda') {
            if (count($l['mesa']) >= self::MESA_MAX) {
                throw new \InvalidArgumentException('Sua mesa já tem ' . self::MESA_MAX . ' Lendas.');
            }
            $pal = self::palavrasDe($e, $id);
            $l['mesa'][] = ['i' => $l['mao'][$k]['i'], 'c' => $id, 'atk' => (int) $carta['ataque'], 'vida' => (int) $carta['vida'],
                            'max' => (int) $carta['vida'], 'pode' => !$defendendo && in_array('impeto', $pal, true), 'virada' => false, 'pal' => $pal];
            array_splice($l['mao'], $k, 1);
            $l['energia'] -= $custo;
            unset($l);
            self::log($e, ($defendendo ? "Na defesa, a Lenda $nome entrou na mesa" : "Lenda $nome entrou na mesa") . "$semCusto.");

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
                $ki = self::idx($e['lados'][$dono]['mesa'], (int) $alvo);
                if ($ki >= 0 && $dono === $op && self::tem($e['lados'][$dono]['mesa'][$ki], 'resistencia')) {
                    throw new \InvalidArgumentException(self::nomeLenda($e, $e['lados'][$dono]['mesa'][$ki]) . ' tem Resistência a magia: não pode ser alvo dos seus feitiços.');
                }
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

        $l['cemiterio'][] = $l['mao'][$k];
        array_splice($l['mao'], $k, 1);
        $l['energia'] -= $custo;
        unset($l);

        switch ($efeito) {
            case 'dano':
                if ($alvoLenda) {
                    $e['lados'][$alvoLenda[0]]['mesa'][$alvoLenda[1]]['vida'] -= $valor;
                    self::log($e, "$nome causou $valor de dano em " . self::nomeLenda($e, $e['lados'][$alvoLenda[0]]['mesa'][$alvoLenda[1]]) . "$semCusto.");
                } else {
                    $e['lados'][$alvoHeroi]['vida'] -= $valor;
                    self::log($e, "$nome causou $valor de dano no adversário$semCusto.");
                }
                break;
            case 'dano_todos':
                foreach ($e['lados'][$op]['mesa'] as $i => $m) {
                    $e['lados'][$op]['mesa'][$i]['vida'] -= $valor;
                }
                self::log($e, "$nome causou $valor de dano em todas as Lendas inimigas$semCusto.");
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
                self::log($e, "$nome curou $valor$semCusto.");
                break;
            case 'comprar':
                for ($n = 0; $n < $valor; $n++) {
                    $e = self::comprarUma($e, $lado);
                }
                self::log($e, "$nome: comprou $valor carta(s)$semCusto.");
                break;
            case 'fortalecer':
                $e['lados'][$lado]['mesa'][$alvoLenda[1]]['atk'] += $valor;
                self::log($e, "$nome deu +$valor de ataque a " . self::nomeLenda($e, $e['lados'][$lado]['mesa'][$alvoLenda[1]]) . "$semCusto.");
                break;
            case 'energia':
                $e['lados'][$lado]['energia'] += $valor;
                self::log($e, "$nome deu +$valor de energia$semCusto.");
                break;
        }

        return self::conferir($e, $lado);
    }

    /* ================================================================== */
    /*  Combate                                                            */
    /* ================================================================== */

    /** Declara o ataque contra o adversário com uma ou mais Lendas prontas. A partida espera a defesa. */
    public static function atacar(array $e, string $lado, array $insts): array
    {
        self::exigirTurno($e, $lado);
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
        $e['ataque'] = ['atacantes' => $insts, 'bloqueios' => (object) []];
        self::log($e, 'Ataque com ' . implode(', ', $nomes) . '. O adversário escolhe a defesa.');

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
                    self::log($e, "Vínculo com a vida: o defensor curou $curaDefensor.");
                }
            }
        }

        $e['fase'] = 'principal';
        $e['ataque'] = null;

        return self::conferir($e, $at);
    }

    /** Termina o turno: o outro ganha +1 de energia máxima, recarrega, as Lendas dele ficam prontas e ele compra 1. */
    public static function passar(array $e, string $lado): array
    {
        self::exigirTurno($e, $lado);
        $op = self::outro($lado);
        $e['vez'] = $op;
        $e['turno']++;
        $l = &$e['lados'][$op];
        $l['energiaMax'] = min(self::ENERGIA_MAX, $l['energiaMax'] + 1);
        $l['energia'] = $l['energiaMax'];
        foreach ($l['mesa'] as $i => $m) {
            $l['mesa'][$i]['pode'] = true;
            $l['mesa'][$i]['virada'] = false;
        }
        unset($l);
        $e = self::comprarUma($e, $op);

        return self::conferir($e, $lado);
    }

    public static function desistir(array $e, string $lado): array
    {
        if ($e['fim']) {
            throw new \InvalidArgumentException('A partida já acabou.');
        }
        $e['fim'] = ['vencedor' => self::outro($lado), 'motivo' => 'desistencia'];
        $e['fase'] = 'principal';
        $e['ataque'] = null;
        self::log($e, 'Um jogador desistiu.');

        return $e;
    }

    /* ================================================================== */
    /*  Ferramentas manuais (efeitos escritos nas cartas)                  */
    /* ================================================================== */

    public static function comprar(array $e, string $lado): array
    {
        self::exigirTurno($e, $lado);
        $e = self::comprarUma($e, $lado);
        self::log($e, 'Comprou uma carta (ação manual).');

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
        self::log($e, 'Embaralhou o baralho.');

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
        self::log($e, 'Mudou a ordem das cartas do baralho.');

        return $e;
    }

    public static function descartar(array $e, string $lado, int $inst): array
    {
        self::exigirTurno($e, $lado);
        $k = self::idx($e['lados'][$lado]['mao'], $inst);
        if ($k < 0) {
            throw new \InvalidArgumentException('Essa carta não está na sua mão.');
        }
        $c = $e['lados'][$lado]['mao'][$k];
        array_splice($e['lados'][$lado]['mao'], $k, 1);
        $e['lados'][$lado]['cemiterio'][] = $c;
        self::log($e, 'Descartou ' . self::nome($e, $c['c']) . '.');

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
        self::log($e, 'Recuperou ' . self::nome($e, $c['c']) . ' do cemitério para a mão.');

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
                                        'max' => $vida, 'pode' => false, 'virada' => false, 'pal' => []];
        self::log($e, "Criou a ficha $nome ($atk/$vida).");

        return $e;
    }

    /** Ajusta ataque e vida de uma Lenda sua (+1/+1, -1/-1…). Vida 0 ou menos: ela cai. */
    public static function ajustar(array $e, string $lado, int $inst, int $datk, int $dvida): array
    {
        self::exigirTurno($e, $lado);
        $k = self::idx($e['lados'][$lado]['mesa'], $inst);
        if ($k < 0) {
            throw new \InvalidArgumentException('Essa Lenda não está na sua mesa.');
        }
        $m = &$e['lados'][$lado]['mesa'][$k];
        $m['atk'] = max(0, $m['atk'] + $datk);
        $m['vida'] += $dvida;
        if ($dvida > 0) {
            $m['max'] += $dvida;
        }
        $txt = self::nomeLenda($e, $m) . ' ficou ' . $m['atk'] . '/' . $m['vida'];
        unset($m);
        self::log($e, sprintf('Ajustou %+d/%+d: %s.', $datk, $dvida, $txt));

        return self::conferir($e, $lado);
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
        $e['lados'][$lado]['mesa'][$k] = ['i' => $mesa[$k]['i'], 'c' => $mod['c'], 'nome' => $mod['nome'] ?? null,
            'atk' => $atk, 'vida' => $vida, 'max' => $vida, 'pode' => $mesa[$k]['pode'], 'virada' => $mesa[$k]['virada'] ?? false, 'pal' => $mod['pal'] ?? []];
        if ($mod['c'] !== null) {
            unset($e['lados'][$lado]['mesa'][$k]['nome']);
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
        self::log($e, 'Sacrificou ' . self::nomeLenda($e, $m) . '.');

        return $e;
    }

    /** O que um lado pode ver: a mão do outro e o baralho dele viram só a quantidade; cada um vê o próprio baralho. */
    public static function visao(array $e, ?string $eu): array
    {
        foreach (['a', 'b'] as $lado) {
            if ($lado !== $eu) {
                $e['lados'][$lado]['baralho'] = count($e['lados'][$lado]['baralho']);
                $e['lados'][$lado]['mao'] = count($e['lados'][$lado]['mao']);
            }
        }

        return $e;
    }
}
