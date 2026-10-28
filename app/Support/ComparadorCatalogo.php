<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Compara a descrição que o jogador escreveu de uma maldição com o texto real do catálogo,
 * sem serviço externo: normalização, stopwords, stemming leve, sinônimos do domínio, TF-IDF,
 * n-gramas, estrutura de regras e metadados (raridade, órgão hospedado, apelido).
 *
 * CALIBRAGEM RÍGIDA (v3). Princípios: o texto precisa dizer a mesma coisa (conteúdo), com as mesmas
 * regras (estrutura/números); copiar cabeçalho, vocabulário genérico do jogo ou números soltos não rende nota.
 *
 *  - Stopwords de domínio (DOMINIO): termos genéricos do sistema que aparecem em quase toda maldição
 *    (maldição, ameaça/nível, efeito, teste, turno, cena, mestre, dano, alvo, ação, valor...) não entram em
 *    significado, termos-chave, ordem nem desvio. Os demais termos pesam pelo IDF no corpus
 *    (peso = IDF - 1, entre 0,05 e 1,6: termo presente em quase todas as maldições pesa quase nada;
 *    termo fora de todo o catálogo pesa 0,5).
 *  - Sinônimo vale 0,6 de um acerto exato no significado e 0,5 nos termos-chave.
 *
 * PESOS (soma 100). Texto real puro rende no máximo 92 (metadados não estão no texto):
 *   significado  42  média de cosseno (com crédito de sinônimo) e F1 entre cobertura do texto real e precisão
 *                    do texto do jogador (pesos por IDF), elevada a 1,2: 100*sim^1,2, sem passar de 100.
 *                    A precisão entra suavizada (raiz) porque parafrasear com outras palavras é legítimo; a cobertura
 *                    esperada vale pelo menos 70% do peso do real (nem um texto curto e certeiro cobre tudo) e o
 *                    resultado é reduzido se houver menos de 12 termos de conteúdo distintos (título/cabeçalho soltos)
 *   termos_chave 20  os 25 termos de maior TF-IDF do real, ponderados pelo IDF (exato 1, sinônimo 0,5)
 *   ordem        10  bigramas (peso 35%) e trigramas (65%) de raízes de conteúdo, por frase, do jogador
 *                    que existem no real (70% de acerto já vale 100)
 *   estrutura    14  níveis de ameaça citados que existem na maldição (30%; sem citar nível vale metade),
 *                    fidelidade dos números/percentuais/dados do real (30%), nº de frases próximo ao do real (15%),
 *                    frases completas e tamanho médio da frase (25%). Só vale na proporção da relevância
 *                    (média de significado e termos-chave; 45 já vale 100%).
 *   concisao      6  ideal entre 50% (de no máx. 260 palavras) e 130% do real; abaixo é proporcional,
 *                    acima perde linearmente até 0 em 3,25x. Também só vale na proporção da relevância.
 *   raridade      3  cita a raridade certa; outra raridade citada = 0 e -3 pontos no total
 *   orgao         3  órgão hospedado (parcial para "a&b&c"), com sinônimos (coração/peito/cardíaco...)
 *   apelido       2  apelido popular (singular/plural/feminino)
 *   Metadados somam 8% (máx. 10%) e são condicionados ao conteúdo: valem integralmente com significado >= 40;
 *   abaixo disso escalam linearmente até 0 (só citar o cabeçalho não rende nada).
 *
 * TETO POR CONTEÚDO: a nota final nunca passa de significado + 15.
 *
 * PENALIDADES (multiplicativas, cada uma em [0,6; 1], aplicadas depois da soma):
 *   redundancia    trigramas repetidos acima do texto real e baixa diversidade lexical (TTR por janela)
 *   prolixidade    acima de 1,6x o real: 2x = 0,80; 3x = 0,65; 4x ou mais = 0,60
 *   desvio         >35% do peso (IDF) dos termos de conteúdo não existe no real (nem nos sinônimos), exceto metadados
 *   lista_palavras poucas palavras de ligação, sem frases ou frases gigantes (keyword stuffing); piso 0,35
 *   invencao       níveis de ameaça ou números/dados que não existem no real: -12% cada, até 0,6
 */
class ComparadorCatalogo
{
    public const PESOS = [
        'significado' => 42, 'termos_chave' => 20, 'ordem' => 10, 'estrutura' => 14,
        'concisao' => 6, 'raridade' => 3, 'orgao' => 3, 'apelido' => 2,
    ];
    public const TOP_TERMOS = 25;
    public const REF_MAX_PALAVRAS = 260;
    public const PENALIDADE_RARIDADE = 3;
    public const CREDITO_SINONIMO = 0.6;       // no significado
    public const CREDITO_SINONIMO_CHAVE = 0.5; // nos termos-chave
    public const CURVA_SIGNIFICADO = 1.2;
    public const TETO_SOBRE_SIGNIFICADO = 15;
    public const TETO_PESO = 1.6;              // peso máximo de um termo no significado
    public const FOLGA_COBERTURA = 1.5;        // cobertura exigida: até 1,5x o peso do texto do jogador
    public const PISO_COBERTURA = 0.7;         // cobertura mínima esperada do peso do real, mesmo num texto curto
    public const PESO_DESCONHECIDO = 0.5;      // termo fora de todo o catálogo
    public const EXP_PRECISAO = 0.5;           // precisão suavizada (raiz): parafrasear com outras palavras é legítimo
    public const TERMOS_MINIMOS = 12;          // termos de conteúdo distintos para o significado valer por inteiro
    public const SIGNIFICADO_META = 40;        // metadados valem 100% a partir daqui

    /** Termos genéricos do sistema (formas de superfície; viram canônicos via sinônimos/stemming). */
    private const DOMINIO = 'maldição maldições ameaça nível efeito efeitos teste testes turno rodada cena mestre dano alvo ação ações valor valores amaldiçoado portador personagem jogador habilidade técnica sofrer receber perder ganhar vantagem desvantagem bônus penalidade aura início fim seguinte novo novamente caso forma tipo vez ponto pontos sempre poder possível aplicar fazer ficar tornar existir ocorrer acontecer causar usar';

    /** Aliases de órgãos (já normalizados, sem acento). Órgãos fora daqui usam o grupo de sinônimos. */
    private const ORGAOS = [
        'coracao'        => ['coracao', 'peito', 'cardiaco', 'cardio'],
        'cerebro'        => ['cerebro', 'mente', 'cabeca', 'cerebral', 'encefalo', 'cranio'],
        'ouvidos'        => ['ouvidos', 'ouvido', 'orelha', 'orelhas', 'audicao', 'auditivo', 'timpanos'],
        'olhos'          => ['olhos', 'olho', 'visao', 'ocular', 'vista'],
        'maos'           => ['maos', 'mao', 'dedos', 'palma'],
        'caixa toracica' => ['caixa toracica', 'torax', 'toracico', 'costelas', 'costela', 'peito'],
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
        $neutras = ['redundancia' => 1.0, 'prolixidade' => 1.0, 'desvio' => 1.0, 'lista_palavras' => 1.0, 'invencao' => 1.0];

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
        $paresR     = self::conteudo($real);
        $paresJ     = array_values(array_filter(self::conteudo($textoJogador), fn ($p) => isset($setR[$p['c']]) || !isset($metaTermos[$p['c']])));

        $significado = self::pontuarSignificado($paresJ, $paresR, $idf);
        $termos      = self::pontuarTermosChave($paresJ, $paresR, $idf);
        $ordem       = self::pontuarOrdem($textoJogador, $real);
        $relevancia  = min(1.0, (0.5 * $significado + 0.5 * $termos) / 45);
        $fatorMeta   = min(1.0, $significado / self::SIGNIFICADO_META);

        $est = self::analisarEstrutura($textoJogador, $real, array_map(fn ($a) => (string) (int) ($a['nivel'] ?? 0), (array) ($item['ameacas'] ?? [])));
        $rar = self::pontuarRaridade($textoJogador, (string) ($item['raridade'] ?? ''), $maldicoes, $real);
        $detalhes = [
            'significado'  => $significado,
            'termos_chave' => $termos,
            'ordem'        => $ordem,
            'estrutura'    => (int) round($est['pontos'] * $relevancia),
            'concisao'     => (int) round(self::pontuarConcisao($textoJogador, $real) * $relevancia),
            'raridade'     => (int) round($rar['pontos'] * $fatorMeta),
            'orgao'        => (int) round(self::pontuarOrgao($textoJogador, (string) ($meta['orgao'] ?? '')) * $fatorMeta),
            'apelido'      => (int) round(self::pontuarApelido($textoJogador, (string) ($meta['apelido'] ?? '')) * $fatorMeta),
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
            'desvio'         => self::penalidadeDesvio($paresJ, $paresR, $idf),
            'lista_palavras' => self::penalidadeLista($textoJogador, $real),
            'invencao'       => max(0.6, 1 - 0.12 * $est['inventados']),
        ];
        foreach ($pen as $p) {
            $total *= $p;
        }
        // o texto não pode ganhar muito mais do que sua semelhança de sentido
        $total = min($total, $significado + self::TETO_SOBRE_SIGNIFICADO);
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

    /** Peso de um termo canônico: IDF - 1 (mín. 0,05). Termo que o catálogo inteiro tem pesa quase nada. */
    private static function peso(string $c, array $idf, float $teto = INF): float
    {
        // termo que nenhuma maldição tem (vocabulário comum fora do jogo ou inventado) pesa 1,0
        $v = isset($idf[$c]) ? $idf[$c] - 1.0 : self::PESO_DESCONHECIDO;

        return max(0.05, min($teto, $v));
    }

    /** Agrupa pares por termo canônico: [c => ['n' => contagem, 's' => [raízes de superfície => true]]]. */
    private static function agrupar(array $pares): array
    {
        $g = [];
        foreach ($pares as $p) {
            $g[$p['c']]['n'] = ($g[$p['c']]['n'] ?? 0) + 1;
            $g[$p['c']]['s'][$p['s']] = true;
        }

        return $g;
    }

    /** Crédito de um termo do jogador contra o real: 1 exato, $sin sinônimo (mesmo canônico), 0 ausente. */
    private static function credito(string $c, array $gj, array $gr, float $sin): float
    {
        if (!isset($gj[$c], $gr[$c])) {
            return 0.0;
        }

        return array_intersect_key($gj[$c]['s'], $gr[$c]['s']) ? 1.0 : $sin;
    }

    /**
     * Semelhança de sentido: média entre o cosseno TF-IDF (com crédito de sinônimo 0,6) e o F1 entre
     * cobertura do real e precisão do jogador (pesos por IDF; termo inventado pesa o máximo).
     * Curva dura: 100 * sim^1,4.
     *
     * @param array $paresJ pares [s, c] de conteúdo do jogador
     * @param array $paresR pares [s, c] de conteúdo do real
     */
    public static function pontuarSignificado(array $paresJ, array $paresR, array $idf): int
    {
        if (!$paresJ || !$paresR) {
            return 0;
        }
        $gj = self::agrupar($paresJ);
        $gr = self::agrupar($paresR);
        $sin = self::CREDITO_SINONIMO;

        $wj = [];
        $nj = 0.0;
        $totJ = 0.0;
        $okJ = 0.0;
        foreach ($gj as $c => $g) {
            $wj[$c] = (1 + log($g['n'])) * self::peso((string) $c, $idf, self::TETO_PESO);
            $nj += $wj[$c] ** 2;
            $totJ += $wj[$c];
            $okJ += $wj[$c] * self::credito((string) $c, $gj, $gr, $sin);
        }
        $nr = 0.0;
        $totR = 0.0;
        $okR = 0.0;
        $dot = 0.0;
        foreach ($gr as $c => $g) {
            $wr = (1 + log($g['n'])) * self::peso((string) $c, $idf, self::TETO_PESO);
            $nr += $wr ** 2;
            $totR += $wr;
            $cr = self::credito((string) $c, $gj, $gr, $sin);
            $okR += $wr * $cr;
            $dot += $wr * ($wj[$c] ?? 0.0) * $cr;
        }
        if ($totJ <= 0 || $totR <= 0 || $nj <= 0 || $nr <= 0) {
            return 0;
        }
        // a cobertura esperada é limitada pelo tamanho do texto do jogador (uma paráfrase curta não precisa
        // cobrir um texto real de 500 palavras), mas o que ele cita precisa existir no real
        $k    = self::FOLGA_COBERTURA;
        $piso = self::PISO_COBERTURA;
        $cos  = min(1.0, $dot / (sqrt($nj) * sqrt(max($piso * $piso * $nr, min($nr, $k * $k * $nj)))));
        $prec = ($okJ / $totJ) ** self::EXP_PRECISAO;
        $cob  = min(1.0, $okR / max($piso * $totR, min($totR, $k * $totJ)));
        $f1   = ($prec + $cob) > 0 ? 2 * $prec * $cob / ($prec + $cob) : 0.0;
        // texto com pouquíssimos termos de conteúdo (cabeçalho, título solto) não é uma descrição
        $densidade = min(1.0, count($gj) / self::TERMOS_MINIMOS);
        $sim  = min(1.0, 0.5 * $cos + 0.5 * $f1) * $densidade;

        return (int) round(100 * $sim ** self::CURVA_SIGNIFICADO);
    }

    /** Top 25 termos alfabéticos do real por TF-IDF, ponderados pelo IDF: exato 1, sinônimo 0,5. */
    public static function pontuarTermosChave(array $paresJ, array $paresR, array $idf): int
    {
        $gr = self::agrupar(array_filter($paresR, fn ($p) => !preg_match('/\d/', $p['c'])));
        $gj = self::agrupar($paresJ);
        $w  = [];
        foreach ($gr as $c => $g) {
            $w[$c] = (1 + log($g['n'])) * self::peso((string) $c, $idf);
        }
        arsort($w);
        $top = array_slice($w, 0, self::TOP_TERMOS, true);
        $tot = array_sum($top);
        if ($tot <= 0) {
            return 0;
        }
        $ok = 0.0;
        foreach ($top as $c => $peso) {
            $ok += $peso * self::credito((string) $c, $gj, $gr, self::CREDITO_SINONIMO_CHAVE);
        }

        return (int) min(100, round(100 * $ok / $tot));
    }

    /** Bigramas (35%) e trigramas (65%) de raízes de conteúdo, por frase, do jogador que existem no real (70% já vale 100). */
    public static function pontuarOrdem(string $textoJogador, string $real): int
    {
        $bj = self::ngramas($textoJogador, 2);
        if (count($bj) < 2) {
            return 0;
        }
        $tj = self::ngramas($textoJogador, 3);
        $br = array_flip(self::ngramas($real, 2));
        $tr = array_flip(self::ngramas($real, 3));
        $fb = count(array_filter($bj, fn ($b) => isset($br[$b]))) / count($bj);
        $ft = $tj ? count(array_filter($tj, fn ($b) => isset($tr[$b]))) / count($tj) : 0.0;

        return (int) min(100, round(100 * (0.35 * $fb + 0.65 * $ft) / 0.7));
    }

    /** Estrutura de regras (0..100): ver analisarEstrutura. */
    public static function pontuarEstrutura(string $textoJogador, string $real, array $niveisItem = []): int
    {
        return self::analisarEstrutura($textoJogador, $real, $niveisItem)['pontos'];
    }

    /**
     * Estrutura rígida: níveis de ameaça citados que existem na maldição (30%; sem citar nível = 50%), fidelidade dos números/percentuais/
     * dados (30%), nº de frases próximo ao do real (15%) e frases completas com tamanho médio parecido (25%).
     * Níveis e números que NÃO existem no real contam como inventados (devolvidos para a penalidade).
     *
     * @param string[] $niveisItem níveis de ameaça cadastrados na maldição (os rótulos "Ameaça N" não entram no texto real)
     * @return array{pontos:int, inventados:int}
     */
    public static function analisarEstrutura(string $textoJogador, string $real, array $niveisItem = []): array
    {
        $fj = self::frases($textoJogador);
        $fr = self::frases($real);
        if (!$fj) {
            return ['pontos' => 0, 'inventados' => 0];
        }
        $n   = count($fj);
        $pon = count(array_filter($fj, fn ($f) => $f['terminada']));
        $cap = count(array_filter($fj, fn ($f) => $f['maiuscula']));

        $mj      = array_sum(array_column($fj, 'palavras')) / $n;
        $mr      = $fr ? array_sum(array_column($fr, 'palavras')) / count($fr) : $mj;
        $tamanho = ($mj > 0 && $mr > 0) ? max(0.0, 1 - abs(log($mj / $mr)) / log(3)) : 0.0;
        $completa = 0.4 * ($pon / $n) + 0.3 * ($cap / $n) + 0.3 * $tamanho;

        $secoes = $fr ? max(0.0, 1 - abs(log($n / count($fr))) / log(4)) : 1.0;

        [$lj, $nj] = self::regras($textoJogador);
        [$lr, $nr] = self::regras($real);
        foreach ($niveisItem as $nv) {
            $lr[(string) $nv] = true;
        }
        $fid = function (array $a, array $b) {
            // fidelidade: cobertura do que o real tem; sem nada no real, só vale se o jogador também não inventar
            if ($b) {
                return count(array_intersect_key($a, $b)) / count($b);
            }
            return $a ? 0.0 : 1.0;
        };
        $invL = count(array_diff_key($lj, $lr));
        $invN = count(array_diff_key($nj, $nr));
        $citados = count($lj) + count($nj);
        $fracInv = $citados > 0 ? ($invL + $invN) / $citados : 0.0;

        // níveis: quem cita níveis é medido pela cobertura e pela precisão; quem não cita fica no meio-termo
        if (!$lr) {
            $niv = $lj ? 0.0 : 1.0;
        } elseif (!$lj) {
            $niv = 0.5;
        } else {
            $ok  = count(array_intersect_key($lj, $lr));
            $niv = 0.5 * $ok / count($lr) + 0.5 * $ok / count($lj);
        }

        $pontos = 0.30 * $niv + 0.30 * $fid($nj, $nr) + 0.15 * $secoes + 0.25 * $completa;
        $pontos *= 1 - 0.5 * $fracInv;

        return ['pontos' => (int) round(100 * $pontos), 'inventados' => $invL + $invN];
    }

    /**
     * Elementos de regra citados: [níveis de ameaça (["0" => true]), números (["10" => true, "2d6" => true])].
     * Os níveis ("ameaça 2", "nível 1") saem da lista de números; "%", "+" e espaços são ignorados.
     */
    private static function regras(string $texto): array
    {
        $t = mb_strtolower(Str::ascii($texto));
        $niveis = [];
        $t = preg_replace_callback('/\b(?:ameaca|nivel|grau)\s*(\d+)\b/', function ($m) use (&$niveis) {
            $niveis[$m[1]] = true;
            return ' ';
        }, $t) ?? $t;
        $nums = [];
        preg_match_all('/\b\d*d\d+\b|\d+(?:[.,]\d+)?/', $t, $mm);
        foreach ($mm[0] as $x) {
            $nums[str_replace(',', '.', $x)] = true;
        }

        return [$niveis, $nums];
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

    /** Peso (IDF) dos termos de conteúdo sem correspondência no real: acima de 35% penaliza até 0,6. */
    public static function penalidadeDesvio(array $paresJ, array $paresR, array $idf): float
    {
        $gj = self::agrupar(array_filter($paresJ, fn ($p) => !preg_match('/\d/', $p['c'])));
        $gr = self::agrupar($paresR);
        if (count($gj) < 5) {
            return 1.0;
        }
        $tot = 0.0;
        $ruido = 0.0;
        foreach ($gj as $c => $g) {
            $w = self::peso((string) $c, $idf);
            $tot += $w;
            if (!isset($gr[$c])) {
                $ruido += $w;
            }
        }
        $razao = $tot > 0 ? $ruido / $tot : 0.0;

        return 1 - min(0.4, max(0.0, $razao - 0.35) / 0.65 * 0.4);
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

        return max(0.35, $p1 * $p2);
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
        return array_column(self::pares($texto), 'c');
    }

    /** Como termos(), mas devolve [['s' => raiz da palavra no texto, 'c' => termo canônico]]: distingue exato de sinônimo. */
    public static function pares(string $texto): array
    {
        $t = ' ' . self::normalizar($texto) . ' ';
        [$mapa, $frases] = self::sinonimos();

        foreach ($frases as $frase => $canon) {
            $t = preg_replace('/(?<![a-z0-9])' . preg_quote($frase, '/') . '(?![a-z0-9])/', ' @' . $canon . '@' . str_replace(' ', '_', $frase) . ' ', $t);
        }

        $stop  = self::stopwords();
        $saida = [];
        foreach (preg_split('/\s+/', trim($t), -1, PREG_SPLIT_NO_EMPTY) as $w) {
            if ($w[0] === '@') {
                [$c, $s] = explode('@', substr($w, 1), 2) + [1 => ''];
                $saida[] = ['s' => $s, 'c' => $c];
                continue;
            }
            $digito = (bool) preg_match('/\d/', $w);
            if (!$digito && (strlen($w) < 2 || isset($stop[$w]))) {
                continue;
            }
            $s       = self::radical($w);
            $saida[] = ['s' => $s, 'c' => $mapa[$s] ?? $s];
        }

        return $saida;
    }

    /** Pares de conteúdo: sem números e sem os termos genéricos do sistema (DOMINIO). */
    public static function conteudo(string $texto): array
    {
        $dom = self::dominio();

        return array_values(array_filter(self::pares($texto), fn ($p) => !preg_match('/\d/', $p['c']) && !isset($dom[$p['c']])));
    }

    /** Termos canônicos das stopwords de domínio: [c => true]. */
    private static function dominio(): array
    {
        static $d = null;
        if ($d === null) {
            $d = [];
            foreach (self::termos(self::DOMINIO) as $c) {
                $d[$c] = true;
            }
        }

        return $d;
    }

    /** @return string[] n-gramas (n = 2 ou 3) de termos de conteúdo consecutivos dentro de cada frase (únicos) */
    private static function ngramas(string $texto, int $n): array
    {
        $saida = [];
        foreach (preg_split('/[.!?…;\n:]+/u', $texto, -1, PREG_SPLIT_NO_EMPTY) as $frase) {
            $t = array_column(self::conteudo($frase), 'c');
            for ($i = 0; $i + $n <= count($t); $i++) {
                $saida[implode(' ', array_slice($t, $i, $n))] = true;
            }
        }

        return array_keys($saida);
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
