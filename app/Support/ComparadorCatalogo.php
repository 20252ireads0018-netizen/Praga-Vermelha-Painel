<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Compara a descrição que o jogador escreveu de uma maldição com o texto real do catálogo,
 * sem serviço externo: normalização, stopwords, stemming leve, sinônimos do domínio, TF-IDF,
 * bigramas, estrutura do texto e metadados (raridade, órgão hospedado, apelido).
 *
 * PESOS (soma 100). Texto real puro rende no máximo 77 (raridade/órgão/apelido não estão no texto):
 *   significado  32  cosseno TF-IDF com sinônimos (65%) + precisão ponderada do vocabulário (35%)
 *   termos_chave 16  os 15 termos alfabéticos de maior TF-IDF do texto real presentes (70% já vale 100)
 *   ordem        12  bigramas canônicos (por frase) do jogador que existem no real (45% já vale 100)
 *   estrutura    11  frases completas (pontuação, maiúscula, nº de frases, tamanho médio) e elementos
 *                    de regra do real (números, NdM, ameaça, turno, teste). Só vale se estiver no assunto.
 *   concisao      6  ideal entre 50% (de no máx. 260 palavras) e 130% do real; abaixo é proporcional,
 *                    acima perde linearmente até 0 em 3,25x. Também só vale se estiver no assunto.
 *   raridade      8  cita a raridade certa; outra raridade citada = 0 e -3 pontos no total
 *   orgao         8  órgão hospedado (parcial para "a&b&c"), com sinônimos (coração/peito/cardíaco...)
 *   apelido       7  apelido popular (singular/plural/feminino)
 *
 * PENALIDADES (multiplicativas, cada uma em [0,6; 1], aplicadas depois da soma):
 *   redundancia    trigramas repetidos acima do texto real e baixa diversidade lexical (TTR por janela)
 *   prolixidade    acima de 1,6x o real: 2x = 0,80; 3x = 0,65; 4x ou mais = 0,60
 *   desvio         >45% dos termos de conteúdo não existem no real (nem nos sinônimos), exceto metadados
 *   lista_palavras poucas palavras de ligação, sem frases ou frases gigantes (keyword stuffing)
 */
class ComparadorCatalogo
{
    public const PESOS = [
        'significado' => 32, 'termos_chave' => 16, 'ordem' => 12, 'estrutura' => 11,
        'concisao' => 6, 'raridade' => 8, 'orgao' => 8, 'apelido' => 7,
    ];
    public const TOP_TERMOS = 15;
    public const REF_MAX_PALAVRAS = 260;
    public const PENALIDADE_RARIDADE = 3;

    /** Aliases de órgãos (já normalizados, sem acento). Órgãos fora daqui usam o grupo de sinônimos. */
    private const ORGAOS = [
        'coracao'        => ['coracao', 'peito', 'cardiaco', 'cardio'],
        'cerebro'        => ['cerebro', 'mente', 'cabeca', 'cerebral', 'encefalo', 'cranio'],
        'ouvidos'        => ['ouvidos', 'ouvido', 'orelha', 'orelhas', 'audicao', 'auditivo', 'timpanos'],
        'olhos'          => ['olhos', 'olho', 'visao', 'ocular', 'vista'],
        'maos'           => ['maos', 'mao', 'dedos', 'palma'],
        'caixa toracica' => ['caixa toracica', 'torax', 'toracico', 'costelas', 'costela', 'peito'],
    ];

    private const ELEMENTOS_REGRA = [
        'ameaca' => ['ameaca'],
        'turno'  => ['turno', 'rodada'],
        'teste'  => ['teste', 'rolagem', 'jogada'],
    ];

private const STOPWORDS = 'a o as os um uma uns umas de do da dos das dum duma em no na nos nas num numa por pelo pela pelos pelas para pra com sem sob sobre entre ate apos ante perante contra desde e ou mas nem que se como quando onde porque pois porem logo entao assim tambem ainda ja so apenas muito muita muitos muitas mais menos bem mal tao tanto tanta ele ela eles elas eu tu nos vos voce voces me te lhe lhes mim ti si meu minha meus minhas teu tua teus tuas seu sua seus suas nosso nossa nossos nossas este esta estes estas esse essa esses essas isto isso aquilo aquele aquela aqueles aquelas qual quais quem cujo cuja ser sou es eh somos sao era eram foi foram sera serao seja sejam sendo sido estar estou estamos estao estava estavam estive esteve estiver estiverem estando estado ter tenho tem temos tinha tinham tive teve tiver tiverem tendo tido haver ha havia houve havera hao poder posso pode podem podemos podia podiam pudesse puder poderia possa possam ir vai vao fazer faz fazem fez cada todo toda todos todas outro outra outros outras mesmo mesma algum alguma alguns algumas nenhum nenhuma qualquer quaisquer';

    /** Grupos de sinônimos: o primeiro termo é o canônico. Frases (várias palavras) são aceitas. */
    private const SINONIMOS = [
        ['dano', 'ferimento', 'ferida', 'machucado', 'lesão', 'machucar', 'ferir'],
        ['aura', 'energia', 'daemônica'],
        ['pv', 'vida', 'pontos de vida', 'saúde', 'vitalidade'],
        ['sanidade', 'mente', 'loucura', 'insanidade', 'psique'],
        ['teste', 'rolagem', 'dado', 'rolar', 'jogada'],
        ['vantagem', 'bônus', 'benefício', 'ganho', 'ganhar'],
        ['desvantagem', 'penalidade', 'ônus', 'prejuízo', 'castigo'],
        ['alvo', 'inimigo', 'oponente', 'criatura', 'adversário', 'vítima'],
        ['aliado', 'companheiro', 'amigo', 'parceiro'],
        ['turno', 'rodada', 'round'],
        ['cena', 'combate', 'batalha', 'luta', 'confronto'],
        ['amaldiçoado', 'portador', 'hospedeiro', 'usuário', 'possuidor'],
        ['maldição', 'praga', 'feitiço', 'encantamento'],
        ['demônio', 'diabo', 'entidade', 'espírito', 'inferno'],
        ['corpo', 'físico', 'organismo', 'carcaça'],
        ['morte', 'morrer', 'falecer', 'perecer', 'matar'],
        ['cura', 'curar', 'regenerar', 'recuperar', 'sarar', 'restaurar'],
        ['tempo', 'duração', 'período', 'temporal', 'momento'],
        ['aumentar', 'elevar', 'crescer', 'ampliar', 'expandir', 'incrementar'],
        ['diminuir', 'reduzir', 'encolher', 'minguar', 'abaixar'],
        ['força', 'potência', 'vigor', 'poderio'],
        ['rápido', 'veloz', 'ágil', 'ligeiro', 'velocidade'],
        ['lento', 'devagar', 'vagaroso', 'lentidão', 'enlentecer'],
        ['invocar', 'conjurar', 'convocar', 'evocar'],
        ['olhos', 'visão', 'olhar', 'ver', 'enxergar', 'vista'],
        ['ouvidos', 'audição', 'escutar', 'ouvir', 'tímpanos', 'orelha'],
        ['mãos', 'dedos', 'toque', 'tocar', 'palma'],
        ['coração', 'peito', 'cardíaco', 'batimento'],
        ['cérebro', 'cerebral', 'cabeça', 'encéfalo', 'crânio'],
        ['sangue', 'hemorragia', 'sangrar', 'sangramento'],
        ['fogo', 'chama', 'queimar', 'incêndio', 'calor', 'flamejante', 'queimadura'],
        ['som', 'ruído', 'barulho', 'estrondo', 'sonoro'],
        ['medo', 'pavor', 'terror', 'temor', 'horror', 'pânico'],
        ['gelo', 'frio', 'congelar', 'gelado'],
        ['arma', 'espada', 'lâmina', 'faca'],
        ['ataque', 'atacar', 'golpe', 'investida', 'ofensiva'],
        ['defesa', 'defender', 'proteção', 'proteger', 'escudo', 'resistência'],
        ['esquiva', 'esquivar', 'desviar', 'evitar'],
        ['custo', 'gasto', 'gastar', 'consumir', 'pagar'],
        ['chance', 'probabilidade', 'possibilidade', 'risco', 'sorte'],
        ['efeito', 'consequência', 'resultado', 'manifestação'],
        ['técnica', 'habilidade', 'perícia', 'poder especial', 'arte', 'talento'],
        ['nível', 'grau', 'estágio', 'ameaça', 'intensidade'],
        ['sentido', 'percepção', 'sensorial', 'sensação'],
        ['pele', 'derme', 'epiderme', 'couro'],
        ['osso', 'esqueleto', 'ossatura'],
        ['músculo', 'musculatura', 'muscular'],
        ['boca', 'lábios', 'dentes', 'mandíbula'],
        ['língua', 'fala', 'falar', 'palavra', 'voz', 'dizer', 'idioma'],
        ['pulmão', 'respiração', 'respirar', 'fôlego', 'ar'],
        ['perna', 'pé', 'andar', 'correr'],
        ['rosto', 'semblante', 'feição'],
        ['estômago', 'barriga', 'fome', 'ventre', 'abdômen'],
        ['tendão', 'ligamento', 'articulação'],
        ['dor', 'sofrimento', 'agonia', 'tormento', 'doer'],
        ['dormir', 'sono', 'descansar', 'repouso', 'descanso'],
        ['memória', 'lembrança', 'lembrar', 'esquecer', 'esquecimento'],
        ['voo', 'voar', 'levitar', 'flutuar', 'planar'],
        ['sombra', 'escuridão', 'trevas', 'escuro', 'negrume'],
        ['luz', 'brilho', 'luminoso', 'claridade', 'iluminar'],
        ['veneno', 'toxina', 'peçonha', 'tóxico', 'envenenar'],
        ['transformar', 'mudar', 'alterar', 'modificar', 'metamorfose', 'transformação', 'distorcer'],
        ['criar', 'gerar', 'produzir', 'fabricar', 'formar', 'manifestar'],
        ['destruir', 'quebrar', 'despedaçar', 'demolir', 'arruinar', 'aniquilar'],
        ['controlar', 'manipular', 'dominar', 'comandar', 'governar'],
        ['perder', 'sofrer', 'receber'],
        ['imune', 'imunidade', 'invulnerável', 'inatingível'],
        ['mestre', 'narrador', 'game master', 'gm'],
        ['jogador', 'participante', 'personagem', 'player'],
        ['obrigar', 'forçar', 'impor', 'compelir', 'exigir', 'impedir'],
        ['lei', 'regra', 'norma', 'decreto'],
        ['duplo', 'dobro', 'dobrar', 'dobrada'],
        ['passado', 'antigo', 'velho', 'anterior', 'outrora'],
        ['futuro', 'adiante', 'posterior', 'amanhã'],
        ['sumir', 'desaparecer', 'evaporar', 'dissipar', 'desvanecer'],
        ['aparecer', 'surgir', 'emergir', 'brotar', 'nascer'],
        ['inteiro', 'completo', 'total'],
        ['órgão', 'víscera'],
        ['distância', 'longe', 'alcance', 'remoto'],
        ['próximo', 'perto', 'adjacente', 'vizinho', 'contato'],
        ['permanente', 'eterno', 'perpétuo', 'infinito', 'constante', 'permanência', 'fixo'],
        ['aleatório', 'acaso', 'imprevisível', 'casual'],
        ['curso', 'caminho', 'trajeto', 'trajetória', 'percurso', 'rota', 'rumo'],
        ['linha', 'fio', 'traço'],
        ['falha', 'falhar', 'erro', 'errar', 'fracasso', 'fracassar', 'desastre'],
        ['pior', 'desfavorável', 'ruim', 'péssimo', 'horrível', 'inconveniente'],
        ['arremessar', 'lançar', 'atirar', 'disparar', 'projétil'],
        ['teletransportar', 'teletransporte', 'teleporte', 'teleportar'],
        ['automático', 'automaticamente', 'sozinho'],
        ['asfixia', 'afogar', 'afogamento', 'sufocar', 'sufocamento'],
        ['envelhecer', 'envelhecimento', 'idade', 'idoso', 'velhice'],
        ['raciocínio', 'raciocinar', 'pensar', 'pensamento', 'lógica', 'lógico', 'inteligência', 'intelecto'],
        ['voltar', 'retornar', 'regressar', 'reviver', 'retorno'],
        ['preservar', 'conservar', 'manter'],
        ['desfazer', 'cancelar', 'anular', 'rebobinar', 'reverter'],
        ['mover', 'deslocar', 'deslocamento', 'movimento', 'movimentar'],
        ['acertar', 'atingir', 'alvejar'],
        ['raiva', 'fúria', 'ira', 'cólera', 'ódio'],
        ['emoção', 'sentimento', 'afeto', 'paixão'],
    ];


    // ------------------------------------------------------------------
    // API principal
    // ------------------------------------------------------------------

    /**
     * @param array|null $maldicoes catálogo (padrão: config('maldicoes'))
     * @param array|null $lista     lista (padrão: config('maldicoes_lista'))
     * @return array{pontuacao:int, detalhes:array<string,int>, penalidades:array<string,float>}
     */
    public static function comparar(string $textoJogador, string $slug, ?array $maldicoes = null, ?array $lista = null): array
    {
        $maldicoes ??= (array) config('maldicoes', []);
        $lista     ??= (array) config('maldicoes_lista', []);

        $zeros   = array_fill_keys(array_keys(self::PESOS), 0);
        $neutras = ['redundancia' => 1.0, 'prolixidade' => 1.0, 'desvio' => 1.0, 'lista_palavras' => 1.0];

        $item = $maldicoes[$slug] ?? null;
        if (!$item) {
            return ['pontuacao' => 0, 'detalhes' => $zeros, 'penalidades' => $neutras];
        }
        $meta = null;
        foreach ($lista as $m) {
            if (($m['slug'] ?? null) === $slug) {
                $meta = $m;
                break;
            }
        }

        $textoJogador = self::limpar($textoJogador);
        $real         = self::textoReal($item);
        $tokensR      = self::termos($real);
        $tokensJ      = self::termos($textoJogador);
        if (!$tokensJ || !$tokensR) {
            return ['pontuacao' => 0, 'detalhes' => $zeros, 'penalidades' => $neutras];
        }
        $idf = self::idf($maldicoes);

        // Termos de metadados (raridade/órgão/apelido) não contam como ruído nem como vocabulário.
        $metaTermos = self::termosMeta($item, $meta, $maldicoes);
        $setR       = array_flip($tokensR);
        $tokensV    = array_values(array_filter($tokensJ, fn ($t) => isset($setR[$t]) || !isset($metaTermos[$t])));

        $significado = self::pontuarSignificado($tokensV, $tokensR, $idf);
        $termos      = self::pontuarTermosChave($tokensV, $tokensR, $idf);
        $ordem       = self::pontuarOrdem($textoJogador, $real);
        $relevancia  = min(1.0, (0.5 * $significado + 0.5 * $termos) / 50);

        $rar = self::pontuarRaridade($textoJogador, (string) ($item['raridade'] ?? ''), $maldicoes, $real);
        $detalhes = [
            'significado'  => $significado,
            'termos_chave' => $termos,
            'ordem'        => $ordem,
            'estrutura'    => (int) round(self::pontuarEstrutura($textoJogador, $real) * $relevancia),
            'concisao'     => (int) round(self::pontuarConcisao($textoJogador, $real) * $relevancia),
            'raridade'     => $rar['pontos'],
            'orgao'        => self::pontuarOrgao($textoJogador, (string) ($meta['orgao'] ?? '')),
            'apelido'      => self::pontuarApelido($textoJogador, (string) ($meta['apelido'] ?? '')),
        ];

        // Critérios sem dado de referência saem da conta e os pesos restantes são reescalados.
        $ausentes = [];
        if (trim((string) ($item['raridade'] ?? '')) === '') {
            $ausentes[] = 'raridade';
        }
        if (trim((string) ($meta['orgao'] ?? '')) === '') {
            $ausentes[] = 'orgao';
        }
        if (trim((string) ($meta['apelido'] ?? '')) === '') {
            $ausentes[] = 'apelido';
        }
        $somaPesos = 0;
        $total     = 0.0;
        foreach (self::PESOS as $k => $peso) {
            if (in_array($k, $ausentes, true)) {
                continue;
            }
            $somaPesos += $peso;
            $total += $detalhes[$k] * $peso;
        }
        $total = $somaPesos > 0 ? $total / $somaPesos : 0.0;

        $pen = [
            'redundancia'    => self::penalidadeRedundancia($tokensJ, $tokensR),
            'prolixidade'    => self::penalidadeProlixidade($textoJogador, $real),
            'desvio'         => self::penalidadeDesvio($tokensJ, $setR, $metaTermos),
            'lista_palavras' => self::penalidadeLista($textoJogador, $real),
        ];
        foreach ($pen as $p) {
            $total *= $p;
        }
        $total -= $rar['erro'] ? self::PENALIDADE_RARIDADE : 0;

        return [
            'pontuacao'   => max(0, min(100, (int) round($total))),
            'detalhes'    => $detalhes,
            'penalidades' => array_map(fn ($p) => round($p, 3), $pen),
        ];
    }

    /** Descrição + regra/efeitos/penalidade das ameaças, sem HTML; cada trecho termina em pontuação. */
    public static function textoReal(array $item): string
    {
        $partes = [(string) ($item['descricao'] ?? '')];
        foreach (($item['ameacas'] ?? []) as $a) {
            $partes[] = (string) ($a['regra'] ?? '');
            foreach (($a['efeitos'] ?? []) as $e) {
                $rot = trim((string) ($e['rotulo'] ?? ''));
                $txt = trim((string) ($e['texto'] ?? ''));
                $partes[] = $rot !== '' && $txt !== '' ? $rot . ': ' . $txt : $rot . $txt;
            }
            $partes[] = (string) ($a['penalidade'] ?? '');
        }
        $saida = [];
        foreach ($partes as $p) {
            $p = trim(self::limpar($p));
            if ($p === '') {
                continue;
            }
            $saida[] = preg_match('/[.!?…:]$/u', $p) ? $p : $p . '.';
        }

        return implode(' ', $saida);
    }

    /** Remove HTML/entidades, corrige codificação e espaços invisíveis. */
    public static function limpar(string $texto): string
    {
        if (!mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'UTF-8,ISO-8859-1');
        }
        if (preg_match('/<\/?[a-z][^>]*>/i', $texto)) {
            $texto = preg_replace('/<br\s*\/?>|<\/p>|<\/li>/i', "\n", $texto);
            $texto = strip_tags($texto);
        }
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    // ------------------------------------------------------------------
    // Métricas (cada uma devolve 0..100)
    // ------------------------------------------------------------------

    /** 65% cosseno TF-IDF (cos >= 0,55 vale 100) + 35% precisão: peso do vocabulário do jogador que existe no real. */
    public static function pontuarSignificado(array $tokensJ, array $tokensR, array $idf): int
    {
        if (!$tokensJ || !$tokensR) {
            return 0;
        }
        $vj  = self::vetor($tokensJ, $idf);
        $cos = self::cosseno($vj, self::vetor($tokensR, $idf));
        $set = array_flip($tokensR);
        $tot = 0.0;
        $ok  = 0.0;
        foreach ($vj as $t => $w) {
            $tot += $w;
            if (isset($set[$t])) {
                $ok += $w;
            }
        }
        $prec = $tot > 0 ? $ok / $tot : 0.0;

        return (int) round(100 * (0.65 * min(1.0, $cos / 0.55) + 0.35 * $prec));
    }

    /** % dos 15 termos alfabéticos de maior TF-IDF do real presentes no jogador (70% de cobertura já vale 100). */
    public static function pontuarTermosChave(array $tokensJ, array $tokensR, array $idf): int
    {
        $v = array_filter(self::vetor($tokensR, $idf), fn ($x, $t) => !preg_match('/\d/', (string) $t), ARRAY_FILTER_USE_BOTH);
        arsort($v);
        $top = array_slice(array_keys($v), 0, self::TOP_TERMOS);
        if (!$top) {
            return 0;
        }
        $set   = array_flip($tokensJ);
        $achou = count(array_filter($top, fn ($t) => isset($set[(string) $t])));

        return (int) min(100, round(100 * ($achou / count($top)) / 0.7));
    }

    /** Sobreposição de bigramas canônicos (pares consecutivos de termos, por frase) com o texto real. */
    public static function pontuarOrdem(string $textoJogador, string $real): int
    {
        $bj = self::bigramas($textoJogador);
        if (count($bj) < 2) {
            return 0;
        }
        $br = array_flip(self::bigramas($real));
        $ok = count(array_filter($bj, fn ($b) => isset($br[$b])));

        return (int) min(100, round(100 * ($ok / count($bj)) / 0.45));
    }

    /** Frases completas + elementos de regra do real. */
    public static function pontuarEstrutura(string $textoJogador, string $real): int
    {
        $fj = self::frases($textoJogador);
        $fr = self::frases($real);
        if (!$fj) {
            return 0;
        }
        $n        = count($fj);
        $pon      = count(array_filter($fj, fn ($f) => $f['terminada']));
        $cap      = count(array_filter($fj, fn ($f) => $f['maiuscula']));
        $alvo     = max(1, min(3, count($fr)));
        $completa = 0.4 * ($pon / $n) + 0.3 * ($cap / $n) + 0.3 * min(1.0, $n / $alvo);

        $mj      = array_sum(array_column($fj, 'palavras')) / $n;
        $mr      = $fr ? array_sum(array_column($fr, 'palavras')) / count($fr) : $mj;
        $tamanho = ($mj > 0 && $mr > 0) ? max(0.0, 1 - abs(log($mj / $mr)) / log(3)) : 0.0;

        // elementos de regra presentes no real
        $bj  = self::brutos($textoJogador);
        $br  = self::brutos($real);
        $req = 0;
        $tem = 0;
        $conta = function (bool $noReal, bool $noJogador) use (&$req, &$tem) {
            if ($noReal) {
                $req++;
                $tem += $noJogador ? 1 : 0;
            }
        };
        $conta(self::temNumero($br), self::temNumero($bj));
        $conta(self::temDado($br), self::temDado($bj));
        foreach (self::ELEMENTOS_REGRA as $aliases) {
            $conta(self::contemAlgum($br, $aliases), self::contemAlgum($bj, $aliases));
        }
        $regra = $req > 0 ? $tem / $req : 1.0;

        return (int) round(100 * (0.4 * $completa + 0.2 * $tamanho + 0.4 * $regra));
    }

    /** Ideal: 50% (de no máximo 260 palavras) a 130% do real; abaixo proporcional; acima perde até 0 em 3,25x. */
    public static function pontuarConcisao(string $textoJogador, string $real): int
    {
        $a = self::contarPalavras($textoJogador);
        $b = self::contarPalavras($real);
        if ($a === 0 || $b === 0) {
            return 0;
        }
        $baixo = 0.5 * min($b, self::REF_MAX_PALAVRAS);
        $alto  = 1.3 * $b;
        if ($a < $baixo) {
            return (int) round(100 * $a / $baixo);
        }
        if ($a <= $alto) {
            return 100;
        }

        return (int) round(max(0.0, 100 * (1 - ($a / $alto - 1) / 1.5)));
    }

    /**
     * @return array{pontos:int, erro:bool} 100 se cita a raridade certa; citar só outra vale 0 e marca erro.
     * Palavras de raridade que já fazem parte do texto real não contam como citação errada.
     */
    public static function pontuarRaridade(string $textoJogador, string $raridade, array $maldicoes = [], string $real = ''): array
    {
        $certa = self::radicalBruto($raridade);
        if ($certa === '') {
            return ['pontos' => 0, 'erro' => false];
        }
        $todas = [$certa => true];
        foreach ($maldicoes as $m) {
            $r = self::radicalBruto((string) ($m['raridade'] ?? ''));
            if ($r !== '') {
                $todas[$r] = true;
            }
        }

        $pal  = array_flip(self::brutos($textoJogador));
        $noRe = array_flip(self::brutos($real));
        if (isset($pal[$certa])) {
            return ['pontos' => 100, 'erro' => false];
        }
        foreach ($todas as $r => $_) {
            if (isset($pal[$r]) && !isset($noRe[$r])) {
                return ['pontos' => 0, 'erro' => true];
            }
        }

        return ['pontos' => 0, 'erro' => false];
    }

    /** % das partes do órgão ("olhos&ouvidos&mãos") citadas, considerando sinônimos. */
    public static function pontuarOrgao(string $textoJogador, string $orgao): int
    {
        $partes = array_values(array_filter(array_map('trim', explode('&', $orgao)), fn ($p) => $p !== ''));
        if (!$partes) {
            return 0;
        }
        $texto = self::brutos($textoJogador);
        $achou = 0;
        foreach ($partes as $parte) {
            foreach (self::aliasesOrgao($parte) as $alias) {
                if (self::contemSequencia($texto, self::brutos($alias))) {
                    $achou++;
                    break;
                }
            }
        }

        return (int) round(100 * $achou / count($partes));
    }

    /** 100 se o apelido (singular, plural ou flexão de gênero) aparece no texto. */
    public static function pontuarApelido(string $textoJogador, string $apelido): int
    {
        $alvo = self::brutos($apelido);

        return ($alvo && self::contemSequencia(self::brutos($textoJogador), $alvo)) ? 100 : 0;
    }

    // ------------------------------------------------------------------
    // Penalidades (fatores entre 0,6 e 1)
    // ------------------------------------------------------------------

    /** Trigramas repetidos acima do real + diversidade lexical (TTR) abaixo da esperada para o mesmo tamanho. */
    public static function penalidadeRedundancia(array $tokensJ, array $tokensR): float
    {
        $n = count($tokensJ);
        if ($n < 12) {
            return 1.0;
        }
        $fracRep = function (array $t) {
            $tri = [];
            for ($i = 0; $i + 2 < count($t); $i++) {
                $tri[] = $t[$i] . ' ' . $t[$i + 1] . ' ' . $t[$i + 2];
            }
            return $tri ? 1 - count(array_unique($tri)) / count($tri) : 0.0;
        };
        $excesso = max(0.0, $fracRep($tokensJ) - $fracRep($tokensR) - 0.04);
        $pRep    = 1 - min(0.4, $excesso * 1.2);

        $pTtr = 1.0;
        if ($n >= 25) {
            $m    = min($n, count($tokensR));
            $ttrs = [];
            foreach (array_chunk($tokensR, max(1, $m)) as $chunk) {
                if (count($chunk) >= $m * 0.8) {
                    $ttrs[] = count(array_unique($chunk)) / count($chunk);
                }
            }
            $esperado = $ttrs ? array_sum($ttrs) / count($ttrs) : 0.0;
            if ($esperado > 0) {
                $razao = (count(array_unique($tokensJ)) / $n) / $esperado;
                $pTtr  = 1 - min(0.4, max(0.0, 0.75 - $razao) * 1.2);
            }
        }

        return max(0.6, $pRep * $pTtr);
    }

    /** Até 1,6x o real nada; 2x = 0,80; 3x = 0,65; 4x ou mais = 0,60. */
    public static function penalidadeProlixidade(string $textoJogador, string $real): float
    {
        $r = self::contarPalavras($textoJogador) / max(40, self::contarPalavras($real));
        if ($r <= 1.6) {
            return 1.0;
        }
        $pontos = [[1.6, 1.0], [2.0, 0.8], [3.0, 0.65], [4.0, 0.6]];
        for ($i = 1; $i < count($pontos); $i++) {
            if ($r <= $pontos[$i][0]) {
                [$x0, $y0] = $pontos[$i - 1];
                [$x1, $y1] = $pontos[$i];
                return $y0 + ($y1 - $y0) * ($r - $x0) / ($x1 - $x0);
            }
        }

        return 0.6;
    }

    /** Proporção de termos de conteúdo (alfabéticos) sem correspondência no real: acima de 45% penaliza até 0,6. */
    public static function penalidadeDesvio(array $tokensJ, array $setR, array $metaTermos = []): float
    {
        $conteudo = array_filter($tokensJ, fn ($t) => !preg_match('/\d/', $t) && !isset($metaTermos[$t]));
        $total    = count($conteudo);
        if ($total < 5) {
            return 1.0;
        }
        $ruido = count(array_filter($conteudo, fn ($t) => !isset($setR[$t])));
        $razao = $ruido / $total;

        return 1 - min(0.4, max(0.0, $razao - 0.45) / 0.55 * 0.4);
    }

    /** Lista de palavras: poucas palavras de ligação, nenhuma frase ou frases gigantes. */
    public static function penalidadeLista(string $textoJogador, string $real): float
    {
        $palavras = self::palavras($textoJogador);
        $n        = count($palavras);
        if ($n < 8) {
            return 1.0;
        }
        $stop = self::stopwords();
        $fn   = function (array $ps) use ($stop) {
            $c = 0;
            foreach ($ps as $p) {
                if (isset($stop[self::normalizar($p)])) {
                    $c++;
                }
            }
            return count($ps) ? $c / count($ps) : 0.0;
        };
        $ref = min(0.22, 0.5 * $fn(self::palavras($real)));
        $p1  = $ref > 0 ? 0.6 + 0.4 * min(1.0, $fn($palavras) / $ref) : 1.0;

        $terminais = preg_match_all('/[.!?…]/u', $textoJogador);
        $fr  = self::frases($real);
        $mr  = $fr ? array_sum(array_column($fr, 'palavras')) / count($fr) : 20;
        $lim = max(40, 2 * $mr);
        if ($terminais === 0) {
            $p2 = 0.75;
        } else {
            $p2 = 1 - min(0.3, max(0.0, $n / $terminais - $lim) * 0.0075);
        }

        return max(0.6, $p1 * $p2);
    }

    // ------------------------------------------------------------------
    // Texto -> termos canônicos
    // ------------------------------------------------------------------

    public static function normalizar(string $texto): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower($texto))));
    }

    /**
     * Termos canônicos (com repetição): sem stopwords, com sinônimos e stemming.
     * Frases de sinônimos são trocadas antes; números e notação de dados (1d4, 2d6, 15) são mantidos.
     */
    public static function termos(string $texto): array
    {
        $t = ' ' . self::normalizar($texto) . ' ';
        [$mapa, $frases] = self::sinonimos();

        foreach ($frases as $frase => $canon) {
            $t = preg_replace('/(?<![a-z0-9])' . preg_quote($frase, '/') . '(?![a-z0-9])/', ' @' . $canon . ' ', $t);
        }

        $stop  = self::stopwords();
        $saida = [];
        foreach (preg_split('/\s+/', trim($t), -1, PREG_SPLIT_NO_EMPTY) as $w) {
            if ($w[0] === '@') {
                $saida[] = substr($w, 1);
                continue;
            }
            $digito = (bool) preg_match('/\d/', $w);
            if (!$digito && (strlen($w) < 2 || isset($stop[$w]))) {
                continue;
            }
            $s       = self::radical($w);
            $saida[] = $mapa[$s] ?? $s;
        }

        return $saida;
    }

    /** Raízes de todas as palavras (inclusive stopwords), sem sinônimos: usado em órgão/apelido/raridade. */
    public static function brutos(string $texto): array
    {
        $n = self::normalizar($texto);

        return $n === '' ? [] : array_map([self::class, 'radical'], explode(' ', $n));
    }

    /** Stemming leve; a raiz nunca fica com menos de 3 letras. */
    public static function radical(string $w): string
    {
        if (preg_match('/\d/', $w)) {
            return $w;
        }
        $troca = function (string $w, string $suf, string $novo = '', int $minBase = 0) {
            if (str_ends_with($w, $suf)) {
                $base = substr($w, 0, -strlen($suf));
                $r    = $base . $novo;
                if (strlen($r) >= 3 && strlen($base) >= $minBase) {
                    return $r;
                }
            }
            return null;
        };

        // plurais (a base deve ter 3+ letras: "leis" -> "lei", "animais" -> "animal")
        foreach ([['oes', 'ao'], ['aes', 'ao'], ['ais', 'al'], ['eis', 'el'], ['res', 'r'], ['zes', 'z'], ['ses', 's'], ['s', '']] as [$suf, $novo]) {
            if (($r = $troca($w, $suf, $novo, $suf === 's' ? 0 : 3)) !== null) {
                $w = $r;
                break;
            }
        }

        // advérbio em -mente: continua para tirar a vogal final ("rapidamente" -> "rapid")
        if (str_ends_with($w, 'mente') && strlen($w) >= 9) {
            $w = substr($w, 0, -5);
        }

        // derivacionais e verbais (só o primeiro que casar)
        foreach (['cao', 'dade', 'avel', 'ivel', 'ando', 'endo', 'indo', 'aram', 'eram', 'iram', 'avam', 'ava', 'iam', 'ado', 'ido', 'ada', 'ida', 'ou', 'ar', 'er', 'ir', 'a', 'o', 'e'] as $suf) {
            if (($r = $troca($w, $suf, '', 3)) !== null) {
                return $r;
            }
        }

        return $w;
    }

    private static function radicalBruto(string $palavra): string
    {
        $b = self::brutos($palavra);

        return count($b) === 1 ? $b[0] : '';
    }

    // ------------------------------------------------------------------
    // TF-IDF
    // ------------------------------------------------------------------

    /** IDF suavizado sobre os textos reais de todo o catálogo (cache por conteúdo). */
    public static function idf(array $maldicoes): array
    {
        static $cache = [];
        $chave = md5(serialize($maldicoes));
        if (isset($cache[$chave])) {
            return $cache[$chave];
        }

        $df = [];
        $n  = 0;
        foreach ($maldicoes as $item) {
            $n++;
            foreach (array_unique(self::termos(self::textoReal((array) $item))) as $t) {
                $df[$t] = ($df[$t] ?? 0) + 1;
            }
        }
        $idf = [];
        foreach ($df as $t => $d) {
            $idf[$t] = log(($n + 1) / ($d + 1)) + 1;
        }
        $idf['__padrao'] = log($n + 1) + 1; // termo que nenhum texto do catálogo tem

        return $cache[$chave] = $idf;
    }

    /** Vetor TF-IDF (tf sublinear: 1 + ln(contagem)). */
    public static function vetor(array $tokens, array $idf): array
    {
        $v = [];
        foreach (array_count_values($tokens) as $t => $c) {
            $v[$t] = (1 + log($c)) * ($idf[$t] ?? $idf['__padrao'] ?? 1.0);
        }

        return $v;
    }

    public static function cosseno(array $a, array $b): float
    {
        $dot = 0.0;
        foreach ($a as $t => $x) {
            if (isset($b[$t])) {
                $dot += $x * $b[$t];
            }
        }
        $na = sqrt(array_sum(array_map(fn ($x) => $x * $x, $a)));
        $nb = sqrt(array_sum(array_map(fn ($x) => $x * $x, $b)));

        return ($na > 0 && $nb > 0) ? $dot / ($na * $nb) : 0.0;
    }

    // ------------------------------------------------------------------
    // Apoio
    // ------------------------------------------------------------------

    public static function contarPalavras(string $texto): int
    {
        return count(self::palavras($texto));
    }

    /** @return string[] */
    private static function palavras(string $texto): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** Frases: separadas por . ! ? … ou quebra de linha; ignora trechos com menos de 3 palavras. */
    private static function frases(string $texto): array
    {
        $saida = [];
        preg_match_all('/[^.!?…\n]+[.!?…]*/u', $texto, $m);
        foreach ($m[0] as $trecho) {
            $limpo = trim($trecho);
            $pal   = self::palavras($limpo);
            if (count($pal) < 3) {
                continue;
            }
            $saida[] = [
                'palavras'  => count($pal),
                'terminada' => (bool) preg_match('/[.!?…]$/u', $limpo),
                'maiuscula' => (bool) preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $limpo),
            ];
        }

        return $saida;
    }

    /** @return string[] bigramas "a b" de termos canônicos consecutivos dentro de cada frase (únicos) */
    private static function bigramas(string $texto): array
    {
        $saida = [];
        foreach (preg_split('/[.!?…;\n:]+/u', $texto, -1, PREG_SPLIT_NO_EMPTY) as $frase) {
            $t = self::termos($frase);
            for ($i = 0; $i + 1 < count($t); $i++) {
                $saida[$t[$i] . ' ' . $t[$i + 1]] = true;
            }
        }

        return array_keys($saida);
    }

    private static function temNumero(array $brutos): bool
    {
        foreach ($brutos as $b) {
            if (ctype_digit($b)) {
                return true;
            }
        }
        return false;
    }

    private static function temDado(array $brutos): bool
    {
        foreach ($brutos as $b) {
            if (preg_match('/^\d*d\d+$/', $b)) {
                return true;
            }
        }
        return false;
    }

    private static function contemAlgum(array $brutos, array $aliases): bool
    {
        $set = array_flip($brutos);
        foreach ($aliases as $a) {
            if (isset($set[self::radical($a)])) {
                return true;
            }
        }
        return false;
    }

    private static function contemSequencia(array $texto, array $seq): bool
    {
        $n = count($seq);
        if ($n === 0) {
            return false;
        }
        for ($i = 0; $i + $n <= count($texto); $i++) {
            if (array_slice($texto, $i, $n) === $seq) {
                return true;
            }
        }
        return false;
    }

    /** @return string[] */
    private static function aliasesOrgao(string $parte): array
    {
        $p = self::normalizar($parte);
        if (isset(self::ORGAOS[$p])) {
            return self::ORGAOS[$p];
        }
        $alvo = self::brutos($p);
        foreach (self::SINONIMOS as $grupo) {
            foreach ($grupo as $termo) {
                if (self::brutos($termo) === $alvo) {
                    return array_merge([$p], array_map(fn ($g) => self::normalizar($g), $grupo));
                }
            }
        }

        return [$p];
    }

    /** Termos canônicos que vêm dos metadados (raridade, órgão, apelido): [termo => true]. */
    private static function termosMeta(array $item, ?array $meta, array $maldicoes): array
    {
        $textos = [(string) ($item['raridade'] ?? ''), (string) ($meta['apelido'] ?? '')];
        foreach (explode('&', (string) ($meta['orgao'] ?? '')) as $parte) {
            $textos = array_merge($textos, self::aliasesOrgao($parte));
        }
        foreach ($maldicoes as $m) {
            $textos[] = (string) ($m['raridade'] ?? '');
        }
        $saida = [];
        foreach ($textos as $t) {
            foreach (self::termos($t) as $x) {
                $saida[$x] = true;
            }
        }

        return $saida;
    }

    private static function stopwords(): array
    {
        static $s = null;
        return $s ??= array_flip(explode(' ', self::STOPWORDS));
    }

    /** @return array{0: array<string,string>, 1: array<string,string>} [radical => canônico, frase => canônico] */
    private static function sinonimos(): array
    {
        static $cache = null;
        if ($cache) {
            return $cache;
        }
        $mapa   = [];
        $frases = [];
        foreach (self::SINONIMOS as $grupo) {
            $canon = self::radical(str_replace(' ', '', self::normalizar($grupo[0])));
            $mapa[$canon] ??= $canon;
            foreach ($grupo as $termo) {
                $n = self::normalizar($termo);
                if (str_contains($n, ' ')) {
                    $frases[$n] ??= $canon;
                } else {
                    $mapa[self::radical($n)] ??= $canon;
                }
            }
        }
        uksort($frases, fn ($x, $y) => strlen($y) <=> strlen($x));

        return $cache = [$mapa, $frases];
    }
}
