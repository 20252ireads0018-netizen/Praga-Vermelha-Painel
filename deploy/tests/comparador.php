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

// cada caso: slug, parafrase (sem metadados), frase de metadados (%R = raridade), raridade errada, lista de termos, outra maldicao
$casos = [
    'paradoxo' => [
        'para' => 'Quem carrega essa praga perde a noção do compasso: o corpo e a cabeça pulam entre momentos distintos da história. Quando uma cena começa, existe uma probabilidade de 10%, somada a 15% por nível de ameaça, de algo estranho acontecer, como uma lesão velha voltar a sangrar ou o personagem sumir por 2d6 minutos. Durante as lutas, a cada rodada, o golpe pode ser desfeito antes de acertar ou um ataque já evitado retorna contra você. No extremo, o sujeito é jogado em outra linha temporal, e isso equivale à morte.',
        'meta' => 'Maldição de raridade %R, hospedada no coração; quem a possui é chamado de relógio.',
        'errada' => 'Comum',
        'lista' => 'relógio tempo voltar coração ameaça turno passado futuro ferida cena evento chance teste dano sanidade linha temporal',
        'outra' => 'imortalidade',
    ],
    'tolice' => [
        'para' => 'O portador sofre de uma enorme lentidão cerebral: esquece rápido, raciocina mal e pensa devagar. Seu conhecimento fica preso em 10 e o BE em 1, e qualquer jogada de conhecimento, percepção ou reação recebe um castigo de (ameaça + 1) x 15. Além disso, perde eficiência nos combates e nas artes hexomânticas. Com ameaça 2, tudo o que acontece em uma cena é apagado da memória ao seu término, e as penalidades passam a valer para todo teste.',
        'meta' => 'É uma maldição de raridade %R alojada no cérebro, e os amaldiçoados são chamados de gênios.',
        'errada' => 'Exótica',
        'lista' => 'gênio cérebro mente lenta memória raciocínio conhecimento travado ameaça desvantagem teste esquecimento cena reações percepção',
        'outra' => 'paradoxo',
    ],
    'curso-forcado' => [
        'para' => 'A praga faz o corpo inteiro seguir trajetórias traçadas por fios amarelos que somente o dono enxerga, e sempre escolhe o trajeto mais desfavorável. Ao começar a cena, o narrador seleciona uma perícia em que toda rolagem do jogador dá errado automaticamente, e ainda guarda chances para mover o personagem a seu bel-prazer. Projéteis e objetos lançados acertam o pior alvo do alcance. Em ameaças maiores existe risco de 20% de ser teletransportado a um lugar horrível, e qualquer erro vira desastre.',
        'meta' => 'Raridade %R, localizada na caixa torácica; quem a tem é chamado de cursor.',
        'errada' => 'Rara',
        'lista' => 'cursor caixa torácica curso forçado pior habilidade falha teste ameaça mestre chances deslocamento teletransporte falha crítica cena',
        'outra' => 'tolice',
    ],
    'imortalidade' => [
        'para' => 'O corpo do amaldiçoado é conservado por forças obscuras para durar para sempre: ele não envelhece, não sente fome, sede ou fadiga. O preço é mental, pois a loucura cresce com os séculos e, após 100 anos de vida, perde 2 pontos de sanidade por ano. Em níveis mais fortes, fica imune a cegueira, sangramento e asfixia, não cai mais morrendo, e quando morre retorna à vida depois de décadas, meses ou até no dia seguinte, com os membros recuperados. Por fim, nem a destruição completa da carcaça o impede de reviver.',
        'meta' => 'Raridade %R; o órgão hospedeiro é o coração e os amaldiçoados são chamados de imortais.',
        'errada' => 'Comum',
        'lista' => 'imortal coração imortalidade ameaça sanidade idade morrer voltar anos regenerar imunidade sangrando afogando morrendo corpo destruído',
        'outra' => 'curso-forcado',
    ],
];

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
    $meta  = str_replace('%R', mb_strtolower($raridade), $c['meta']);
    $metaE = str_replace('%R', mb_strtolower($c['errada']), $c['meta']);

    $textos = [
        'a real+meta'      => $real . ' ' . $meta,
        'b real'           => $real,
        'c parafrase+meta' => $c['para'] . ' ' . $meta,
        'd parafrase'      => $c['para'],
        'e real x3'        => $real . ' ' . $real . ' ' . $real,
        'f embaralhado'    => embaralhar($real),
        'g lista termos'   => $c['lista'],
        'h culinaria'      => $culinaria,
        'i outra maldicao' => C::textoReal($maldicoes[$c['outra']]),
        'j raridade errada' => $c['para'] . ' ' . $metaE,
        'k parafrase+enrol' => $c['para'] . ' ' . $meta . "\n\n" . $enrolacao,
    ];
    $r = [];
    foreach ($textos as $k => $t) {
        $r[$k] = C::comparar($t, $slug, $maldicoes, $lista);
    }
    $n = fn (string $k) => $r[$k]['pontuacao'];

    echo "\n=== $slug (real: " . C::contarPalavras($real) . " palavras) ===\n";
    printf("%-18s %4s | %3s %3s %3s %3s %3s %3s %3s %3s | %-5s %-5s %-5s %-5s\n", 'caso', 'nota', 'sig', 'ter', 'ord', 'est', 'con', 'rar', 'org', 'ape', 'redun', 'prol', 'desv', 'lista');
    foreach ($r as $k => $x) {
        $d = $x['detalhes'];
        $p = $x['penalidades'];
        printf("%-18s %4d | %3d %3d %3d %3d %3d %3d %3d %3d | %-5.2f %-5.2f %-5.2f %-5.2f\n", $k, $x['pontuacao'], $d['significado'], $d['termos_chave'], $d['ordem'], $d['estrutura'], $d['concisao'], $d['raridade'], $d['orgao'], $d['apelido'], $p['redundancia'], $p['prolixidade'], $p['desvio'], $p['lista_palavras']);
    }

    $verifica($slug, 'a', $n('a real+meta') >= 92, 'a >= 92');
    $verifica($slug, 'b', $n('b real') >= 72, 'b >= 72');
    $verifica($slug, 'c', $n('c parafrase+meta') >= 55 && $n('c parafrase+meta') <= 85, 'c entre 55 e 85');
    $verifica($slug, 'd', $n('d parafrase') < $n('c parafrase+meta'), 'd < c');
    $verifica($slug, 'e', $n('e real x3') <= $n('b real') - 10, 'e <= b - 10');
    $verifica($slug, 'f', $n('f embaralhado') <= $n('b real') - 15, 'f <= b - 15');
    $verifica($slug, 'g', $n('g lista termos') <= 45 && $n('g lista termos') < $n('c parafrase+meta'), 'g <= 45 e < c');
    $verifica($slug, 'h', $n('h culinaria') <= 8, 'h <= 8');
    $verifica($slug, 'i', $n('i outra maldicao') <= 35, 'i <= 35');
    $verifica($slug, 'j', $n('j raridade errada') < $n('c parafrase+meta'), 'j < c');
    $verifica($slug, 'k', $n('k parafrase+enrol') < $n('c parafrase+meta'), 'k < c');
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
