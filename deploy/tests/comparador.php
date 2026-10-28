<?php

/**
 * Bateria de testes do ComparadorCatalogo (nao vai para o servidor).
 * Uso: php deploy/tests/comparador.php   (sai com codigo 1 se alguma expectativa falhar)
 */

$raiz = dirname(__DIR__, 2);
require $raiz . '/vendor/autoload.php';

use App\Support\ComparadorCatalogo as C;

$maldicoes = require $raiz . '/config/maldicoes.php';
$lista     = require $raiz . '/config/maldicoes_lista.php';

$enrolacao = <<<'TXT'
O mundo é um lugar enorme e cheio de histórias interessantes que as pessoas contam umas às outras durante as longas noites de inverno. As cidades crescem, as estradas se alargam e os moradores seguem a vida entre o mercado, a praça e a casa de seus parentes, sempre conversando sobre o clima e as novidades do bairro.

Muitos viajantes atravessam montanhas, rios e vales em busca de aventuras, e cada um deles guarda lembranças diferentes de lugares distantes. Existem festas, feiras, músicos de rua e crianças brincando, e tudo isso faz parte de um grande cenário que deveria ser lembrado com carinho por todos nós.

Em resumo, a vida é longa, o universo é vasto e há muito a se descobrir e comentar. Vale a pena lembrar que cada pessoa vê as coisas do seu jeito, e que conversar sobre isso é uma ótima maneira de passar o tempo e conhecer melhor os amigos e vizinhos.
TXT;

$culinaria = 'Para fazer um bolo de cenoura fofinho, bata no liquidificador três cenouras, quatro ovos e meio copo de óleo. Misture a farinha de trigo, o açúcar e o fermento em uma tigela e despeje a massa por cima. Leve ao forno por quarenta minutos e cubra com calda de chocolate. Sirva morno com café, e guarde o restante na geladeira para o dia seguinte.';

// cada caso: slug => [para (d: paráfrase fiel), solta (e), meta (%R = raridade), errada, lista, outra]
$casos = [
    'anti-sobrenaturalismo' => [
        'para' => 'Chamar de maldição a aversão ao sobrenatural soa estranho, já que ele corrói e adoece, mas dado o contexto social do mundo essa característica traz mazelas importantes. O portador se torna imune apenas às coisas que deseja: um aliado hexomante até poderia curar sua ferida grave, mas não consegue, e ainda assim um raio de uma atração elétrica o fulmina normalmente. Todo efeito sobrenatural que lhe faria bem, seja de fenômenos, técnicas hexomantes, itens ou elixires, como elevar valores, curar ou ajudá-lo de modo indireto a voar até algum lugar, fica cancelado já em ameaça 0. Se o efeito fosse lhe causar prejuízo, ele continua ocorrendo sem alteração.',
        'solta' => 'Essa maldição faz o portador rejeitar o sobrenatural: qualquer efeito sobrenatural que lhe faria bem, como a cura de um aliado hexomante, é cancelado. Os efeitos que o prejudicam continuam normais. É uma praga estranha, mas num mundo assim ela traz muitos problemas.',
        'meta' => 'Maldição de raridade %R, hospedada no coração; quem a possui é chamado de nulo.',
        'errada' => 'Rara',
        'lista' => 'nulo coração sobrenatural benefício cancelado hexomante cura técnica item elixir fenômeno prejuízo mantido ferida aliado imune',
        'outra' => 'tolice',
    ],
    'paradoxo' => [
        'para' => 'Seu corpo, sua mente e sua alma vagueiam entre épocas distintas, e isso se manifesta de formas variadas: numa conversa você escuta alguém repetir o que falou segundos antes, ou dizer o que ainda não disse; nos casos graves seu corpo volta à situação passada, ou você é arremessado a uma linha temporal diferente sem saber como. No começo de toda cena existe 10% de chance, mais 15% por nível de ameaça, de ocorrer um Evento, e outras técnicas também podem provocá-lo. Num evento, uma ferida antiga reabre como se fosse nova e você sofre de novo um dano que já tinha levado na cena. Ou então você some da cena e retorna depois de 2d6 vezes a ameaça em minutos, indo para o futuro ou voltando a um momento do passado que o mestre escolhe. Ao reaparecer, sofre dano de sanidade igual à ameaça mais 2, mas ganha vantagem em testes de conhecimento até o fim da cena. Em combate, os eventos também podem surgir a cada início de turno: o próximo ataque seu é desfeito antes de acertar o alvo, embora gaste ações e aura, ou um ataque que você já esquivou volta contra você, vindo de um inimigo à escolha do mestre. Na cena ainda há a chance de ficar com o físico de um idoso ou de uma criança até o término, de sumir por uma quantidade de turnos igual à ameaça, ou de ficar lento e sofrer desvantagem igual à ameaça em todo teste do turno. Você deixa de sofrer dano de queda e os danos contínuos só agem a cada rodada, mais uma por ameaça. Por fim, pode ocorrer uma alternância temporal: você é levado para outra linha do tempo, passado ou futuro, escolhida pelo mestre, e em termos de jogo isso significa morrer.',
        'solta' => 'O portador oscila entre momentos diferentes do tempo e às vezes sofre eventos estranhos: feridas antigas voltam, ele desaparece da cena por um tempo e ataques são desfeitos. Nos casos mais graves ele se perde em outra linha temporal e morre.',
        'meta' => 'Maldição de raridade %R, hospedada no coração; quem a possui é chamado de relógio.',
        'errada' => 'Comum',
        'lista' => 'relógio tempo voltar coração ameaça turno passado futuro ferida cena evento chance teste dano sanidade linha temporal',
        'outra' => 'imortalidade',
    ],
    'tolice' => [
        'para' => 'O portador sofre de uma enorme lentidão cerebral: esquece rápido, raciocina mal e pensa devagar. Seu conhecimento fica preso em 10 e o BE em 1, e qualquer jogada de conhecimento, percepção ou reação recebe um castigo de (ameaça + 1) x 15. Além disso, perde eficiência nos combates e nas artes hexomânticas. Ao fim de toda cena, tudo o que aconteceu nela é apagado da memória, e as penalidades de ameaça 0 passam a valer para todo teste.',
        'solta' => 'Essa praga deixa o portador com a mente lenta, memória curta e raciocínio fraco. Ele sofre desvantagem em testes de conhecimento e percepção e esquece o que viveu na cena. Quanto mais forte a maldição, pior fica.',
        'meta' => 'É uma maldição de raridade %R alojada no cérebro, e os amaldiçoados são chamados de gênios.',
        'errada' => 'Exótica',
        'lista' => 'gênio cérebro mente lenta memória raciocínio conhecimento travado ameaça desvantagem teste esquecimento cena reações percepção',
        'outra' => 'paradoxo',
    ],
    'curso-forcado' => [
        'para' => 'Tudo o que seu corpo consegue fazer segue um curso, seja o caminhar, o movimento dos braços, pernas, boca e voz, ou a trajetória do que você arremessa ou golpeia com faca ou espada. A maldição desenha esses cursos como linhas amarelas quase transparentes, visíveis apenas para o amaldiçoado, que ligam seus membros aos alvos possíveis. O problema é que você sempre segue o pior curso para o que deseja: quer ir para a esquerda e vai para a direita, quer cortar um legume e usa a faca do pior jeito. Perícia falha: no início de toda cena o mestre escolhe uma habilidade, mais uma por nível de ameaça, e todos os seus testes nela falham automaticamente; ele deve escolher a mais inconveniente. Movimento forçado: o mestre tem um contador de chances que começa em 2 por cena e cresce com a ameaça; ao se deslocar, ele pode gastar uma chance e mover você para onde quiser usando seu deslocamento e sua ação de movimento. Itens: tudo que você dispara, arremessa ou larga tem o curso manipulado, acerta automaticamente, mas o pior alvo possível dentro do alcance. Teletransporte: ao se deslocar, existe 20% de chance, mais 10% por ameaça, de ser teletransportado para o pior ponto possível, a distância de até a ameaça vezes 5. Todo teste que falhar por causa da maldição vira falha crítica automática.',
        'solta' => 'O corpo do portador sempre segue o pior caminho possível, como se uma linha guiasse cada movimento para o erro. O mestre escolhe habilidades que sempre falham e pode forçar o deslocamento do personagem. Também pode haver teletransportes ruins.',
        'meta' => 'Raridade %R, localizada na caixa torácica; quem a tem é chamado de cursor.',
        'errada' => 'Rara',
        'lista' => 'cursor caixa torácica curso forçado pior habilidade falha teste ameaça mestre chances deslocamento teletransporte falha crítica cena',
        'outra' => 'tolice',
    ],
    'imortalidade' => [
        'para' => 'Seu corpo é preservado por forças sombrias para continuar existindo para sempre, mas a mente humana não aguenta viver mais do que o organismo normal: a sanidade apodrece com a perda dos entes queridos, as relações ficam difíceis e a maioria dos portadores se mata antes de evoluir a maldição por medo da eternidade. Com o tempo você deixa de parecer humano. Desgaste zero: não sofre penalidade de idade, mesmo que continue envelhecendo na aparência, e não sente fome, sede ou cansaço. Perda de sanidade: a cada ano de vida acima de 100 você perde 2 pontos de sanidade total. Imunidades: fica imune a condições como cegueira, atordoamento, enjoo e congelamento, e como não precisa de sangue nem de ar também é imune a sangramento e afogamento. Sem agonizar: não entra em estado de morrendo, pode ficar com vida negativa sem penalidade e resiste a ferida grave e recente. Retorno: quando morre, se o corpo não foi totalmente destruído, volta à vida após 1d100 mais 50 anos com ficha idêntica à da morte, regenerando membros e perdendo sequelas permanentes. Em níveis mais fortes volta em apenas 1d10 meses, depois no dia seguinte, e por fim retorna mesmo se o corpo for completamente destruído.',
        'solta' => 'O corpo do portador é preservado para sempre, sem fome nem cansaço, mas a sanidade sofre com o passar dos anos. Ele fica imune a várias condições e, quando morre, acaba voltando à vida depois de um tempo.',
        'meta' => 'Raridade %R; o órgão hospedeiro é o coração e os amaldiçoados são chamados de imortais.',
        'errada' => 'Comum',
        'lista' => 'imortal coração imortalidade ameaça sanidade idade morrer voltar anos regenerar imunidade sangrando afogando morrendo corpo destruído',
        'outra' => 'curso-forcado',
    ],
];

$fixture = file_get_contents(__DIR__ . '/fixtures/arthur-anti-sobrenaturalismo.txt');
$nomes = [];
foreach ($lista as $m) {
    $nomes[$m['slug']] = $m;
}

function embaralhar(string $texto): string
{
    $p = preg_split('/[^\p{L}\p{N}]+/u', $texto, -1, PREG_SPLIT_NO_EMPTY);
    mt_srand(42);
    for ($i = count($p) - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        [$p[$i], $p[$j]] = [$p[$j], $p[$i]];
    }
    return implode(' ', $p);
}

/** Troca todos os números (inclusive níveis e dados) por outros; sem números, acrescenta níveis e valores inventados. */
function inventarNumeros(string $texto): string
{
    if (!preg_match('/\d/', $texto)) {
        return $texto . ' Em ameaça 3 isso falha com 35% de chance e o efeito dura 4d8 minutos, com ameaça 1 cai para 5%.';
    }
    return preg_replace_callback('/\d+/', fn ($m) => (string) (((int) $m[0] * 3 + 7) % 97 + 2), $texto);
}

$falhas = 0;
$verifica = function (string $slug, string $caso, bool $ok, string $regra) use (&$falhas) {
    if (!$ok) {
        $falhas++;
        echo "  FALHOU [$slug/$caso]: $regra\n";
    }
};

foreach ($casos as $slug => $c) {
    $item = $maldicoes[$slug];
    $real = C::textoReal($item);
    $raridade = $item['raridade'];
    $m     = $nomes[$slug];
    $meta  = str_replace('%R', mb_strtolower($raridade), $c['meta']);
    $metaE = str_replace('%R', mb_strtolower($c['errada']), $c['meta']);
    $cabecalho = $m['nome'] . ', pessoal, ' . $m['orgao'] . ', ' . $m['apelido'] . '. Raridade ' . $raridade . '.';
    $generico  = 'Esta maldição ' . $m['nome'] . ' afeta o amaldiçoado. Em ameaça 0 ele sofre efeitos em testes, a cada turno o mestre decide o que acontece com o alvo e o personagem recebe dano ou vantagem conforme a cena. Em ameaça 2 o efeito da maldição aumenta e o jogador sofre mais desvantagens.';

    $textos = [
        'a real+meta'       => $real . ' ' . $meta,
        'b real'            => $real,
    ];
    if ($slug === 'anti-sobrenaturalismo') {
        $textos['c fixture jogador'] = $fixture;
    }
    $textos += [
        'd parafrase fiel'  => $c['para'],
        'e parafrase solta' => $c['solta'],
        'f generico'        => $generico,
        'g so cabecalho'    => $cabecalho,
        'h lista termos'    => $c['lista'],
        'i outra maldicao'  => C::textoReal($maldicoes[$c['outra']]),
        'j culinaria'       => $culinaria,
        'k real x3'         => $real . ' ' . $real . ' ' . $real,
        'l embaralhado'     => embaralhar($real),
        'm numeros inventados' => inventarNumeros($c['para']),
        'n raridade errada' => $c['para'] . ' ' . $metaE,
        'o d+meta'          => $c['para'] . ' ' . $meta,
        'p d+meta+enrol'    => $c['para'] . ' ' . $meta . "\n\n" . $enrolacao,
    ];
    $r = [];
    foreach ($textos as $k => $t) {
        $r[$k] = C::comparar($t, $slug, $maldicoes, $lista);
    }
    $n = fn (string $k) => $r[$k]['pontuacao'];

    echo "\n=== $slug (real: " . C::contarPalavras($real) . " palavras) ===\n";
    printf("%-21s %4s | %3s %3s %3s %3s %3s %3s %3s %3s | %-5s %-5s %-5s %-5s %-5s\n", 'caso', 'nota', 'sig', 'ter', 'ord', 'est', 'con', 'rar', 'org', 'ape', 'redun', 'prol', 'desv', 'lista', 'inv');
    foreach ($r as $k => $x) {
        $d = $x['detalhes'];
        $p = $x['penalidades'];
        printf("%-21s %4d | %3d %3d %3d %3d %3d %3d %3d %3d | %-5.2f %-5.2f %-5.2f %-5.2f %-5.2f\n", $k, $x['pontuacao'], $d['significado'], $d['termos_chave'], $d['ordem'], $d['estrutura'], $d['concisao'], $d['raridade'], $d['orgao'], $d['apelido'], $p['redundancia'], $p['prolixidade'], $p['desvio'], $p['lista_palavras'], $p['invencao']);
    }

    $verifica($slug, 'a', $n('a real+meta') >= 90, 'a >= 90');
    $verifica($slug, 'b', $n('b real') >= 85, 'b >= 85');
    if (isset($r['c fixture jogador'])) {
        $verifica($slug, 'c', $n('c fixture jogador') >= 50 && $n('c fixture jogador') <= 68, 'c entre 50 e 68');
    }
    $verifica($slug, 'd', $n('d parafrase fiel') >= 45 && $n('d parafrase fiel') <= 70, 'd entre 45 e 70');
    $verifica($slug, 'e', $n('e parafrase solta') >= 20 && $n('e parafrase solta') <= 40, 'e entre 20 e 40');
    $verifica($slug, 'f', $n('f generico') <= 15, 'f <= 15');
    $verifica($slug, 'g', $n('g so cabecalho') <= 10, 'g <= 10');
    $verifica($slug, 'h', $n('h lista termos') <= 25, 'h <= 25');
    $verifica($slug, 'i', $n('i outra maldicao') <= 20, 'i <= 20');
    $verifica($slug, 'j', $n('j culinaria') <= 5, 'j <= 5');
    $verifica($slug, 'k', $n('k real x3') <= $n('b real') - 20, 'k <= b - 20');
    $verifica($slug, 'l', $n('l embaralhado') <= $n('b real') - 25, 'l <= b - 25');
    $verifica($slug, 'm', $n('m numeros inventados') <= $n('d parafrase fiel') - 10, 'm <= d - 10');
    $verifica($slug, 'n', $n('n raridade errada') < $n('o d+meta'), 'raridade errada < certa');
    $verifica($slug, 'p', $n('p d+meta+enrol') < $n('o d+meta'), 'enrolacao reduz a nota');
}

// casos de borda: nada pode lançar erro nem dividir por zero
$bordas = ['', '   ', 'a o de em', '1d4 +2', '<p>Seu <b>corpo</b> &amp; mente oscilam no tempo.</p>', "\xff\xfe texto inválido", str_repeat('tempo ', 600)];
foreach ($bordas as $b) {
    try {
        $x = C::comparar($b, 'paradoxo', $maldicoes, $lista);
        $ok = $x['pontuacao'] >= 0 && $x['pontuacao'] <= 100;
    } catch (Throwable $e) {
        $ok = false;
        echo '  excecao: ' . $e->getMessage() . "\n";
    }
    $verifica('bordas', json_encode(mb_substr($b, 0, 15)), $ok, 'nota 0..100 sem erro');
}
$verifica('bordas', 'slug', C::comparar('texto qualquer', 'nao-existe', $maldicoes, $lista)['pontuacao'] === 0, 'slug inexistente = 0');
$verifica('bordas', 'dados', in_array('1d4', C::termos('Causa 1d4 de dano +2'), true) && in_array('2', C::termos('+2'), true), 'notacao de dados e numeros preservados');
$verifica('bordas', 'plural', C::termos('leis') === C::termos('lei') && C::termos('animais') === C::termos('animal'), 'plurais irregulares');
$verifica('bordas', 'apelido', C::pontuarApelido('Os relógios e a nula', 'relógio') === 100 && C::pontuarApelido('uma nula', 'nulo') === 100, 'apelido plural/feminino');
$verifica('bordas', 'orgao', C::pontuarOrgao('olhos e a audição', 'olhos&ouvidos&mãos') === 67 && C::pontuarOrgao('as costelas', 'caixa torácica') === 100, 'orgao composto/sinonimos');

echo "\n" . ($falhas ? "$falhas expectativa(s) falharam.\n" : "Todas as expectativas passaram.\n");
exit($falhas ? 1 : 0);
