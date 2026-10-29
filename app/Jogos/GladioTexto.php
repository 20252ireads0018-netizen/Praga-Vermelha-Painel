<?php

namespace App\Jogos;

/**
 * Lê o texto que o mestre escreveu numa carta de Gladio e tira dele os efeitos que o motor sabe aplicar sozinho.
 *
 * Cada frase pode ter um gatilho: no começo ("Quando invocado", "Quando <nome> entrar, atacar ou morrer", "Toda vez
 * que essa lenda atacar", "Todo início de turno", "No fim do seu turno") ou no fim ("… quando for invocado", "… todo
 * início de turno"). Sem gatilho vale o da frase anterior ou, na primeira, "ao entrar" (Lenda) / "ao conjurar" (feitiço).
 * A frase é dividida em ações ("sacrifique uma lenda, compre duas cartas"); quem faz a ação vem do sujeito ("todo
 * jogador perde 10$", "o jogador alvo vira um shot e compra uma carta") ou, no imperativo, é quem controla a carta.
 *
 * Para não aplicar meia regra, a frase inteira fica para ser resolvida à mão (e a carta fica 'parcial') quando:
 * - alguma ação dela não foi entendida;
 * - tem condição ou conta ("se", "para cada", "enquanto", "você pode", "X", "próximo turno"…);
 * - o gatilho é de outra coisa ("sempre que um feitiço…", "toda vez que uma lenda sua morrer…", "quando bloquear").
 *   Nesse caso as frases seguintes sem gatilho próprio também ficam à mão (continuam aquela regra).
 *
 * Habilidade: { g: gatilho, passos: [passo…] }. Gatilhos: entrar, conjurar, morrer, inicio (todo turno),
 * inicio_meu (turno de quem controla), fim, fim_meu, atacar.
 * Passo: { a: ação, n, alvo, filtro?, pal?, atk?, vida?, temp?, destino?, nome?, carta? }.
 *   Ações de jogador (alvo: eu | todos | inimigos | jogador = escolhido): shot, comprar, perder (também "pague"),
 *   ganhar, energia, descartar, sacrificar, moer (topo do baralho ao cemitério), buscar (n, filtro lenda|feitico, dica),
 *   recuperar (destino mao|mesa), ficha (nome, atk, vida, pal), invocar (carta: nome de outra carta do catálogo).
 *   Ações em Lendas (alvo: lenda = escolhida | lendas = todas | fonte = a própria carta | qualquer = Lenda ou jogador |
 *   eu | todos | inimigos | jogador; filtro: meu | inimigo | qualquer): dano, destruir, devolver, buff (atk, vida, temp),
 *   palavra (pal, temp).
 */
class GladioTexto
{
    /** Sobe quando o leitor muda: partidas em andamento releem as cartas. */
    public const VERSAO = 4;

    private const NUM = '(\d+|um|uma|dois|duas|tres|quatro|cinco|seis|sete|oito|nove|dez)';
    private const NUMEROS = ['um' => 1, 'uma' => 1, 'dois' => 2, 'duas' => 2, 'tres' => 3, 'quatro' => 4, 'cinco' => 5,
                             'seis' => 6, 'sete' => 7, 'oito' => 8, 'nove' => 9, 'dez' => 10];
    // quantia de vida/Dinheiro: "10$", "$10", "R$ 10", "10 de vida", "10 de dinheiro"
    private const DINHEIRO = '(?:r?\$\s*(\d+)|(\d+)\s*\$|(\d+)\s+(?:de\s+|pontos de\s+)?(?:vida|dinheiro|moedas?))';
    /** Nomes das palavras-chave como aparecem no texto (sem acento) => chave de Gladio::PALAVRAS. */
    private const PALAVRAS = [
        'hexo-imunidade' => 'resistencia', 'hexo-imune' => 'resistencia', 'hexo imune' => 'resistencia', 'heximune' => 'resistencia',
        'hexoimune' => 'resistencia', 'sobrepujar' => 'sobrepujar', 'impeto' => 'impeto', 'voar' => 'voar', 'voo' => 'voar',
        'alcance' => 'alcance', 'toque mortal' => 'toque', 'toque mortifero' => 'toque', 'toque da morte' => 'toque',
        'vinculo com a vida' => 'vinculo', 'iniciativa' => 'iniciativa', 'primeiro ataque' => 'iniciativa', 'golpe duplo' => 'duplo',
        'vigilancia' => 'vigilancia', 'defensor' => 'defensor', 'ameaca' => 'ameaca', 'indestrutivel' => 'indestrutivel',
    ];
    // verbos na 3ª pessoa: a ação é do sujeito da frase (no imperativo é de quem controla a carta)
    private const TERCEIRA = '/^(compra|compram|comprar|perde|perdem|perder|vira|viram|virar|toma|tomam|tomar|bebe|bebem|beber|ganha|ganham|ganhar|'
        . 'recupera|recuperam|recuperar|descarta|descartam|descartar|sacrifica|sacrificam|sacrificar|recebe|recebem|receber|cura|curam|paga|pagam|pagar)$/';
    // frase com condição, conta ou escolha livre: fica à mão
    private const CONDICAO = '/\bse (?!torna)|\bpara cada\b|\benquanto\b|\bcaso\b|\bpode\b|\bpodem\b|\bproxim[oa]s?\b|\bx\b|\buma vez por\b'
        . '|\bate que\b|\bao inves\b|\bdessa forma\b|\bequipad|\bequipe\b|\bcada vez\b/';
    // começo de gatilho que o leitor não reconheceu (de outra carta, de outro evento ou com condição)
    private const GATILHO_DESCONHECIDO = '/^(?:quando|ao |sempre que|toda vez|todo (?:inicio|comeco|fim|final)|toda (?:inicio|vez)|cada (?:inicio|fim)'
        . '|a cada|no (?:inicio|comeco|fim|final)|em todo|daqui a|[a-z]{2,15}:)/';
    private const EVENTO = '(?:for invocad[oa]|ser invocad[oa]|invocad[oa]|entrar|entra|atacar|ataca|morrer|morre|cair|for destruid[oa])';

    public static function normalizar(string $t): string
    {
        $t = mb_strtolower($t);
        $t = strtr($t, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o',
                        'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c', '–' => '-', '—' => '-']);

        return trim(preg_replace('/\s+/', ' ', $t));
    }

    private static function num(string $n): int
    {
        return ctype_digit($n) ? (int) $n : (self::NUMEROS[$n] ?? 1);
    }

    /**
     * Habilidades da carta: { hab: [...], parcial: bool (algum trecho ficou para resolver à mão) }.
     * $nome: nome da própria carta (o texto pode falar dela pelo nome); $nomes: nomes (normalizados) do catálogo, para
     * "invoque um Cabrito".
     */
    public static function ler(?string $texto, string $tipo, string $nome = '', array $nomes = []): array
    {
        if ($tipo === 'equipo' || ($tipo !== 'lenda' && preg_match('/\bequip/', self::normalizar((string) $texto)))) {
            return self::equipamento((string) $texto);
        }
        $eu = self::nomesProprios($nome);
        $hab = [];
        $parcial = false;
        $atual = null;
        foreach (preg_split('/[.;!\r\n]+/', (string) $texto) as $frase) { // a quebra de linha separa frases
            $frase = trim(self::normalizar($frase), ' ,:-');
            if ($frase === '') {
                continue;
            }
            [$gs, $resto] = self::gatilho($frase, $eu);
            if ($gs === null && preg_match(self::GATILHO_DESCONHECIDO, $frase)) {
                $parcial = true;
                $atual = ['manual'];
                continue;
            }
            if ($gs !== null) {
                $atual = $gs;
            }
            $gs ??= $atual ?? [$tipo === 'lenda' ? 'entrar' : 'conjurar'];
            if ($gs === ['manual'] || preg_match(self::CONDICAO, $resto)) {
                $parcial = true;
                $atual = ['manual'];
                continue;
            }
            $passos = [];
            $sujeito = null;
            $entendeu = true;
            foreach (preg_split('/\s*(?:,|\be depois\b|\bdepois\b|\bentao\b|\be\b(?= [a-z0-9+-]))\s*/', $resto) as $c) {
                $c = trim($c, ' ,');
                if ($c === '' || self::ruido($c)) {
                    continue;
                }
                $p = self::clausula($c, $sujeito, $eu, $nomes);
                if ($p === null) {
                    $entendeu = false;
                    break;
                }
                $rep = max(1, (int) ($p['rep'] ?? 1));
                unset($p['rep']);
                for ($k = 0; $k < $rep; $k++) {
                    $passos[] = $p;
                }
            }
            if (!$entendeu) {
                $parcial = true;
                continue;
            }
            foreach ($gs as $g) {
                $ult = array_key_last($hab);
                if ($ult !== null && $hab[$ult]['g'] === $g && count($gs) === 1) {
                    array_push($hab[$ult]['passos'], ...$passos);
                } elseif ($passos) {
                    $hab[] = ['g' => $g, 'passos' => $passos];
                }
            }
        }

        return ['hab' => $hab, 'parcial' => $parcial];
    }

    /**
     * Feitiço de equipar ("Equipe a uma Lenda sua: ela ganha +2/+1 e Voar"): ao conjurar, pede uma Lenda sua e fica
     * vinculado a ela com o bônus somado de todas as frases. Frase que não é bônus nem o "equipe…" fica à mão (parcial).
     */
    private static function equipamento(string $texto): array
    {
        $atk = 0;
        $vida = 0;
        $pal = [];
        $parcial = false;
        foreach (preg_split('/[.;!\r\n]+/', $texto) as $frase) {
            $f = trim(self::normalizar($frase), ' ,:-');
            $f = trim(preg_replace('/^(?:equip(?:e|a|ar|amento)\w*\b(?:\s+(?:a|em|n?uma|o|na|no)\b)*(?:\s+(?:uma\s+)?lendas?(?:\s+(?:sua|aliada|alvo|que voce controla))*)?|custo de equipar\s*\d*)\s*[:,-]?\s*/', '', $f), ' ,:-');
            if ($f === '') {
                continue;
            }
            $achou = false;
            if (preg_match('/\bbonus|\baumentad|\brecebid|\bse (?:o |a )?equipad[oa] for inimig/', $f)) {
                $parcial = true; // regra sobre outros bônus ou condição: fica à mão
                continue;
            }
            if (preg_match_all('/([+-]\d+)\s*\/\s*([+-]?\d+)/', $f, $mm, PREG_SET_ORDER)) { // "+1/+1", "+0/5"
                foreach ($mm as $m) {
                    $atk += (int) $m[1];
                    $vida += (int) $m[2];
                }
                $achou = true;
            }
            foreach (self::PALAVRAS as $nomePal => $chave) {
                if (preg_match('/\b' . preg_quote($nomePal, '/') . '\b/', $f)) {
                    $pal[] = $chave;
                    $achou = true;
                }
            }
            $parcial = $parcial || !$achou;
        }
        $passo = ['a' => 'equipar', 'alvo' => 'lenda', 'filtro' => 'meu', 'atk' => $atk, 'vida' => $vida, 'pal' => array_values(array_unique($pal))];

        return ['hab' => [['g' => 'conjurar', 'passos' => [$passo]]], 'parcial' => $parcial];
    }

    /** Jeitos de o texto falar da própria carta: o nome inteiro, o primeiro nome e "esta/essa lenda". */
    private static function nomesProprios(string $nome): array
    {
        $n = self::normalizar($nome);
        $lista = ['esta lenda', 'essa lenda', 'esta carta', 'essa carta'];
        if ($n !== '') {
            $lista[] = $n;
            $partes = preg_split('/[\s,]+/', $n);
            foreach ([$partes[0], end($partes)] as $parte) { // "Parasita Temporal, Azazyel": Parasita e Azazyel
                if (mb_strlen($parte) > 2) {
                    $lista[] = $parte;
                }
            }
        }

        return $lista;
    }

    private static function euRe(array $eu): string
    {
        return '(?:' . implode('|', array_map(fn ($x) => preg_quote($x, '/'), $eu)) . ')';
    }

    /** Trechos que só completam a frase anterior ("e adicione-as à sua mão", "embaralhe"). */
    private static function ruido(string $c): bool
    {
        return (bool) preg_match('/^(?:adicione-?(?:as|os|a|o)? (?:a|em|na) (?:sua )?mao|coloque-?(?:as|os|a|o)? (?:em|na) (?:sua )?mao'
            . '|embaralhe(?: seu (?:baralho|deck))?|ate o (?:fim|final) do turno|neste turno|sem pagar (?:seus|os) custos)$/', $c);
    }

    /** [gatilhos ou null, resto da frase sem o gatilho]. */
    private static function gatilho(string $f, array $eu): array
    {
        $quem = '(?:' . self::euRe($eu) . '\s+)?';
        $ev = self::EVENTO;
        // começo: "quando invocado", "quando <nome> entrar, atacar ou morrer", "toda vez que essa lenda atacar",
        // "quando invocado ou quando atacar", "ao invocar <nome>"
        if (preg_match('/^(?:quando|ao|assim que|sempre que|toda vez que)\s+' . $quem . "($ev(?:\s*(?:,|ou|e)\s*(?:quando\s+)?$ev)*)\s*(?:,|:|$)/", $f, $m)
            || preg_match('/^ao (invocar|entrar) ' . self::euRe($eu) . '\s*(?:,|:|$)/', $f, $m)) {
            preg_match_all("/$ev/", $m[1], $evs);
            $gs = [];
            foreach ($evs[0] as $x) {
                $gs[] = match (true) {
                    (bool) preg_match('/^(?:atac)/', $x)          => 'atacar',
                    (bool) preg_match('/^(?:morr|cair|for d)/', $x) => 'morrer',
                    default                                       => 'entrar',
                };
            }

            return [array_values(array_unique($gs)), trim(substr($f, strlen($m[0])), ' ,:-')];
        }
        $turno = [
            'inicio_meu' => '(?:todo|em todo|a cada|cada|no|em cada|ao)?\s*(?:inicio|comeco) (?:do|de) seu turno',
            'inicio'     => '(?:todo|em todo|a cada|cada|no|em cada|ao)?\s*(?:inicio|comeco) (?:de cada|do|de todo|de) turno',
            'fim_meu'    => '(?:todo|em todo|a cada|cada|no|em cada|ao)?\s*(?:fim|final) (?:do|de) seu turno',
            'fim'        => '(?:todo|em todo|a cada|cada|no|em cada|ao)?\s*(?:fim|final) (?:de cada|do|de todo|de) turno',
        ];
        foreach ($turno as $g => $re) {
            if (preg_match("/^$re\s*(?:,|:|$)/", $f, $m)) {
                return [[$g], trim(substr($f, strlen($m[0])), ' ,:-')];
            }
            if (preg_match("/^(.+?),?\s+$re$/", $f, $m)) { // gatilho no fim da frase
                return [[$g], $m[1]];
            }
        }
        if (preg_match('/^(.+?),?\s+(?:quando|ao) (?:for |ser )?(?:invocad[oa]|entrar)$/', $f, $m)) {
            return [['entrar'], $m[1]];
        }

        return [null, $f];
    }

    /** Lado de quem age, pelo que vem antes do verbo; null se não há sujeito ali. */
    private static function sujeito(string $antes): ?string
    {
        return match (true) {
            (bool) preg_match('/\b(?:jogador|adversario|oponente|inimigo) alvo\b/', $antes)                                => 'jogador',
            (bool) preg_match('/\b(?:cada|todo|todos os|seus|os|o|seu|um) (?:adversario|oponente|inimigo)s?\b|\bexceto voce\b|\bos outros jogadores\b|\btodo outro jogador\b|\btodos os outros\b/', $antes) => 'inimigos',
            (bool) preg_match('/\b(?:cada|todo|todos os|todos) (?:jogador(?:es)?)\b|\btodos\b/', $antes)                  => 'todos',
            (bool) preg_match('/\bvoce\b/', $antes)                                                                        => 'eu',
            default => null,
        };
    }

    /** Escolha de Lenda: de quem (pelo texto). */
    private static function filtro(string $t): string
    {
        return match (true) {
            (bool) preg_match('/inimig|adversari|oponente/', $t)                => 'inimigo',
            (bool) preg_match('/\bsuas?\b|aliad|que voce controla/', $t)       => 'meu',
            default => 'qualquer',
        };
    }

    /** Alvo de um efeito em Lendas/jogadores pelo trecho depois do verbo; null se o trecho fala de algo que o leitor não conhece. */
    private static function alvo(string $t, string $semAlvo, array $eu): ?array
    {
        $t = trim($t);
        return match (true) {
            (bool) preg_match('/(?:todas as |as )?lendas (?:inimigas|adversarias|d[oa]s? (?:adversario|oponente|inimigo)s?)/', $t) => ['lendas', 'inimigo'],
            (bool) preg_match('/(?:todas as |as )?(?:suas (?:outras )?lendas|lendas aliadas|lendas que voce controla)/', $t)     => ['lendas', 'meu'],
            (bool) preg_match('/todas as lendas|cada lenda/', $t)                                                                => ['lendas', 'qualquer'],
            (bool) preg_match('/\blendas? (?:inimigas? |aliadas? )?alvo\b|\b(?:uma|a) lenda\b|^lendas?$|^' . self::NUM . ' lendas\b/', $t) => ['lenda', self::filtro($t)],
            (bool) preg_match('/\b(?:cada|todo|todos os|seus) (?:adversario|oponente|inimigo)s?\b|\btodos os outros jogadores\b|\btodo outro jogador\b/', $t) => ['inimigos', null],
            (bool) preg_match('/\b(?:cada|todo|todos os) jogador(?:es)?\b/', $t)                                                => ['todos', null],
            (bool) preg_match('/\b(?:jogador|adversario|oponente) alvo\b|\b(?:o|um) (?:adversario|oponente)\b/', $t)             => ['jogador', preg_match('/adversario|oponente/', $t) ? 'inimigo' : 'qualquer'],
            (bool) preg_match('/\b(?:a|em) voce\b|\bvoce mesmo\b/', $t)                                                          => ['eu', null],
            (bool) preg_match('/^(?:(?:a|em|para|ao?)\s+)?(?:si|ela|ele|nela|nele|' . self::euRe($eu) . ')$/', $t)               => ['fonte', null],
            (bool) preg_match('/^(?:(?:a|em|para)\s+)?(?:um |qualquer )?alvo$|^(?:a|em) qualquer alvo$/', $t)                    => ['qualquer', 'qualquer'],
            $t === ''                                                                                                            => [$semAlvo, $semAlvo === 'fonte' ? null : 'qualquer'],
            default => null,
        };
    }

    /** Quantas vezes o alvo se repete ("duas lendas alvo", "mate duas lendas"). */
    private static function vezes(string $t): int
    {
        return preg_match('/\b' . self::NUM . ' lendas\b/', $t, $m) ? self::num($m[1]) : 1;
    }

    /**
     * Uma ação. Devolve o passo ou null se não entendeu.
     * $sujeito guarda o sujeito da frase para as ações seguintes na 3ª pessoa.
     */
    private static function clausula(string $c, ?string &$sujeito, array $eu, array $nomes): ?array
    {
        $N = self::NUM;
        $temp = (bool) preg_match('/ate o (?:fim|final) do turno|neste turno|nesse turno/', $c);
        $c = trim(preg_replace('/,?\s*(?:ate o (?:fim|final) do turno|neste turno|nesse turno)/', '', $c));
        // quem age: sujeito antes do verbo; sem ele, o da frase (3ª pessoa) ou quem controla a carta (imperativo)
        $quem = function (string $antes, string $verbo) use (&$sujeito): ?string {
            $s = self::sujeito($antes);
            if ($s !== null) {
                $sujeito = $s;

                return $s;
            }
            if (trim($antes) !== '') {
                return null; // sujeito que o leitor não conhece ("o jogador que o lançou")
            }

            return preg_match(self::TERCEIRA, $verbo) ? ($sujeito ?? 'eu') : 'eu';
        };
        $jogador = function (string $acao, array $m, int $n) use ($quem): ?array {
            $q = $quem($m[1], $m[2]);

            return $q === null ? null : ['a' => $acao, 'n' => $n, 'alvo' => $q];
        };

        if (preg_match('/^(.*?)\b(vir[ae]m?|virar|tom[ae]m?|tomar|beb[ae]m?|beber)\s+' . $N . '\s+(?:shots?|doses?)$/', $c, $m)) {
            return $jogador('shot', $m, self::num($m[3]));
        }
        if (preg_match('/^(.*?)\b(compr(?:e|a|am|ar))\s+' . $N . '\s+cartas?$/', $c, $m)) {
            return $jogador('comprar', $m, self::num($m[3]));
        }
        // descarte é sempre da mão de quem descarta (nunca do campo): "descarte uma lenda", "descarte 2 cartas
        // aleatórias", "descarte sua mão", "o oponente descarta uma carta"
        if (preg_match('/^(.*?)\b(descart(?:e|a|am|ar))\s+(?:(?:a |sua |toda a |todas as cartas d[ea] )?(?:sua )?mao(?: inteira)?|todas as (?:suas )?cartas(?: d[ea] (?:sua )?mao)?)$/', $c, $m)) {
            return $jogador('descartar', $m, 99) + ['tudo' => true];
        }
        if (preg_match('/^(.*?)\b(descart(?:e|a|am|ar))\s+' . $N . '(?:\s+(cartas?|lendas?|feiticos?))?(\s+aleatori[ao]s?|\s+ao acaso)?(?:\s+(?:d[ea]|em) (?:sua |seu |s?ua )?mao)?(\s+aleatori[ao]s?|\s+ao acaso)?$/', $c, $m)) {
            $p = $jogador('descartar', $m, self::num($m[3]));
            if ($p === null) {
                return null;
            }
            $tipo = $m[4] ?? '';
            if (str_starts_with($tipo, 'lenda')) {
                $p['filtro'] = 'lenda';
            } elseif (str_starts_with($tipo, 'feitico')) {
                $p['filtro'] = 'feitico';
            }
            if (trim(($m[5] ?? '') . ($m[6] ?? '')) !== '') {
                $p['aleatorio'] = true;
            }

            return $p;
        }
        if (preg_match('/^(.*?)\b(sacrifiqu?e|sacrifica|sacrificam|sacrificar)\s+' . $N . '\s+lendas?(?:\s+para invocar\s+' . self::euRe($eu) . ')?$/', $c, $m)) {
            return $jogador('sacrificar', $m, self::num($m[3]));
        }
        if (preg_match('/^(.*?)\b(ganh(?:e|a|am|ar)|receb(?:e|a|am|er))\s+' . $N . '\s+(?:de\s+)?energias?$/', $c, $m)) {
            return $jogador('energia', $m, self::num($m[3]));
        }
        if (preg_match('/^(.*?)\b(perd(?:e|a|em|er)|pagu?(?:e|a|am|ar))\s+' . self::DINHEIRO . '$/', $c, $m)) {
            return $jogador('perder', $m, (int) ($m[3] ?: ($m[4] ?: $m[5])));
        }
        if (preg_match('/^(.*?)\b(recuper(?:e|a|am|ar)|ganh(?:e|a|am|ar)|cur(?:e|a|am|ar)|receb(?:e|a|am|er))\s+' . self::DINHEIRO . '$/', $c, $m)) {
            return $jogador('ganhar', $m, (int) ($m[3] ?: ($m[4] ?: $m[5])));
        }
        // "mill N" / "jogue ao cemitério as N (primeiras) cartas do (topo do) seu baralho"
        if (preg_match('/^(.*?)\b(mill|moa|moe|jogue ao cemiterio as|jogue no cemiterio as)\s+' . $N . '(?:\s+(?:primeiras\s+)?cartas?(?:\s+do (?:topo do )?seu (?:baralho|deck))?)?$/', $c, $m)) {
            return $jogador('moer', $m, self::num($m[3]));
        }
        if (preg_match('/^(?:caus(?:e|a|am|ar)|inflij(?:a|e)|d[ea])\s+' . $N . '\s+(?:pontos? de\s+|de\s+)?dano\b(.*)$/', $c, $m)) {
            $alvo = self::alvo(preg_replace('/^\s*(?:a|em|no|na|ao|nos|nas|para)\s+/', '', $m[2]), 'qualquer', $eu);

            return $alvo === null ? null : ['a' => 'dano', 'n' => self::num($m[1]), 'alvo' => $alvo[0], 'filtro' => $alvo[1], 'rep' => self::vezes($m[2])];
        }
        if (preg_match('/^(?:destru(?:a|ir)|elimine|mate)\s+(.*)$/', $c, $m)) {
            if (!preg_match('/\blendas?\b|^' . self::euRe($eu) . '$/', $m[1])) {
                return null; // "destrua todas as cartas": o motor só destrói Lendas
            }
            $alvo = self::alvo($m[1], 'lenda', $eu);
            if ($alvo === null || !in_array($alvo[0], ['lenda', 'lendas', 'fonte'], true)) {
                return null;
            }

            return ['a' => 'destruir', 'alvo' => $alvo[0], 'filtro' => $alvo[1], 'rep' => self::vezes($m[1])];
        }
        if (preg_match('/\bcemiterio\b/', $c) && preg_match('/^(?:recuper\w*|devolv\w*|traga|retorne|pegue|resgate|invoque|reviva)\s+(?:um|uma|a|o)\s+(lenda|feitico|carta)\s+(?:d[eo]|n[oa]) (?:seu |sua )?cemiterio(?:\s+(?:para|a|na) (?:sua )?(mao|mesa|campo))?(?:\s+sem pagar (?:seus|os) custos)?$/', $c, $m)) {
            $mesa = str_starts_with($c, 'invoque') || str_starts_with($c, 'reviva') || in_array($m[2] ?? '', ['mesa', 'campo'], true);

            return ['a' => 'recuperar', 'alvo' => 'eu', 'destino' => $mesa ? 'mesa' : 'mao', 'filtro' => $m[1] === 'carta' ? null : $m[1]];
        }
        if (preg_match('/^(?:devolv(?:a|e)|retorne|mande)\s+(.*?)\s+(?:para|a|pra)\s+(?:a\s+)?(?:sua\s+)?mao(?: d[eo] (?:seu )?dono)?$/', $c, $m)) {
            $alvo = self::alvo($m[1], 'lenda', $eu);
            if ($alvo === null || !in_array($alvo[0], ['lenda', 'lendas', 'fonte'], true)) {
                return null;
            }

            return ['a' => 'devolver', 'alvo' => $alvo[0], 'filtro' => $alvo[1]];
        }
        // "saque uma propriedade fisiomante de seu baralho", "busque no seu baralho por uma lenda"
        if (preg_match('/^(?:saqu|busqu|procur|pegu|tutor|puxe)\w*\s+(?:(.+?)\s+(?:d[oe]|em|no)\s+(?:seu\s+|sua\s+)?(?:baralho|deck|grimorio)|(?:no|em) (?:seu )?(?:baralho|deck) (?:por|uma?|pela?)\s*(.+))$/', $c, $m)) {
            $o = trim(($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? ''));
            $n = preg_match('/^' . $N . '\s+/', $o, $q) ? self::num($q[1]) : 1;
            $dica = trim(preg_replace('/^(?:' . $N . '|uma?|a|o)\s+/', '', $o));

            return ['a' => 'buscar', 'alvo' => 'eu', 'n' => $n, 'dica' => $dica,
                    'filtro' => preg_match('/^lendas?$/', $dica) ? 'lenda' : (preg_match('/^feiticos?$/', $dica) ? 'feitico' : null)];
        }
        if (preg_match('/^(?:cri[ea]|invoqu(?:e|a)|ger[ea]|coloque)\s+(?:' . $N . '\s+)?fichas?\s*(?:(?:de|chamadas?)\s+([a-z ]+?)\s+)?(\d+)\s*\/\s*(\d+)(?:\s+(?:de\s+)?([a-z ]+?))?(?:\s+(?:com|e)\s+(.+))?$/', $c, $m)) {
            // nome antes ou depois do 2/2 ("ficha de animal 2/2", "ficha 1/1 de olhinho"); palavras-chave no fim
            $resto = trim(($m[5] ?? '') . ' ' . ($m[6] ?? ''));
            $pal = [];
            $nome = trim($m[2] ?? '');
            foreach (self::PALAVRAS as $p => $ch) {
                if (preg_match('/\b' . preg_quote($p, '/') . '\b/', $resto)) {
                    $pal[] = $ch;
                    $resto = trim(str_replace($p, '', $resto));
                }
            }
            if ($nome === '' && $resto !== '' && !preg_match('/\b(?:e|com)\b/', $resto) && empty($m[6] ?? '')) {
                $nome = $resto; // "ficha 1/1 de olhinho"
                $resto = '';
            }
            if (trim(preg_replace('/\b(?:e|com)\b/', '', $resto)) !== '') {
                return null; // "com o mesmo efeito", "com metade da defesa"…
            }

            return ['a' => 'ficha', 'n' => ($m[1] ?? '') !== '' ? self::num($m[1]) : 1, 'alvo' => 'eu',
                    'nome' => $nome !== '' ? mb_convert_case($nome, MB_CASE_TITLE) : 'Ficha', 'atk' => (int) $m[3], 'vida' => (int) $m[4],
                    'pal' => array_values(array_unique($pal))];
        }
        // "invoque um Cabrito": outra carta do catálogo, pelo nome
        if (preg_match('/^invoqu(?:e|a)\s+(?:' . $N . '|outr[oa])\s+(.+?)(?:\s+sem pagar (?:seus|os) custos)?$/', $c, $m)) {
            $alvoNome = trim($m[2]);
            foreach ($nomes as $n) {
                if ($n !== '' && ($alvoNome === $n || $alvoNome === $n . 's')) {
                    return ['a' => 'invocar', 'alvo' => 'eu', 'carta' => $n, 'n' => $m[1] !== '' && $m[1] !== null ? self::num($m[1]) : 1];
                }
            }

            return null;
        }
        if (preg_match('/^(.*?)\b(ganh(?:e|a|am)|receb(?:e|a|am)|adicione)?\s*([+-]\d+)\s*\/\s*([+-]\d+)(.*)$/', $c, $m)) {
            $alvo = self::alvoDeBonus($m[1], $m[5], $eu);

            return $alvo === null ? null : ['a' => 'buff', 'atk' => (int) $m[3], 'vida' => (int) $m[4], 'alvo' => $alvo[0], 'filtro' => $alvo[1], 'temp' => $temp];
        }
        foreach (self::PALAVRAS as $nomePal => $chave) {
            if (preg_match('/^(.*?)\b(?:(?:aplique|conceda|de|da|ganha|ganham|ganhe|recebe|recebem|receba|se torna|se tornam|adquire)\s+)?'
                . preg_quote($nomePal, '/') . '\b(.*)$/', $c, $m)) {
                $alvo = self::alvoDeBonus($m[1], $m[2], $eu);

                return $alvo === null ? null : ['a' => 'palavra', 'pal' => $chave, 'alvo' => $alvo[0], 'filtro' => $alvo[1], 'temp' => $temp];
            }
        }

        return null;
    }

    /**
     * Quem ganha um bônus (+1/+1 ou palavra-chave): "a lenda alvo ganha…", "aplique … a uma Lenda alvo", "suas lendas
     * ganham…" ou, sem ninguém nomeado ("Voar, sobrepujar", "recebe +3/+3"), a própria carta.
     */
    private static function alvoDeBonus(string $antes, string $depois, array $eu): ?array
    {
        $antes = trim(preg_replace('/\b(?:aplique|conceda|de|da|adicione)\s*$/', '', trim($antes)));
        $depois = trim(preg_replace('/^(?:a|em|para|ao?)\s+/', '', trim($depois)));
        if ($antes === '' && $depois === '') {
            return ['fonte', null];
        }
        $alvo = self::alvo($antes !== '' ? $antes : $depois, 'fonte', $eu);
        if ($alvo === null || !in_array($alvo[0], ['lenda', 'lendas', 'fonte'], true) || ($antes !== '' && $depois !== '')) {
            return null;
        }

        return $alvo;
    }

    /* ================================================================== */
    /*  Resumo legível (mostrado nos detalhes da carta)                    */
    /* ================================================================== */

    private const GATILHOS = [
        'entrar' => 'Ao entrar', 'conjurar' => 'Ao conjurar', 'morrer' => 'Ao cair', 'inicio' => 'No início do turno do dono',
        'inicio_meu' => 'No início do seu turno', 'fim' => 'Todo fim de turno', 'fim_meu' => 'No fim do seu turno', 'atacar' => 'Ao atacar',
    ];

    public static function resumo(array $hab): string
    {
        $partes = [];
        foreach ($hab as $h) {
            $partes[] = (self::GATILHOS[$h['g']] ?? $h['g']) . ': ' . implode('; ', array_map([self::class, 'textoPasso'], $h['passos'])) . '.';
        }

        return implode(' ', $partes);
    }

    private static function quemTexto(?string $alvo): string
    {
        return match ($alvo) {
            'todos' => 'todo jogador', 'inimigos' => 'cada adversário', 'jogador' => 'o jogador escolhido', default => 'você',
        };
    }

    private static function lendaTexto(array $p): string
    {
        $f = ['meu' => ' sua', 'inimigo' => ' inimiga'][$p['filtro'] ?? ''] ?? '';

        return match ($p['alvo']) {
            'lenda' => "uma Lenda$f à escolha", 'lendas' => $f === ' sua' ? 'todas as suas Lendas' : 'todas as Lendas' . ($f ? $f . 's' : ''),
            'fonte' => 'esta Lenda', 'qualquer' => 'uma Lenda ou jogador à escolha', default => self::quemTexto($p['alvo']),
        };
    }

    public static function textoPasso(array $p): string
    {
        $n = (int) ($p['n'] ?? 1);
        $quem = self::quemTexto($p['alvo'] ?? 'eu');
        $temp = !empty($p['temp']) ? ' até o fim do turno' : '';
        $pal = $p['a'] === 'ficha' ? implode(', ', array_map(fn ($k) => Gladio::PALAVRAS[$k] ?? $k, $p['pal'] ?? [])) : '';

        return match ($p['a']) {
            'shot'       => "$quem toma $n shot" . ($n > 1 ? 's' : ''),
            'comprar'    => "$quem compra $n carta" . ($n > 1 ? 's' : ''),
            'descartar'  => !empty($p['tudo']) ? "$quem descarta a mão inteira"
                : "$quem descarta $n " . (['lenda' => 'Lenda', 'feitico' => 'feitiço'][$p['filtro'] ?? ''] ?? 'carta') . ($n > 1 ? 's' : '')
                  . (!empty($p['aleatorio']) ? ' ao acaso' : '') . ' da mão',
            'sacrificar' => "$quem sacrifica $n Lenda" . ($n > 1 ? 's' : ''),
            'moer'       => "$quem joga $n carta" . ($n > 1 ? 's' : '') . ' do topo do baralho no cemitério',
            'energia'    => "$quem ganha $n de energia",
            'perder'     => "$quem perde $n de vida",
            'ganhar'     => "$quem recupera $n de vida",
            'dano'       => "$n de dano em " . self::lendaTexto($p),
            'destruir'   => 'destrói ' . self::lendaTexto($p),
            'devolver'   => 'devolve ' . self::lendaTexto($p) . ' para a mão do dono',
            'recuperar'  => 'escolhe ' . (($p['filtro'] ?? null) === 'lenda' ? 'uma Lenda' : (($p['filtro'] ?? null) === 'feitico' ? 'um feitiço' : 'uma carta')) . ' do seu cemitério para ' . ($p['destino'] === 'mesa' ? 'a mesa' : 'a mão'),
            'buscar'     => 'busca ' . ($n > 1 ? "$n × " : '') . ($p['dica'] ?? 'uma carta') . ' no seu baralho e põe na mão',
            'ficha'      => "cria $n ficha" . ($n > 1 ? 's' : '') . " {$p['nome']} {$p['atk']}/{$p['vida']}" . ($pal !== '' ? " com $pal" : ''),
            'invocar'    => "invoca $n × " . mb_convert_case($p['carta'] ?? '', MB_CASE_TITLE),
            'equipar'    => 'equipa uma Lenda sua' . (($b = array_merge(($p['atk'] ?? 0) || ($p['vida'] ?? 0) ? [sprintf('%+d/%+d', $p['atk'], $p['vida'])] : [],
                array_map(fn ($k) => Gladio::PALAVRAS[$k] ?? $k, $p['pal'] ?? []))) ? ' (' . implode(', ', $b) . ')' : ''),
            'buff'       => sprintf('%+d/%+d em ', $p['atk'], $p['vida']) . self::lendaTexto($p) . $temp,
            'palavra'    => (Gladio::PALAVRAS[$p['pal']] ?? $p['pal']) . ' para ' . self::lendaTexto($p) . $temp,
            default      => $p['a'],
        };
    }
}
