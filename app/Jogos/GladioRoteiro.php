<?php

namespace App\Jogos;

/**
 * Roteiros das cartas de Gladio que o leitor de textos (GladioTexto) não entende sozinho: para cada texto (como o mestre
 * escreveu, comparado sem acento nem maiúscula), o que o motor faz. Se o mestre mudar o texto, a carta volta para o leitor.
 *
 * Roteiro: { hab: [{ g, passos, f? }], est: [efeito contínuo…], ativ: [habilidade…], regen?: N (volta ao morrer N vezes),
 *            sac?: N (sacrifica N Lendas para invocar), resp?: 'negar' | 'imbuir' (só em resposta), mods?: {…} (equipamento) }.
 * Gatilhos além dos do leitor: entra / entra_minha (outra Lenda entrou), cemiterio, scry, ganha / ganha_meu, perde, shot,
 *   dano_feitico, gasto2, atacado, descartar_meu, morrer_combate. f: filtro do evento { sub, min, fixo, por }.
 * Efeito contínuo (est): { q: fonte | meus | meus_outros | inimigos | todos, sub?, atk?, vida?, pal?, fl?, se? }
 *   ou de jogador { j: custo | ve_topo | duplo_extra | formas_cem, … } ou da mesa { g: hexo_todos }.
 * Habilidade (ativ): { rot, custo: { energia?, vida?, virar?, descartar?, sacrificar? }, vez: meu | qualquer, uma?, zona?, passos }.
 * Quantias: número, 'poder' ou '#chave' (Gladio::quantia). Condições: Gladio::condicao.
 */
class GladioRoteiro
{
    /** Lendas que aparecem na foto do Bando Demoníaco (dobram de poder junto com ele), pelo nome sem acento. */
    public const FOTO_BANDO = ['aporium', 'dante', 'balinha'];

    private static ?array $cache = null;

    /** Roteiro do texto (ou null: a carta fica com o leitor). */
    public static function de(?string $texto): ?array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (self::lista() as $t => $r) {
                self::$cache[self::chave($t)] = $r;
            }
        }

        return self::$cache[self::chave((string) $texto)] ?? null;
    }

    private static function chave(string $t): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[.,;:!?]+/', ' ', GladioTexto::normalizar($t))));
    }

    private static function ficha(string $nome, int|string $atk, int|string $vida, array $pal = [], array $extra = []): array
    {
        return $extra + ['a' => 'ficha', 'n' => 1, 'alvo' => 'eu', 'nome' => $nome, 'atk' => $atk, 'vida' => $vida, 'pal' => $pal];
    }

    private static function lista(): array
    {
        $lenda = fn (string $f = 'qualquer', array $x = []) => ['alvo' => 'lenda', 'filtro' => $f] + $x;
        $buff = fn (int $a, int $v, array $alvo) => ['a' => 'buff', 'atk' => $a, 'vida' => $v, 'temp' => false] + $alvo;
        $fonte = ['alvo' => 'fonte', 'filtro' => null];
        $sim = fn (int $resto) => ['a' => 'confirmar', 'resto' => $resto];

        return [
            /* ---------------- feitiços, encantamentos e equipamentos ---------------- */
            'A lenda alvo se torna hexo-imune até o fim do seu próximo turno' => ['hab' => [['g' => 'conjurar', 'passos' => [
                ['a' => 'palavra', 'pal' => 'resistencia', 'temp' => false, 'dura' => 'prox_fim'] + $lenda()]]]],
            'Vire a lenda alvo de cabeça para baixo. Enquanto estiver assim, ela é hexo-imune e indestrutível e não pode atacar. O dono dela pode pagar 1 em aura para desvirá-la'
                => ['hab' => [['g' => 'conjurar', 'passos' => [['a' => 'status', 's' => 'silhueta'] + $lenda()]]]],
            'Todos os bônus de ataque ou defesa recebidos pelo alvo decorrente de feitiços e efeitos de lendas é aumentado em +1/+1'
                => ['mods' => ['fl' => ['amplifica'], 'alvo' => 'qualquer']],
            'Para cada propriedade Fisiomantica em jogo, o jogador alvo bebe um shot' => ['hab' => [['g' => 'conjurar', 'passos' => [
                ['a' => 'shot', 'n' => '#props', 'alvo' => 'jogador']]]]],
            'Troque o alvo de um efeito ou ataque ocorrendo' => ['resp' => 'imbuir'],
            'A carta equipada não sofre dano ou pode ser destruída por feitiços' => ['mods' => ['fl' => ['mitigacao']]],
            'Se o equipado for aliado, ganha +1/0. Se for inimigo, recebe 2 em dano' => ['mods' => ['quente' => true, 'alvo' => 'qualquer']],
            'A Lenda equipada ganha Regenerar-se 1 até que morra uma vez' => ['mods' => ['regen' => 1]],
            'Sempre que um Kuro for invocado, roube 5$ ou faça uma Lenda alvo receber +1/+1' => ['hab' => [['g' => 'entra', 'f' => ['sub' => 'kuro'], 'passos' => [
                ['a' => 'ou', 'ops' => [['Roubar 5$', [['a' => 'roubar', 'n' => 5, 'alvo' => 'jogador', 'filtro' => 'inimigo']]],
                                        ['+1/+1 numa Lenda', [$buff(1, 1, $lenda())]]]]]]]],
            'Todas as cartas são hexomantes' => ['est' => [['g' => 'hexo_todos']]],
            'Mantido. Todo início de turno, suas lendas ganham +0/+2' => ['hab' => [['g' => 'inicio', 'passos' => [$buff(0, 2, ['alvo' => 'lendas', 'filtro' => 'meu'])]]]],
            'No fim do próximo turno do jogador alvo, a lenda alvo morre' => ['hab' => [['g' => 'conjurar', 'passos' => [
                ['a' => 'alvo_jog', 'alvo' => 'jogador'], ['a' => 'marcar_lenda'] + $lenda(),
                ['a' => 'atrasar', 'quando' => 'fim', 'de' => 'jog', 'passos' => [['a' => 'destruir', 'alvo' => 'ultima', 'filtro' => null]]]]]]],
            'O equipado não sofre mais dano de efeitos de cartas' => ['mods' => ['fl' => ['imune_efeito']]],
            'A lenda equipada, na primeira vez que fosse morrer, ressucita' => ['mods' => ['salva' => 'recursao']],
            'Negue a invocação da carta alvo' => ['resp' => 'negar'],
            'Busque duas formas hexomantes em seu baralho e adicione-as a sua mão' => ['hab' => [['g' => 'conjurar', 'passos' => [
                ['a' => 'buscar', 'alvo' => 'eu', 'n' => 2, 'sub' => 'forma', 'dica' => 'forma hexomante']]]]],
            'Toda vez que o jogador alvo for atacado, enquanto essa carta existir em campo, ele vira um shot' => ['hab' => [
                ['g' => 'conjurar', 'passos' => [['a' => 'fixar', 'alvo' => 'jogador']]],
                ['g' => 'atacado', 'f' => ['fixo' => true], 'passos' => [['a' => 'shot', 'n' => 1, 'alvo' => 'evento']]]]],
            'Invoque três fichas de animal 1/1. Busque no seu baralho por uma propriedade fisiomantica, ela afeta as três fichas' => ['hab' => [['g' => 'conjurar', 'passos' => [
                self::ficha('Animal', 1, 1, [], ['n' => 3, 'marca' => true]),
                ['a' => 'buscar', 'alvo' => 'eu', 'n' => 1, 'sub' => 'propriedade', 'dica' => 'propriedade fisiomântica', 'destino' => 'fichas']]]]],
            'Equipe a uma Lenda. Quando ela morrer, ressucite-a e sacrifique essa carta. Ela ganha +3/+3' => ['mods' => ['salva' => 'conquistador']],
            'A carta equipada, quando for alvo de ataques ou efeitos, provoca uma moeda, somente se sair coroa ela é afetada' => ['mods' => ['fl' => ['moeda']]],
            'Selecione 3 lendas de um jogador alvo, elas recebem hexo-imunidade' => ['hab' => [['g' => 'conjurar', 'passos' => array_merge(
                [['a' => 'alvo_jog', 'alvo' => 'jogador']],
                array_fill(0, 3, ['a' => 'palavra', 'pal' => 'resistencia', 'temp' => false, 'alvo' => 'lenda', 'filtro' => 'jog']))]]],
            'Revele cartas do topo do seu baralho até achar um hexomante ou fisiomante, invoque-o sem pagar os custos. Embaralhe' => ['hab' => [['g' => 'conjurar', 'passos' => [
                ['a' => 'revelar_ate', 'sub' => ['hexomante', 'fisiomante']]]]]],
            'As próximas duas cartas conjuradas pelo jogador alvo são canceladas' => ['hab' => [['g' => 'conjurar', 'passos' => [
                ['a' => 'fixar', 'alvo' => 'jogador'], ['a' => 'cont', 'k' => 'cancela', 'd' => 2, 'onde' => 'fonte']]]]],

            /* ---------------- Lendas ---------------- */
            'Saque uma propriedade fisiomante de seu baralho' => ['hab' => [['g' => 'entrar', 'passos' => [
                ['a' => 'buscar', 'alvo' => 'eu', 'n' => 1, 'sub' => 'propriedade', 'dica' => 'propriedade fisiomântica']]]]],
            'Quando gato mágico sair do campo, você pode pagar 5$ e devolvê-lo para o campo' => ['hab' => [['g' => 'morrer', 'passos' => [
                $sim(2), ['a' => 'perder', 'n' => 5, 'alvo' => 'eu'], ['a' => 'voltar']]]]],
            'Toda vez que um hexomante entrar num cemitério, recupere 10$' => ['hab' => [['g' => 'cemiterio', 'f' => ['sub' => 'hexomante'], 'passos' => [
                ['a' => 'ganhar', 'n' => 10, 'alvo' => 'eu']]]]],
            'Ao invocar Isaque, olhe as 3 cartas do topo do seu baralho. Mude a ordem entre elas como preferir e as coloque de volta' => ['hab' => [['g' => 'entrar', 'passos' => [
                ['a' => 'ordenar', 'n' => 3, 'alvo' => 'eu']]]]],
            'Adicione a defesa de Pedrinha ao ataque de suas lendas. Pedrinha morre quando você lançar sua terceira lenda em campo' => [
                'est' => [['q' => 'meus_outros', 'atk' => '#vida_fonte']],
                'hab' => [['g' => 'lancar_minha', 'passos' => [['a' => 'cont', 'k' => 'lendas', 'd' => 1, 'onde' => 'fonte'],
                    ['a' => 'se', 'c' => 'cont:lendas>=3', 'passos' => [['a' => 'destruir'] + $fonte]]]]]],
            'Invoque uma ficha de mão 1/1' => ['hab' => [['g' => 'entrar', 'passos' => [self::ficha('Mão', 1, 1)]]]],
            'Quando Amir atacar, crie X fichas de lança 1/1 atacando, onde X é o número de vezes que Amir já atacou' => ['hab' => [['g' => 'atacar', 'passos' => [
                self::ficha('Lança', 1, 1, [], ['n' => '#ataques', 'atacando' => true])]]]],
            'Quando Aporium atacar, ele se torna posse do jogador defensor. Se Aporium terminou seu turno em seu campo, perca 10$' => ['hab' => [
                ['g' => 'atacar', 'passos' => [['a' => 'posse']]], ['g' => 'fim_meu', 'passos' => [['a' => 'perder', 'n' => 10, 'alvo' => 'eu']]]]],
            'Toda vez que uma lenda for invocada para você, você pode transformá-la em um cara-lata' => ['hab' => [['g' => 'entra_minha', 'passos' => [
                $sim(1), ['a' => 'transformar']]]]],
            'Toda vez que uma Lenda morrer, o jogador dono recebe um estrídio. Todo final de turno em que você tiver um estrídio, perca 10$' => ['hab' => [
                ['g' => 'morre', 'passos' => [['a' => 'cont', 'k' => 'estridio', 'd' => 1, 'onde' => 'evento_jog']]],
                ['g' => 'fim', 'passos' => [['a' => 'se', 'c' => 'vez:estridio>=1', 'passos' => [['a' => 'perder', 'n' => 10, 'alvo' => 'vez']]]]]]],
            'Quando invocado, você pode pagar +1 aura quantas vezes quiser para invocar mais estripadores em valor equivalente. No início de seu turno, você pode sacrificar todos os seus estripadores e invocar um Estripador com ataque e defessa igual ao dobro do ataque dos estripadores sacrificados' => ['hab' => [
                ['g' => 'entrar', 'passos' => [['a' => 'laco', 'energia' => 1, 'texto' => 'pagar 1 de energia e invocar mais um Estripador',
                    'passos' => [['a' => 'ficha', 'n' => 1, 'alvo' => 'eu', 'copia' => true]]]]],
                ['g' => 'inicio_meu', 'passos' => [$sim(1), ['a' => 'fundir']]]]],
            'Compre duas cartas, no seu próximo turno, pule sua fase de compra' => ['hab' => [['g' => 'entrar', 'passos' => [
                ['a' => 'comprar', 'n' => 2, 'alvo' => 'eu'], ['a' => 'pular_compra', 'alvo' => 'eu']]]]],
            'Gabriel deve atacar todo turno se for capaz' => ['est' => [['q' => 'fonte', 'fl' => ['deve_atacar']]]],
            'Você sempre pode olhar a carta do topo do seu baralho' => ['est' => [['j' => 've_topo']]],
            'Quando invocado ou quando atacar, mate uma lenda alvo. Se essa lenda morrer, devolva as lendas mortas dessa forma' => ['hab' => [
                ['g' => 'entrar', 'passos' => [['a' => 'destruir', 'anotar' => true] + $lenda()]],
                ['g' => 'atacar', 'passos' => [['a' => 'destruir', 'anotar' => true] + $lenda()]],
                ['g' => 'morrer', 'passos' => [['a' => 'reviver_mortas']]]]],
            'Quando uma lenda aliada sofrer dano, você pode transferir qualquer quantia deste dano para Alvinho. Se equipado com Imbuir, pode transferir para qualquer Lenda em campo'
                => ['est' => [['q' => 'fonte', 'fl' => ['alvinho']]]],
            'Uma vez por turno, você pode descartar uma carta, se fizer isso, Aporium não pode sofrer dano este turno. Todo início de turno, dobre o ataque de Aporium' => [
                'ativ' => [['rot' => 'Descartar uma carta: não sofre dano nem é destruído neste turno', 'custo' => ['descartar' => 1], 'vez' => 'qualquer', 'uma' => true,
                    'passos' => [['a' => 'status', 's' => 'semdano'] + $fonte]]],
                'hab' => [['g' => 'inicio', 'passos' => [['a' => 'dobrar'] + $fonte]]]],
            'Todos os seus hexomantes ganham toque da morte e inicativa' => ['est' => [['q' => 'meus', 'sub' => 'hexomante', 'pal' => ['toque', 'iniciativa']]]],
            'Cada início de turno, crie uma ficha 4/4 de constructo se alguma lenda sua morreu no turno passado. Esta lenda ganha +2/+2 para cada ficha invocada assim' => ['hab' => [
                ['g' => 'inicio', 'passos' => [['a' => 'se', 'c' => 'morreu_turno', 'passos' => [self::ficha('Constructo', 4, 4), $buff(2, 2, $fonte)]]]]]],
            'Todo início de turno em que Dante estiver em jogo, invoque uma ficha 1/1 de Olhinho. Olhinhos invocados dessa forma ganham +1/+1 para cada Olhinho em campo' => ['hab' => [
                ['g' => 'inicio', 'passos' => [self::ficha('Olhinho', 1, 1, [], ['est' => [['q' => 'fonte', 'atk' => '#olhinhos', 'vida' => '#olhinhos']]])]]]],
            'Quando atacar um jogador diretamente, ele perde 1 energia em seu próximo turno' => ['hab' => [['g' => 'direto', 'passos' => [
                ['a' => 'atrasar', 'quando' => 'inicio', 'de' => 'evento', 'passos' => [['a' => 'energia', 'n' => 1, 'neg' => true, 'alvo' => 'quem']]]]]]],
            'Se Flavinho estiver em seu cemitério, você pode descartar uma carta para invocá-lo sem pagar seus custos' => ['ativ' => [
                ['rot' => 'Descartar uma carta: invocar do cemitério', 'zona' => 'cemiterio', 'custo' => ['descartar' => 1], 'vez' => 'meu', 'passos' => [['a' => 'voltar']]]]],
            'Diminua o custo de seus feitiços que causam dano em -1' => ['est' => [['j' => 'custo', 'tipo' => 'feitico_dano', 'd' => -1]]],
            'Virar: Perca 10$, o jogador alvo vira 2 shots' => ['ativ' => [['rot' => 'Virar: perde 10$ e o jogador alvo toma 2 shots', 'custo' => ['virar' => true], 'vez' => 'meu',
                'passos' => [['a' => 'perder', 'n' => 10, 'alvo' => 'eu'], ['a' => 'shot', 'n' => 2, 'alvo' => 'jogador']]]]],
            'Quando Kaique entrar ou morrer, invoque uma ficha de carniçal 2/2 com Toque mortífero Se equipado com Amplificação, os carniçais são 5/5' => ['hab' => array_map(fn ($g) => ['g' => $g, 'passos' => [
                ['a' => 'se', 'c' => 'equip:amplificacao', 'passos' => [self::ficha('Carniçal', 5, 5, ['toque'])], 'senao' => [self::ficha('Carniçal', 2, 2, ['toque'])]]]], ['entrar', 'morrer'])],
            'Todo início de turno em que você comprou mais do que uma carta, invoque uma ficha de aura' => ['hab' => [['g' => 'inicio', 'passos' => [
                ['a' => 'se', 'c' => 'comprou_mais1', 'passos' => [['a' => 'energia', 'n' => 1, 'alvo' => 'eu']]]]]]],
            'Todas as suas lendas ganham +0/+X, onde X é o número de cartas da sua mão' => ['est' => [['q' => 'meus', 'vida' => '#mao']]],
            'Quando Lucas mata uma Lenda, compre uma carta. Se a carta comprada for uma lenda, você pode invocá-la ao descartar uma carta de sua mão' => ['hab' => [['g' => 'matar', 'passos' => [
                ['a' => 'comprar', 'n' => 1, 'alvo' => 'eu', 'marca' => true],
                ['a' => 'se', 'c' => 'comprada_lenda', 'passos' => [$sim(2), ['a' => 'descartar', 'n' => 1, 'alvo' => 'eu', 'fora_comprada' => true], ['a' => 'invocar_comprada']]]]]]],
            'Toda vez que um feitiço for conjurado, scry 2. Se for uma forma hexomante, scry 2 e compre 1' => ['hab' => [['g' => 'feitico', 'passos' => [
                ['a' => 'scry', 'n' => 2, 'alvo' => 'eu'],
                ['a' => 'se', 'c' => 'ctx:forma', 'passos' => [['a' => 'scry', 'n' => 2, 'alvo' => 'eu'], ['a' => 'comprar', 'n' => 1, 'alvo' => 'eu']]]]]]],
            'Enquanto estiver no campo, veja a carta do topo do seu deck ou a mão de um oponente' => ['hab' => [['g' => 'entrar', 'passos' => [['a' => 'ver']]]]],
            'Todo início de turno, invoque uma ficha de Carniçal 2/2 com Toque mortífero' => ['hab' => [['g' => 'inicio', 'passos' => [self::ficha('Carniçal', 2, 2, ['toque'])]]]],
            'Regenerar 2, sobrepujar, vôo 09 não sofre dano por cartas de feitiço' => ['regen' => 2, 'est' => [['q' => 'fonte', 'pal' => ['sobrepujar', 'voar'], 'fl' => ['imune_feitico']]]],
            'Quando anísio entrar ou morrer, scry X, onde X é o número de lendas no seu cemitério' => ['hab' => array_map(fn ($g) => ['g' => $g, 'passos' => [
                ['a' => 'scry', 'n' => '#lendas_cem', 'alvo' => 'eu']]], ['entrar', 'morrer'])],
            'Esta lenda sempre ataca primeiro. Quando ela mata uma Lenda e sai vivo, recupere 80$' => ['est' => [['q' => 'fonte', 'pal' => ['iniciativa']]],
                'hab' => [['g' => 'matar', 'passos' => [['a' => 'ganhar', 'n' => 80, 'alvo' => 'eu']]]]],
            'Não sofre dano por ataques de lendas' => ['est' => [['q' => 'fonte', 'fl' => ['imune_combate']]]],
            'Se Carneiro morrer em combate, mate a carta que combateu com ele. Sempre que uma Lenda morrer, roube 10$' => ['hab' => [
                ['g' => 'morrer_combate', 'passos' => [['a' => 'destruir', 'alvo' => 'evento', 'filtro' => null]]],
                ['g' => 'morre', 'passos' => [['a' => 'roubar', 'n' => 10, 'alvo' => 'jogador', 'filtro' => 'inimigo']]]]],
            'Todo início de turno, o jogador alvo deve acertar qual o tipo (lenda ou feitiço) da carta no topo do seu deck. Revele-a, se ele errou, compre uma carta' => ['hab' => [['g' => 'inicio', 'passos' => [
                ['a' => 'adivinhar', 'alvo' => 'jogador', 'filtro' => 'inimigo', 'errou' => [['a' => 'comprar', 'n' => 1, 'alvo' => 'eu']]]]]]],
            'Toda vez que um jogador Mill ou Scry, diminua o poder de todas as lendas dele em 1' => ['hab' => [['g' => 'scry', 'passos' => [
                $buff(-1, 0, ['alvo' => 'lendas', 'filtro' => 'evento'])]]]],
            'Para cada outra lenda no campo, Estilo ganha +1/+1' => ['est' => [['q' => 'fonte', 'atk' => '#outras', 'vida' => '#outras']]],
            'Quando invocado ou quando atacar, reduza o custo de uma carta em sua mão em -1' => ['hab' => array_map(fn ($g) => ['g' => $g, 'passos' => [
                ['a' => 'barato', 'd' => 1, 'alvo' => 'eu']]], ['entrar', 'atacar'])],
            'Sempre que uma forma hexomante for conjurada, cause 3 de dano a algo' => ['hab' => [['g' => 'feitico', 'f' => ['sub' => 'forma'], 'passos' => [
                ['a' => 'dano', 'n' => 3, 'alvo' => 'qualquer', 'filtro' => 'qualquer']]]]],
            'Quando um jogador recuperar dinheiro, recupere metade daquele valor. Quando um jogador perder ao menos 10$, compre uma carta' => ['hab' => [
                ['g' => 'ganha', 'passos' => [['a' => 'ganhar', 'n' => '#metade', 'alvo' => 'eu']]],
                ['g' => 'perde', 'f' => ['min' => 10], 'passos' => [['a' => 'comprar', 'n' => 1, 'alvo' => 'eu']]]]],
            'Toda vez que uma carta for comprada ou descartada por você, roube 10$' => ['hab' => array_map(fn ($g) => ['g' => $g, 'passos' => [
                ['a' => 'roubar', 'n' => 10, 'alvo' => 'jogador', 'filtro' => 'inimigo']]], ['comprar_meu', 'descartar_meu'])],
            'Toda vez que você ganhar vida, compre uma carta' => ['hab' => [['g' => 'ganha_meu', 'passos' => [['a' => 'comprar', 'n' => 1, 'alvo' => 'eu']]]]],
            'Regenerar 1. Quando Maldito ataca, ele ataca todas as Lendas do jogador alvo' => ['regen' => 1, 'est' => [['q' => 'fonte', 'fl' => ['ataca_todas']]]],
            'Toda vez que houver uma batalha entre duas lendas, some o poder dessa carta a uma delas' => ['est' => [['q' => 'fonte', 'fl' => ['organizador']]]],
            'Sobrepujar, regenerar 2. Você pode sacrificar outra lenda sua, se fizer isso, Barrera ganha +2/+2' => ['regen' => 2, 'est' => [['q' => 'fonte', 'pal' => ['sobrepujar']]],
                'ativ' => [['rot' => 'Sacrificar outra Lenda sua: +2/+2', 'custo' => ['sacrificar' => 1], 'vez' => 'meu', 'passos' => [$buff(2, 2, $fonte)]]]],
            'Ganha indestrutível se houver um hexomante no campo inimigo. Sempre que você ativar um feitiço, scry 3' => [
                'est' => [['q' => 'fonte', 'pal' => ['indestrutivel'], 'se' => 'hexo_inimigo']],
                'hab' => [['g' => 'feitico_meu', 'passos' => [['a' => 'scry', 'n' => 3, 'alvo' => 'eu']]]]],
            'Serrotinho pode ser equipado a alguma lenda, somando seu ataque ao dela. Se a lenda equipada fosse morrer, mate serrotinho ao invés' => ['ativ' => [
                ['rot' => 'Equipar em uma Lenda sua (soma o ataque e morre no lugar dela)', 'vez' => 'meu', 'custo' => [], 'passos' => [['a' => 'serrar'] + $lenda('meu')]]]],
            'Escolha uma letra, cartas de feitiço com essa letra não podem ser invocadas' => ['hab' => [['g' => 'entrar', 'passos' => [['a' => 'letra']]]]],
            'quando invocado, busque dois hexomantes no seu deck, invoque um sem pagar os custos e jogue um no cemitério' => ['hab' => [['g' => 'entrar', 'passos' => [
                ['a' => 'buscar', 'alvo' => 'eu', 'n' => 1, 'sub' => 'hexomante', 'dica' => 'hexomante para invocar', 'destino' => 'mesa'],
                ['a' => 'buscar', 'alvo' => 'eu', 'n' => 1, 'sub' => 'hexomante', 'dica' => 'hexomante para o cemitério', 'destino' => 'cemiterio']]]]],
            'Quando entra, todos os outros hexomantes morrem' => ['hab' => [['g' => 'entrar', 'passos' => [
                ['a' => 'destruir', 'alvo' => 'lendas', 'filtro' => 'qualquer', 'sub' => 'hexomante', 'outras' => true]]]]],
            'Quando morrer, invoque dois drixopatas com metade da defesa. Perde este efeito se tiver defesa igual ou menor a 2' => ['hab' => [['g' => 'morrer', 'passos' => [
                ['a' => 'se', 'c' => 'ctx:max>2', 'passos' => [['a' => 'ficha', 'n' => 2, 'alvo' => 'eu', 'copia' => true, 'vida' => '#meia_ctx']]]]]]],
            'Não pode morrer em combate. Toda vez que atacar, recebe +3/+3' => ['est' => [['q' => 'fonte', 'fl' => ['nao_morre_combate']]],
                'hab' => [['g' => 'atacar', 'passos' => [$buff(3, 3, $fonte)]]]],
            'Para cada Propriedade fisiomantica em jogo, Ícaro ganha uma palavra chave a sua escolha' => ['hab' => [
                ['g' => 'entrar', 'passos' => [['a' => 'vezes', 'n' => '#props', 'passos' => [['a' => 'palavra', 'pal' => '?', 'temp' => false] + $fonte]]]],
                ['g' => 'feitico', 'f' => ['sub' => 'propriedade'], 'passos' => [['a' => 'palavra', 'pal' => '?', 'temp' => false] + $fonte]]]],
            'Voar, sobrepujar. Lucius não sofre dano de cartas com custo de energia 2 ou menos' => ['est' => [['q' => 'fonte', 'pal' => ['voar', 'sobrepujar'], 'fl' => ['imune_custo2']]]],
            'Sempre que uma forma hexomante for conjurada, essa lenda recebe +2/+2' => ['hab' => [['g' => 'feitico', 'f' => ['sub' => 'forma'], 'passos' => [$buff(2, 2, $fonte)]]]],
            'Quando bloquear alguma carta, invoque uma ficha de Hexomante 1/1 e busque no seu baralho por uma forma hexomante' => ['hab' => [['g' => 'bloquear', 'passos' => [
                self::ficha('Hexomante', 1, 1), ['a' => 'buscar', 'alvo' => 'eu', 'n' => 1, 'sub' => 'forma', 'dica' => 'forma hexomante']]]]],
            'Todo início de turno, todos exceto você viram um shot. Toda vez que um jogador virar um shot, compre uma carta ou cause 2 de dano a algo' => ['hab' => [
                ['g' => 'inicio', 'passos' => [['a' => 'shot', 'n' => 1, 'alvo' => 'inimigos']]],
                ['g' => 'shot', 'passos' => [['a' => 'ou', 'ops' => [['Comprar uma carta', [['a' => 'comprar', 'n' => 1, 'alvo' => 'eu']]],
                    ['2 de dano em algo', [['a' => 'dano', 'n' => 2, 'alvo' => 'qualquer', 'filtro' => 'qualquer']]]]]]]]],
            'Para cada Kuro em campo, Yeti ganha +3/+3' => ['est' => [['q' => 'fonte', 'atk' => '#kuros3', 'vida' => '#kuros3']]],
            'Todos os seus hexomantes custam -1' => ['est' => [['j' => 'custo', 'sub' => 'hexomante', 'd' => -1]]],
            'Toda vez que atacar, invoque uma ficha de animal 2/2 sobrepujar. Para cada ficha invocada dessa forma, adicione uma palavra chave a Abel' => ['hab' => [['g' => 'atacar', 'passos' => [
                self::ficha('Animal', 2, 2, ['sobrepujar']), ['a' => 'palavra', 'pal' => '?', 'temp' => false] + $fonte]]]],
            'Se amir atacar diretamente um jogador 3 vezes, aquele jogador perde' => ['hab' => [['g' => 'direto', 'passos' => [
                ['a' => 'cont', 'k' => 'amir', 'd' => 1, 'onde' => 'fonte_evento'],
                ['a' => 'se', 'c' => 'evento_fonte:amir>=3', 'passos' => [['a' => 'derrotar', 'alvo' => 'evento']]]]]]],
            'Sacrifique uma lenda para invocar Caveirão. Quando ele matar uma lenda, invoque uma ficha de carniçal com status e palavras chave iguais aos da lenda morta' => ['sac' => 1,
                'hab' => [['g' => 'matar', 'passos' => [self::ficha('Carniçal', '#atk_ctx', '#max_ctx', [], ['pal_ctx' => true])]]]],
            'Lendas com golpe duplo atacam uma vez adicional' => ['est' => [['j' => 'duplo_extra']]],
            'Quando entrar, olhe as 5 cartas do topo do seu deck, ative todos os feitiços instantâneos que houverem entre eles sem pagar os seus custos' => ['hab' => [['g' => 'entrar', 'passos' => [
                ['a' => 'ativar_topo', 'n' => 5, 'alvo' => 'eu']]]]],
            'Para cada carta Aluno, a cada início de turno, invoque uma ficha de Olhinho 3/3. Se você tiver 1o olhinhos invocados dessa forma, ganhe o jogo' => ['hab' => [['g' => 'inicio', 'passos' => [
                ['a' => 'vezes', 'n' => '#alunos', 'passos' => [self::ficha('Olhinho', 3, 3, [], ['tag' => 'hix'])]],
                ['a' => 'se', 'c' => 'tag:hix>=10', 'passos' => [['a' => 'vencer']]]]]]],
            'Quando Rivaldo atacar, escolha duas lendas alvo para se atacarem. Quando uma lenda morrer, seu dono vira um shot' => ['hab' => [
                ['g' => 'atacar', 'passos' => [['a' => 'lutar']]], ['g' => 'morre', 'passos' => [['a' => 'shot', 'n' => 1, 'alvo' => 'evento']]]]],
            'Toda vez que uma lenda morrer ou entrar, o dono dela vira um shot. Sempre que um jogador virar um shot, ele perde 10$' => ['hab' => [
                ['g' => 'morre', 'passos' => [['a' => 'shot', 'n' => 1, 'alvo' => 'evento']]], ['g' => 'entra', 'passos' => [['a' => 'shot', 'n' => 1, 'alvo' => 'evento']]],
                ['g' => 'shot', 'passos' => [['a' => 'perder', 'n' => 10, 'alvo' => 'evento']]]]],
            'Todo início de turno, ganhe X energia. Onde X é a soma dos PVs de suas lendas, exceto robertinho' => ['hab' => [['g' => 'inicio', 'passos' => [
                ['a' => 'energia', 'n' => '#pv_outras', 'alvo' => 'eu']]]]],
            'Toda vez que um feitiço causar dano, receba +1 aura. Sempre que você gastar exatamente 2 em aura para um feitiço, receba +2 em aura' => ['hab' => [
                ['g' => 'dano_feitico', 'passos' => [['a' => 'energia', 'n' => 1, 'alvo' => 'eu']]], ['g' => 'gasto2', 'passos' => [['a' => 'energia', 'n' => 2, 'alvo' => 'eu']]]]],
            'Compre uma carta para cada carta em sua mão. Ative todas as propriedades Fisiomantes que comprar dessa forma e equipe-as a Abel' => ['hab' => [['g' => 'entrar', 'passos' => [
                ['a' => 'comprar', 'n' => '#mao', 'alvo' => 'eu', 'marca' => true], ['a' => 'equipar_compradas', 'sub' => 'propriedade']]]]],
            'Quando invocada, mate uma Lenda alvo para cada carta que você descartar' => ['hab' => [['g' => 'entrar', 'passos' => [
                ['a' => 'laco', 'texto' => 'descartar uma carta para matar uma Lenda', 'passos' => [['a' => 'descartar', 'n' => 1, 'alvo' => 'eu', 'obrig' => true], ['a' => 'destruir'] + $lenda()]]]]]],
            'Todo início de turno: Dobre o poder dessa lenda e das Lendas que estão em sua foto, invoque duas fichas de olhinho 1/1, cause 2 de dano a todas as lendas inimigas' => ['hab' => [['g' => 'inicio', 'passos' => [
                ['a' => 'dobrar'] + $fonte, ['a' => 'dobrar', 'alvo' => 'lendas', 'filtro' => 'meu', 'foto' => true],
                self::ficha('Olhinho', 1, 1, [], ['n' => 2]), ['a' => 'dano', 'n' => 2, 'alvo' => 'lendas', 'filtro' => 'inimigo']]]]],
            'Quando invocada, invoque 3 fichas de Lacaio 4/4 sobrepujar. Sempre que uma Lenda sua morrer, invoque mais uma' => ['hab' => [
                ['g' => 'entrar', 'passos' => [self::ficha('Lacaio', 4, 4, ['sobrepujar'], ['n' => 3])]],
                ['g' => 'morre_minha', 'passos' => [self::ficha('Lacaio', 4, 4, ['sobrepujar'])]]]],
            'Todo início de turno, você pode sacrificar duas lendas, se fizer isso, cause 4 de dano a todos os outros jogadores. Sempre que você ativar uma forma hexomante, cause 1 de dano a todos os outros jogadores' => ['hab' => [
                ['g' => 'inicio', 'passos' => [['a' => 'se', 'c' => 'outras>=2', 'passos' => [$sim(3), ['a' => 'sacrificar', 'n' => 1, 'alvo' => 'eu', 'obrig' => true],
                    ['a' => 'sacrificar', 'n' => 1, 'alvo' => 'eu', 'obrig' => true], ['a' => 'dano', 'n' => 4, 'alvo' => 'inimigos', 'filtro' => null]]]]],
                ['g' => 'feitico_meu', 'f' => ['sub' => 'forma'], 'passos' => [['a' => 'dano', 'n' => 1, 'alvo' => 'inimigos', 'filtro' => null]]]]],
            'Todo início de turno, as Lendas inimigas recebem -1/-1. Sempre que uma Lenda morrer dessa forma, invoque uma ficha de Monolisco com o Primeiro efeito dessa lenda' => ['hab' => [
                ['g' => 'inicio', 'passos' => [$buff(-1, -1, ['alvo' => 'lendas', 'filtro' => 'inimigo', 'marca_por' => true])]],
                ['g' => 'morre', 'f' => ['por' => true], 'passos' => [['a' => 'ficha', 'n' => 1, 'alvo' => 'eu', 'copia' => true, 'so' => 1]]]]],
            'Daqui a 6 turnos, ganhe o jogo' => ['hab' => [['g' => 'entrar', 'passos' => [
                ['a' => 'atrasar', 'quando' => 'inicio', 'de' => 'qualquer', 'turnos' => 6, 'fonte_viva' => true, 'passos' => [['a' => 'vencer']]]]]]],
            'jogue ao cemitério as 10 primeiras cartas do seu deck. Todo início de turno, ative algum feitiço no seu cemitério sem pagar os custos' => ['hab' => [
                ['g' => 'entrar', 'passos' => [['a' => 'moer', 'n' => 10, 'alvo' => 'eu']]], ['g' => 'inicio', 'passos' => [['a' => 'ativar_cem', 'alvo' => 'eu']]]]],
            'Se Rarakoara morrer, perca 10$ e o traga de volta virado com +1/+1' => ['hab' => [['g' => 'morrer', 'passos' => [
                ['a' => 'perder', 'n' => 10, 'alvo' => 'eu'], ['a' => 'voltar', 'virada' => true, 'atk' => 1, 'vida' => 1]]]]],
            'sacrifique duas lendas para invocar Azazyel. Todo início de turno, todo outro jogador sofre o poder de Azazyel em dano, que pode redistribuir entre as próprias lendas para evitar' => ['sac' => 2,
                'hab' => [['g' => 'inicio', 'passos' => [['a' => 'absorver', 'n' => 'poder', 'alvo' => 'inimigos']]]]],
            'Todos os seus Kuros recebem +4/+4 e sobrepujar. Radagast recebe todas as palavras chave de lendas no seu campo' => ['est' => [
                ['q' => 'meus', 'sub' => 'kuro', 'atk' => 4, 'vida' => 4, 'pal' => ['sobrepujar']], ['q' => 'fonte', 'pal' => '*meu']]],
            'Todo início de turno, todos os hexomantes e fisiomantes inimigos morrem. Beba um shot todo início de turno' => ['hab' => [['g' => 'inicio', 'passos' => [
                ['a' => 'destruir', 'alvo' => 'lendas', 'filtro' => 'inimigo', 'sub' => ['hexomante', 'fisiomante']], ['a' => 'shot', 'n' => 1, 'alvo' => 'eu']]]]],
            'Sempre que um feitiço for conjurado, busque uma forma hexomante e coloque em sua mão. Você pode conjurar formas do seu cemitério, banindo-as ao invés do comum' => [
                'est' => [['j' => 'formas_cem']],
                'hab' => [['g' => 'feitico', 'passos' => [['a' => 'buscar', 'alvo' => 'eu', 'n' => 1, 'sub' => 'forma', 'dica' => 'forma hexomante']]]]],

            /* ---------------- cartas novas (lote 2) ---------------- */
            'Sempre que um Kuro for invocado, roube 2$ ou faça uma Lenda alvo receber +1/+1' => ['hab' => [['g' => 'entra', 'f' => ['sub' => 'kuro'], 'passos' => [
                ['a' => 'ou', 'ops' => [['Roubar 2$', [['a' => 'roubar', 'n' => 2, 'alvo' => 'jogador', 'filtro' => 'inimigo']]],
                                        ['+1/+1 numa Lenda', [$buff(1, 1, $lenda())]]]]]]]],
            'A Lenda alvo não pode mais atacar' => ['mods' => ['fl' => ['preso_eq'], 'alvo' => 'qualquer']],
            'Responda ao feitiço alvo, ele tem como alvo todas as cartas do campo ao invés do comum' => ['resp' => 'intoxicar'],
            'Todo ganho de dinheiro é reduzido pela metade. Se houverem duas cópias dessa carta no campo, torna-se impossível ganhar dinheiro'
                => ['est' => [['j' => 'ganho_metade']]],
            'Toda vez que um jogador fosse ganhar dinheiro, ao invés disso, ele a perde' => ['est' => [['j' => 'ganho_inverte']]],
            'Todas as lendas de um jogador alvo sofrem X dano, onde X é o número de propriedade fisiomanticas no seu campo' => ['hab' => [['g' => 'conjurar', 'passos' => [
                ['a' => 'alvo_jog', 'alvo' => 'jogador'], ['a' => 'dano', 'n' => '#props_meu', 'alvo' => 'lendas', 'filtro' => 'jog']]]]],
            'Todas as cartas de um jogador alvo perdem Hexo-imune' => ['hab' => [['g' => 'conjurar', 'passos' => [
                ['a' => 'alvo_jog', 'alvo' => 'jogador'], ['a' => 'tirapal_fixa', 'pal' => 'resistencia', 'alvo' => 'lendas', 'filtro' => 'jog']]]]],
            'Toda vez que o jogador alvo invocar uma ficha, ele perde 3$' => ['hab' => [
                ['g' => 'conjurar', 'passos' => [['a' => 'fixar', 'alvo' => 'jogador']]],
                ['g' => 'ficha', 'f' => ['fixo' => true], 'passos' => [['a' => 'perder', 'n' => 3, 'alvo' => 'evento']]]]],
            'Responda a um feitiço alvo, conjure-o de novo sem pagar os custos' => ['resp' => 'ressonar'],
            'Negue a conjuração da carta alvo respondida' => ['resp' => 'negar_feitico'],
            'Sempre que conjurar uma forma hexomante ou propriedade fisiomante, compre uma carta ou scry 2' => ['hab' => [['g' => 'feitico_meu', 'f' => ['sub' => ['forma', 'propriedade']], 'passos' => [
                ['a' => 'ou', 'ops' => [['Comprar uma carta', [['a' => 'comprar', 'n' => 1, 'alvo' => 'eu']]], ['Scry 2', [['a' => 'scry', 'n' => 2, 'alvo' => 'eu']]]]]]]]],
            'Toda vez que uma lenda em seu controle ganhar pontos de ataque, cause a mesma quantia de dano ao jogador ou lenda alvo' => ['hab' => [['g' => 'ganhou_atk_meu', 'passos' => [
                ['a' => 'dano', 'n' => '#valor', 'alvo' => 'qualquer', 'filtro' => 'qualquer']]]]],
            'Clone a Lenda alvo para o seu lado do campo' => ['hab' => [['g' => 'conjurar', 'passos' => [['a' => 'clonar'] + $lenda()]]]],
            'Tome controle da lenda alvo' => ['mods' => ['controla' => true, 'alvo' => 'inimigo']],
            'No início de seu turno, pague 5 em aura, caso contrário, perca essa carta. Toda vez que uma lenda sua atacar, ataque todas as lendas de todos os inimigos' => [
                'est' => [['q' => 'meus', 'fl' => ['ataca_todas', 'ataca_inimigos']]],
                'hab' => [['g' => 'inicio_meu', 'passos' => [['a' => 'manter_pagando', 'energia' => 5]]]]],
            'Transforme a propriedade fisiomante ou forma hexomante alvo em outra carta de mesmo tipo que estiver em sua mão' => ['hab' => [['g' => 'conjurar', 'passos' => [
                ['a' => 'trocar_forma']]]]],
            'Até o fim do seu próximo turno, você não pode atacar ou ser atacado' => ['hab' => [['g' => 'conjurar', 'passos' => [['a' => 'intocavel']]]]],
            'Quando você juntar em campo 4 cartas com este mesmo nome, ganhe o jogo' => ['est' => [['j' => 'quatro_iguais']]],
            'Todo início de turno, pague 3 ou perca essa carta. Sempre que um jogador conjurar uma carta, você pode pagar 2 para negá-la' => [
                'est' => [['j' => 'painel_negar', 'energia' => 2]],
                'hab' => [['g' => 'inicio_meu', 'passos' => [['a' => 'manter_pagando', 'energia' => 3]]]]],
            'A Lenda alvo se torna algum Kuro do seu campo' => ['hab' => [['g' => 'conjurar', 'passos' => [['a' => 'demonizar'] + $lenda()]]]],
            'Para cada lenda com 2 ou menos em defesa no seu campo, essa carta ganha 1 em ataque' => ['est' => [['q' => 'fonte', 'atk' => '#defesa2']]],
            'Todo jogador, incluindo você, só pode conjurar uma carta por turno' => ['est' => [['g' => 'uma_conjuracao']]],
            'Destrua um encantamento ou equipamento alvo' => ['hab' => [['g' => 'conjurar', 'passos' => [['a' => 'destruir_campo']]]]],
            'Sempre que você conjurar um feitiço que custe 3 ou mais, receba 1 em aura atual' => ['hab' => [['g' => 'feitico_meu', 'f' => ['custo_min' => 3], 'passos' => [
                ['a' => 'energia', 'n' => 1, 'alvo' => 'eu']]]]],
            'Todo jogador compra duas cartas ao invés de uma na própria fase de compras' => ['est' => [['g' => 'compra_dupla']]],
            'Todo início de turno, você pode sacrificar uma lenda e acumular um contador de sacrifício. Formas hexomantes e propriedades fisiomantes custam -1 pra cada dois contadores seus' => [
                'est' => [['j' => 'custo', 'sub' => ['forma', 'propriedade'], 'd' => '#menos_meio:sacrificio']],
                'hab' => [['g' => 'inicio_meu', 'passos' => [['a' => 'se', 'c' => 'outras>=1', 'passos' => [$sim(2), ['a' => 'sacrificar', 'n' => 1, 'alvo' => 'eu', 'obrig' => true],
                    ['a' => 'cont', 'k' => 'sacrificio', 'd' => 1, 'onde' => 'fonte']]]]]]],
            'destrua todas as cartas em campo, todo jogador embaralha a própria mão no próprio deck e compra 5 cartas' => ['hab' => [['g' => 'conjurar', 'passos' => [
                ['a' => 'destruir', 'alvo' => 'tudo', 'filtro' => null], ['a' => 'reembaralhar', 'n' => 5]]]]],
            'Todo início de turno em que você tiver 5 ou mais cartas na mão, roube 5$ de um jogador alvo' => ['hab' => [['g' => 'inicio', 'passos' => [
                ['a' => 'se', 'c' => 'mao>=5', 'passos' => [['a' => 'roubar', 'n' => 5, 'alvo' => 'jogador', 'filtro' => 'inimigo']]]]]]],
            'Ao ser invocado, você pode pagar 15$ para matar a Lenda alvo' => ['hab' => [['g' => 'entrar', 'passos' => [
                $sim(2), ['a' => 'perder', 'n' => 15, 'alvo' => 'eu'], ['a' => 'destruir'] + $lenda()]]]],
            'Toda vez que um jogador fosse invocar uma ficha, gire uma moeda, ele só invoca se sair cara' => ['est' => [['g' => 'ficha_moeda']]],
            'Lucas pode destruir cartas indestrutíveis em combate' => ['est' => [['q' => 'fonte', 'fl' => ['quebra_indestrutivel']]]],
            'Todo início de turno, invoque uma ficha de tranqueira 0/1. Toda carta com nome Amir ganha +3/+3 e uma palavra chave para cada ficha de tranqueira em campo' => [
                // a palavra-chave de cada tranqueira é escolhida pelo dono de cada Amir quando ela entra (vale enquanto ela estiver em campo)
                'est' => [['q' => 'todos', 'nome' => 'amir', 'atk' => '#tranqueiras3', 'vida' => '#tranqueiras3', 'pal' => '*amir']],
                'hab' => [['g' => 'inicio', 'passos' => [self::ficha('Tranqueira', 0, 1)]], ['g' => 'ficha', 'passos' => [['a' => 'amir_palavra']]]]],
            'Todo início de turno, invoque uma ficha de tranqueira 0/1. Toda carta com nome Amir ganha +1/+1 para cada ficha de tranqueira em campo' => [
                'est' => [['q' => 'todos', 'nome' => 'amir', 'atk' => '#tranqueiras', 'vida' => '#tranqueiras']],
                'hab' => [['g' => 'inicio', 'passos' => [self::ficha('Tranqueira', 0, 1)]]]],
            'Se qualquer hexomante for invocado, maguila morre' => ['hab' => [['g' => 'entra', 'f' => ['sub' => 'hexomante'], 'passos' => [['a' => 'destruir'] + $fonte]]]],
            'Quando uma carta morrer, ganhe 1$' => ['hab' => [['g' => 'morre', 'passos' => [['a' => 'ganhar', 'n' => 1, 'alvo' => 'eu']]]]],
            'Se você tiver menos lendas do que o jogador alvo em campo, mate duas lendas alvo dele cujo custo seja 3 ou menos' => ['hab' => [['g' => 'conjurar', 'passos' => [
                ['a' => 'alvo_jog', 'alvo' => 'jogador', 'filtro' => 'inimigo'],
                ['a' => 'se', 'c' => 'menos_lendas_jog', 'passos' => array_fill(0, 2, ['a' => 'destruir', 'alvo' => 'lenda', 'filtro' => 'jog', 'custo_max' => 3])]]]]],
            'Quando uma lenda aliada a isaque atacar, ela ganha +X/+X, onde X é o número de formas hexomantes nos cemitérios de todos os jogadores' => ['hab' => [['g' => 'ataca_minha', 'passos' => [
                ['a' => 'buff', 'atk' => '#formas_cem', 'vida' => '#formas_cem', 'temp' => true, 'alvo' => 'evento', 'filtro' => null]]]]],
            'Toda vez que uma lenda de custo 3 ou menos for invocada, você pode pagar 10$ para negar a invocação' => ['est' => [['j' => 'marechal_negar', 'vida' => 10, 'custo_max' => 3]]],
            'Regenerar 1. Toda primeira carta conjurada por um jogador é negada' => ['regen' => 1, 'est' => [['g' => 'basileu']]],
            'Sempre que Maldito morrer pelo ataque de uma lenda custo 3 ou menos ou pelo efeito de um feitiço de custo 3 ou menos, ele revive com +1/+1 acumulativo e +1 palavra chave' => ['hab' => [['g' => 'morrer', 'passos' => [
                ['a' => 'se', 'c' => 'morte_barata', 'passos' => [['a' => 'reviver_maldito']]]]]]],
            'Seus encantamentos não podem sair de campo enquanto Vassago estiver vivo' => ['est' => [['j' => 'encantos_fixos']]],
            'Todo início de turno, sacrifique 10$, todo jogador sacrifica uma Lenda' => ['hab' => [['g' => 'inicio', 'passos' => [
                ['a' => 'perder', 'n' => 10, 'alvo' => 'eu'], ['a' => 'sacrificar', 'n' => 1, 'alvo' => 'todos', 'obrig' => true]]]]],
            'Você pode re-invocar essa Lenda mesmo que ela esteja em campo, pagando 2 ao invés do preço comum' => ['ativ' => [
                ['rot' => 'Pagar 2 de energia: invocar outra cópia', 'custo' => ['energia' => 2], 'vez' => 'meu', 'passos' => [['a' => 'ficha', 'n' => 1, 'alvo' => 'eu', 'copia' => true]]]]],
            'Toda vez que uma lenda aliada morrer ou for descartada, Valete acumula +2/+2' => ['hab' => [
                ['g' => 'morre_minha', 'passos' => [$buff(2, 2, $fonte)]], ['g' => 'descartar_meu', 'f' => ['tipo' => 'lenda'], 'passos' => [$buff(2, 2, $fonte)]]]],
            'A cada duas fichas que um jogador invocar, invoque uma ficha de caveira 2/2 com toque mortífero' => ['hab' => [['g' => 'ficha', 'f' => ['nao_proprio' => true], 'passos' => [
                ['a' => 'cont', 'k' => 'fichas', 'd' => 1, 'onde' => 'fonte_evento'],
                ['a' => 'se', 'c' => 'par:fichas', 'passos' => [self::ficha('Caveira', 2, 2, ['toque'])]]]]]],
            'Toda vez que causar dano a um jogador ou sofrer dano, crie uma ficha de aura' => ['hab' => [
                ['g' => 'sofreu', 'passos' => [['a' => 'energia', 'n' => 1, 'alvo' => 'eu']]], ['g' => 'feriu_jogador', 'passos' => [['a' => 'energia', 'n' => 1, 'alvo' => 'eu']]]]],
            'Toda vez que você conjurar um feitiço, acumule um contador nessa lenda. Você recebe fichas de aura equivalentes aos contadores dessa lenda, que resetam e que você pode gastar por turno' => ['hab' => [
                ['g' => 'feitico_meu', 'passos' => [['a' => 'cont', 'k' => 'aura', 'd' => 1, 'onde' => 'fonte']]],
                ['g' => 'inicio_meu', 'passos' => [['a' => 'energia', 'n' => '#cont:aura', 'alvo' => 'eu']]]]],
            'Toda vez que um jogador sofrer dano, ele Mill aquela quantidade de cartas. Todo início de turno, todo jogador exceto você sofre X em dano, onde X é a quantidade de cartas em sua mão' => ['hab' => [
                ['g' => 'dano_jog', 'passos' => [['a' => 'moer', 'n' => '#valor', 'alvo' => 'evento']]],
                ['g' => 'inicio', 'passos' => [['a' => 'dano', 'n' => '#mao', 'alvo' => 'inimigos', 'filtro' => null]]]]],
            'Toda vez que uma lenda for invocada, recupere 1$. Sempre que você recuperar vida, o jogador alvo mill uma carta' => ['hab' => [
                ['g' => 'entra', 'passos' => [['a' => 'ganhar', 'n' => 1, 'alvo' => 'eu']]],
                ['g' => 'ganha_meu', 'passos' => [['a' => 'moer', 'n' => 1, 'alvo' => 'jogador', 'filtro' => 'inimigo']]]]],
            'Essa carta pode ser seu comandante, mesmo sendo um encantamento. Toda vez que um jogador Mill, ele mila o dobro de cartas. Sempre que um jogador Mill, uma vez por turno, compre uma carta' => ['cmd' => true,
                'est' => [['g' => 'mill_dobro']],
                'hab' => [['g' => 'mill', 'uma' => true, 'passos' => [['a' => 'comprar', 'n' => 1, 'alvo' => 'eu']]]]],
        ];
    }
}
