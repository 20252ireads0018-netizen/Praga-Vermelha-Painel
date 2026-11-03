<?php

namespace Tests\Unit;

use App\Jogos\Gladio;
use App\Jogos\GladioTexto;
use PHPUnit\Framework\TestCase;

/** Cartas do lote 2 (ids 156–208 de produção): cada uma aplica o próprio texto. */
class GladioCartasNovasTest extends TestCase
{
    use GladioVidaDaAposta;

    private int $seq = 7000;

    private const BASE = [
        'x'    => ['nome' => 'Soldado', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1, 'palavras' => '', 'descricao' => ''],
        'tiro' => ['nome' => 'Tiro', 'tipo' => 'feitico', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'palavras' => '', 'descricao' => 'Cause 3 de dano a um alvo'],
    ];

    private function c(string $nome, string $tipo, string $texto, array $extra = []): array
    {
        return $extra + ['nome' => $nome, 'tipo' => $tipo, 'custo' => 1, 'ataque' => 2, 'vida' => 2, 'palavras' => '', 'descricao' => $texto];
    }

    private function mesa(array $cartas): array
    {
        $deck = array_fill(0, 20, 'x');
        $e = $this->iniciar(self::BASE + $cartas, 99, ['a' => $deck, 'b' => $deck], fn ($a) => $a, [], ['a' => 'x', 'b' => 'x']);
        foreach (['a', 'b'] as $l) {
            $e['lados'][$l]['mao'] = [];
            $e['lados'][$l]['energia'] = 20;
        }

        return $e;
    }

    private function joga(array $e, string $lado, string $c, mixed $alvo = null): array
    {
        $e['lados'][$lado]['mao'][] = ['i' => ++$this->seq, 'c' => $c];
        $vez = $e['vez'];
        $e['vez'] = $lado;
        $e = Gladio::jogar($e, $lado, $this->seq);
        if ($e['vez'] === $lado) {
            $e['vez'] = $vez;
        }

        return $e;
    }

    private function poe(array $e, string $lado, string $c): array
    {
        $e = $this->joga($e, $lado, $c);
        $k = array_key_last($e['lados'][$lado]['mesa']);
        $e['lados'][$lado]['mesa'][$k]['pode'] = true;

        return [$e, (int) $e['lados'][$lado]['mesa'][$k]['i']];
    }

    public function test_cartas_antigas_que_mudaram_e_sem_roteiro_o_leitor_entende(): void
    {
        foreach (['Quando morrer, recupere 5$', 'Pague 5$, cause 5 de dano em uma lenda alvo.', 'Devolva a lenda alvo para a mão de seu dono'] as $t) {
            $r = GladioTexto::ler($t, 'lenda');
            $this->assertNotEmpty($r['hab'], $t);
            $this->assertFalse($r['parcial'], $t);
        }
    }

    public function test_feridas_duradouras_e_reverter(): void
    {
        $feridas = $this->c('Feridas duradouras', 'encanto', 'Todo ganho de dinheiro é reduzido pela metade. Se houverem duas cópias dessa carta no campo, torna-se impossível ganhar dinheiro.');
        $cabrito = $this->c('Cabrito', 'feitico', 'Recupere 10$');
        $e = $this->mesa(['feridas' => $feridas, 'cura' => $cabrito,
            'rev' => $this->c('Reverter intoxicar feridas duradouras', 'encanto', 'Toda vez que um jogador fosse ganhar dinheiro, ao invés disso, ele a perde')]);
        $e = $this->joga($e, 'a', 'feridas');
        $e = $this->joga($e, 'a', 'cura');
        $this->assertSame(104, $e['lados']['a']['vida'], 'metade de 10');
        $e = $this->joga($e, 'a', 'feridas');
        $e = $this->joga($e, 'a', 'cura');
        $this->assertSame(104, $e['lados']['a']['vida'], 'duas cópias: nada');
        $e = $this->joga($e, 'a', 'rev');
        $e = $this->joga($e, 'a', 'cura');
        $this->assertSame(94, $e['lados']['a']['vida'], 'reverter: ganhar vira perder');
    }

    public function test_quadro_da_fome_e_basileu(): void
    {
        $e = $this->mesa(['quadro' => $this->c('Quadro da fome', 'encanto', 'Todo jogador, incluindo você, só pode conjurar uma carta por turno')]);
        $e = $this->joga($e, 'a', 'quadro');
        try {
            $this->joga($e, 'a', 'tiro');
            $this->fail('segunda carta no turno');
        } catch (\InvalidArgumentException $x) {
            $this->assertStringContainsString('uma carta por turno', $x->getMessage());
        }
        $e = $this->mesa(['bas' => $this->c('O Basileu do Principado', 'lenda', 'Regenerar 1. Toda primeira carta conjurada por  um jogador é negada.')]);
        [$e] = $this->poe($e, 'b', 'bas');
        $e = $this->joga($e, 'a', 'tiro');
        $this->assertNull($e['escolha'], 'a primeira foi negada');
        $e = $this->joga($e, 'a', 'tiro');
        $this->assertSame('qualquer', $e['escolha']['tipo'], 'a segunda acontece');
    }

    public function test_tranca_painel_e_marechal_negam(): void
    {
        $tranca = $this->c('Tranca amaldiçoada', 'feitico', 'Negue a conjuração da carta alvo respondida.', ['subtipos' => 'forma hexomante']);
        $painel = $this->c('Painel de Kuros', 'encanto', 'Todo início de turno, pague 3 ou perca essa carta. Sempre que um jogador conjurar uma carta, você pode pagar 2 para negá-la');
        $marechal = $this->c('Marechal Fonsceca', 'lenda', 'Toda vez que uma lenda de custo 3 ou menos for invocada, você pode pagar 10$ para negar a invocação');
        $e = $this->mesa(['tranca' => $tranca, 'painel' => $painel, 'marechal' => $marechal]);
        $e['lados']['b']['mao'][] = ['i' => 9001, 'c' => 'tranca'];
        $e = $this->joga($e, 'a', 'tiro');
        $this->assertSame(['b', 'resposta', [9001]], [$e['escolha']['lado'], $e['escolha']['tipo'], $e['escolha']['opcoes']]);
        $e = Gladio::escolher($e, 'b', 9001);
        $this->assertNull($e['escolha'], 'Tiro negado');
        // Painel de Kuros (no campo de b) paga 2 de energia
        $e = $this->joga($e, 'b', 'painel');
        $e = $this->joga($e, 'a', 'tiro');
        $op = array_values(array_filter($e['escolha']['opcoes'], 'is_string'))[0];
        $energia = $e['lados']['b']['energia'];
        $e = Gladio::escolher($e, 'b', $op);
        $this->assertSame([$energia - 2, null], [$e['lados']['b']['energia'], $e['escolha']]);
        // Marechal Fonsceca: paga 10$ e nega a Lenda de custo até 3
        [$e] = $this->poe($e, 'b', 'marechal');
        $e = $this->joga($e, 'a', 'x');
        $op = array_values(array_filter($e['escolha']['opcoes'], fn ($o) => is_string($o) && str_starts_with($o, 'campo:')))[0];
        $e = Gladio::escolher($e, 'b', $op);
        $this->assertSame([[], 89], [array_column($e['lados']['a']['mesa'], 'c'), $e['lados']['b']['vida']]);
    }

    public function test_corrente_de_negacoes(): void
    {
        $cartas = [
            'vergonha' => $this->c('Vergonha de Dante', 'feitico', 'Negue a invocação da carta alvo'),
            'tranca'   => $this->c('Tranca amaldiçoada', 'feitico', 'Negue a conjuração da carta alvo respondida.'),
        ];
        // a invoca, b nega com Vergonha, a nega a Vergonha com Tranca, b passa: a Lenda entra
        $e = $this->mesa($cartas);
        $e['lados']['b']['mao'][] = ['i' => 9301, 'c' => 'vergonha'];
        $e['lados']['a']['mao'][] = ['i' => 9302, 'c' => 'tranca'];
        $e = $this->joga($e, 'a', 'x');
        $this->assertSame(['b', [9301]], [$e['escolha']['lado'], $e['escolha']['opcoes']]);
        $e = Gladio::escolher($e, 'b', 9301);
        $this->assertSame(['a', 'resposta', [9302]], [$e['escolha']['lado'], $e['escolha']['tipo'], $e['escolha']['opcoes']], 'a pode negar a negação');
        $this->assertSame(['x'], array_column($e['lados']['a']['mesa'], 'c'), 'a Lenda espera a corrente resolver');
        $e = Gladio::escolher($e, 'a', 9302);
        $this->assertNull($e['escolha']);
        $this->assertSame(['x'], array_column($e['lados']['a']['mesa'], 'c'), 'a Vergonha foi negada: a Lenda entrou');

        // b ainda nega a Tranca com outra Tranca: a Vergonha volta a valer e a Lenda é negada
        $e = $this->mesa($cartas);
        $e['lados']['b']['mao'] = [['i' => 9311, 'c' => 'vergonha'], ['i' => 9313, 'c' => 'tranca']];
        $e['lados']['a']['mao'][] = ['i' => 9312, 'c' => 'tranca'];
        $e = $this->joga($e, 'a', 'x');
        $e = Gladio::escolher($e, 'b', 9311);
        $e = Gladio::escolher($e, 'a', 9312);
        $this->assertSame(['b', [9313]], [$e['escolha']['lado'], $e['escolha']['opcoes']]);
        $e = Gladio::escolher($e, 'b', 9313);
        $this->assertNull($e['escolha']);
        $this->assertSame([], $e['lados']['a']['mesa'], 'Tranca de b negou a Tranca de a: a Vergonha negou a Lenda');
        $this->assertContains('x', array_column($e['lados']['a']['cemiterio'], 'c'));

        // ninguém nega a negação: ela acontece como antes
        $e = $this->mesa($cartas);
        $e['lados']['b']['mao'][] = ['i' => 9321, 'c' => 'vergonha'];
        $e = $this->joga($e, 'a', 'x');
        $e = Gladio::escolher($e, 'b', 9321);
        $this->assertSame([null, []], [$e['escolha'], $e['lados']['a']['mesa']]);
    }

    public function test_vassago_protege_do_selo_do_anticristo(): void
    {
        $e = $this->mesa([
            'vas'  => $this->c('Vassago, O primeiro ascensionista', 'lenda', 'Seus encantamentos não podem sair de campo enquanto Vassago estiver vivo.'),
            'enc'  => $this->c('Aura', 'encanto', ''),
            'eq'   => $this->c('Espada', 'equipo', ''),
            'selo' => $this->c('Selo do anticristo', 'feitico', 'Destrua um encantamento ou equipamento alvo'),
        ]);
        $e = $this->joga($e, 'a', 'enc');
        $e = $this->joga($e, 'a', 'eq');
        [$e] = $this->poe($e, 'a', 'vas');
        [$enc, $eq] = array_column($e['lados']['a']['feiticos'], 'i');
        $e = $this->joga($e, 'b', 'selo');
        $this->assertSame([(string) $eq], array_map('strval', $e['escolha']['opcoes']), 'o encantamento protegido nem aparece');
    }

    public function test_barill_sacrifica_obrigatoriamente_e_compra_duas(): void
    {
        $cartas = ['barill' => $this->c('Barill', 'lenda', 'Quando invocado, sacrifique uma lenda, compre duas cartas', ['ataque' => 1, 'vida' => 4])];
        // com outra Lenda: tem de escolher uma delas (não dá para pular nem escolher o próprio Barill)
        $e = $this->mesa($cartas);
        [$e, $soldado] = $this->poe($e, 'a', 'x');
        $mao = count($e['lados']['a']['mao']);
        $e = $this->joga($e, 'a', 'barill');
        $this->assertTrue($e['escolha']['obrig']);
        $this->assertSame([$soldado], $e['escolha']['opcoes']);
        try {
            Gladio::escolher($e, 'a', null);
            $this->fail('o sacrifício não pode ser pulado');
        } catch (\InvalidArgumentException $x) {
            $this->assertStringContainsString('não dá para pular', $x->getMessage());
        }
        $e = Gladio::escolher($e, 'a', $soldado);
        $this->assertSame(['Barill'], array_map(fn ($m) => $e['cartas'][$m['c']]['nome'], $e['lados']['a']['mesa']));
        $this->assertCount($mao + 2, $e['lados']['a']['mao']);
        // sozinho na mesa: ele mesmo é sacrificado e as duas são compradas
        $e = $this->mesa($cartas);
        $e = $this->joga($e, 'a', 'barill');
        $this->assertSame([$this->seq], $e['escolha']['opcoes']);
        $e = Gladio::escolher($e, 'a', $this->seq);
        $this->assertSame([], $e['lados']['a']['mesa']);
        $this->assertCount(2, $e['lados']['a']['mao']);
    }

    public function test_custos_de_descarte_sao_obrigatorios_e_condicionam_o_efeito(): void
    {
        $cartas = [
            'isaque' => $this->c('Isaque Solomons', 'lenda', 'Descarte uma carta de sua mão, mate uma lenda.'),
            'assas'  => $this->c('Assassina Hexomante', 'lenda', 'Se essa lenda atacar um jogador diretamente, ele descarta duas cartas'),
        ];
        // sem carta na mão: não descarta e não mata
        $e = $this->mesa($cartas);
        [$e, $alvo] = $this->poe($e, 'b', 'x');
        $e = $this->joga($e, 'a', 'isaque');
        $this->assertNull($e['escolha']);
        $this->assertCount(1, $e['lados']['b']['mesa'], 'sem descarte, ninguém morre');
        // com carta: o descarte não pode ser pulado e depois vem a morte
        $e = $this->mesa($cartas);
        [$e, $alvo] = $this->poe($e, 'b', 'x');
        $e['lados']['a']['mao'][] = ['i' => 9300, 'c' => 'x'];
        $e = $this->joga($e, 'a', 'isaque');
        $this->assertTrue($e['escolha']['obrig']);
        $e = Gladio::escolher($e, 'a', 9300);
        $this->assertContains($alvo, $e['escolha']['opcoes']);
        $e = Gladio::escolher($e, 'a', $alvo);
        $this->assertSame([], $e['lados']['b']['mesa']);
        // o adversário atacado pela Assassina não pode recusar o descarte
        $e = $this->mesa($cartas);
        [$e, $assas] = $this->poe($e, 'a', 'assas');
        $e['lados']['b']['mao'] = [['i' => 9301, 'c' => 'x'], ['i' => 9302, 'c' => 'x'], ['i' => 9303, 'c' => 'x']];
        $e = Gladio::defender(Gladio::atacar($e, 'a', [$assas], 'b'), 'b');
        $this->assertSame(['b', true], [$e['escolha']['lado'], $e['escolha']['obrig']]);
    }

    public function test_balinha_prisao_e_josael(): void
    {
        $e = $this->mesa([
            'balinha' => $this->c('Balinha', 'lenda', 'Sempre que Balinha entrar, atacar ou morrer, cause 2 de dano a duas lendas alvo'),
            'prisao'  => $this->c('Prisão', 'feitico', 'Exile a carta alvo'),
            'enc'     => $this->c('Confiança', 'encanto', 'Mantido. Todo início de turno, suas lendas ganham +0/+2'),
            'josael'  => $this->c('Josael', 'lenda', 'Quando um jogador recuperar dinheiro, recupere metade daquele valor. Quando um jogador perder ao menos 10$, compre uma carta.'),
            'cura'    => $this->c('Cura', 'feitico', 'Recupere 10$'),
        ]);
        // Balinha: o segundo dano é em outra Lenda
        [$f, $l1] = $this->poe($e, 'b', 'x');
        [$f, $l2] = $this->poe($f, 'b', 'x');
        $f = $this->joga($f, 'a', 'balinha');
        $f = Gladio::escolher($f, 'a', $l1);
        $this->assertNotContains($l1, $f['escolha']['opcoes']);
        $this->assertContains($l2, $f['escolha']['opcoes']);
        // Prisão: também exila um encantamento (não vai para o cemitério)
        $f = $this->joga($e, 'b', 'enc');
        $enc = (int) $f['lados']['b']['feiticos'][0]['i'];
        $f = $this->joga($f, 'a', 'prisao');
        $f = Gladio::escolher($f, 'a', '1');
        $f = Gladio::escolher($f, 'a', (string) $enc);
        $this->assertSame([], $f['lados']['b']['feiticos']);
        $this->assertNotContains($enc, array_map(fn ($c) => (int) $c['i'], $f['lados']['b']['cemiterio']));
        // Josael não reage à própria cura: 10 de cura dá 5 a mais, não 5 + 2 + 1
        [$f] = $this->poe($e, 'a', 'josael');
        $f['lados']['a']['vida'] = 10;
        $f['lados']['a']['max'] = 80;
        $f = $this->joga($f, 'a', 'cura');
        $this->assertSame(25, $f['lados']['a']['vida']);
    }

    public function test_arqueiro_tem_iniciativa_e_luzinha_rouba_ao_comprar(): void
    {
        $e = $this->mesa([
            'arq' => $this->c('Arqueiro Hexocida', 'lenda', 'Esta lenda sempre ataca primeiro. Quando ela mata uma Lenda e sai vivo, recupere 15$.', ['ataque' => 5, 'vida' => 1]),
            'luz' => $this->c('Luzinha', 'lenda', 'Toda vez que uma carta for comprada ou descartada por você, roube 1$', ['ataque' => 0, 'vida' => 1]),
            'compra' => $this->c('Leitura', 'feitico', 'Compre uma carta'),
        ]);
        [$e] = $this->poe($e, 'a', 'arq');
        $this->assertContains('iniciativa', $e['lados']['a']['mesa'][0]['pal']);
        [$e] = $this->poe($e, 'a', 'luz');
        $antes = [$e['lados']['a']['vida'], $e['lados']['b']['vida']];
        $e = $this->joga($e, 'a', 'compra');
        if ($e['escolha']) { // de quem roubar
            $e = Gladio::escolher($e, 'a', 'j:b');
        }
        $this->assertSame([$antes[0] + 1, $antes[1] - 1], [$e['lados']['a']['vida'], $e['lados']['b']['vida']]);
    }

    public function test_fisiomancia_animal_aplica_qualquer_propriedade_nas_fichas(): void
    {
        $prop = ['subtipos' => 'propriedade fisiomântica'];
        $e = $this->mesa([
            'animal' => $this->c('Fisiomancia animal', 'feitico', 'Invoque três fichas de animal 1/1. Busque no seu baralho por uma propriedade fisiomantica, ela afeta as três fichas.'),
            'forte'  => $this->c('Propriedade: Forte', 'feitico', 'A lenda alvo ganha +4/+4 ou sobrepujar.', $prop),
            'frio'   => $this->c('Propriedade: Frio', 'equipo', 'A Lenda alvo não pode mais atacar', $prop),
            'constr' => $this->c('Propriedade: Constrição', 'encanto', 'Toda vez que o jogador alvo invocar uma ficha, ele perde 3$', $prop),
        ]);
        $fichas = fn ($e) => array_values(array_filter($e['lados']['a']['mesa'], fn ($m) => ($m['nome'] ?? '') === 'Animal'));
        $com = function (string $p) use ($e) {
            $e['lados']['a']['baralho'][] = ['i' => 9500, 'c' => $p];
            $e = $this->joga($e, 'a', 'animal');
            $this->assertSame('baralho', $e['escolha']['tipo']);

            return Gladio::escolher($e, 'a', 9500);
        };

        // Forte (um feitiço): cada ficha escolhe +4/+4 ou Sobrepujar
        $e2 = $com('forte');
        foreach (['0', '1', '0'] as $op) {
            $this->assertSame('opcao', $e2['escolha']['tipo']);
            $e2 = Gladio::escolher($e2, 'a', $op);
        }
        $this->assertSame([[5, 5], [1, 1], [5, 5]], array_map(fn ($m) => [$m['atk'], $m['vida']], $fichas($e2)));
        $this->assertContains('sobrepujar', $fichas($e2)[1]['pal']);
        // Frio (equipamento com marca): as três não atacam
        $e2 = $com('frio');
        $this->assertCount(3, $fichas($e2));
        foreach ($fichas($e2) as $m) {
            $this->assertContains('preso_eq', $m['fl']);
        }
        // Constrição não mira Lendas: avisa e vai para o cemitério
        $e2 = $com('constr');
        $this->assertNull($e2['escolha']);
        $this->assertStringContainsString('não tem efeito em Lendas', end($e2['log'])['t'] ?? (string) end($e2['log']));
    }

    public function test_hexo_imune_so_contra_feiticos_encantamentos_e_equipamentos_inimigos(): void
    {
        $e = $this->mesa([
            'hexo'     => $this->c('Guarda', 'lenda', '', ['palavras' => 'resistencia', 'vida' => 9]),
            'chuva'    => $this->c('Chuva de pedras', 'feitico', 'Cause 2 de dano a todas as lendas'),
            'atirador' => $this->c('Atirador', 'lenda', 'Quando entrar, cause 1 de dano a uma lenda alvo'),
        ]);
        [$e, $guarda] = $this->poe($e, 'b', 'hexo');
        [$e, $soldado] = $this->poe($e, 'b', 'x');
        $vida = fn ($e, $i) => array_values(array_filter($e['lados']['b']['mesa'], fn ($m) => (int) $m['i'] === $i))[0]['vida'] ?? null;

        // feitiço inimigo com alvo: a Guarda nem aparece
        $f = $this->joga($e, 'a', 'tiro');
        $this->assertNotContains($guarda, $f['escolha']['opcoes']);
        // feitiço inimigo em área: a Guarda não sofre, o Soldado cai
        $f = $this->joga($e, 'a', 'chuva');
        $this->assertSame(9, $vida($f, $guarda));
        $this->assertNull($vida($f, $soldado));
        // efeito de Lenda inimiga: a Guarda pode ser escolhida e sofre
        $f = $this->joga($e, 'a', 'atirador');
        $this->assertContains($guarda, $f['escolha']['opcoes']);
        $f = Gladio::escolher($f, 'a', $guarda);
        $this->assertSame(8, $vida($f, $guarda));
        // o próprio dono mexe livremente: o feitiço dele mira e a área dele atinge a Guarda
        $f = $this->joga($e, 'b', 'tiro');
        $this->assertContains($guarda, $f['escolha']['opcoes']);
        $f = $this->joga($e, 'b', 'chuva');
        $this->assertSame(7, $vida($f, $guarda));
    }

    public function test_imbuir_deixa_escolher_as_proprias_cartas(): void
    {
        $e = $this->mesa([
            'imbuir' => $this->c('Imbuir', 'feitico', 'Troque o alvo de um efeito ou ataque ocorrendo', ['subtipos' => 'forma hexomante']),
            'hexo'   => $this->c('Guarda', 'lenda', '', ['palavras' => 'resistencia']),
        ]);
        [$e, $minha] = $this->poe($e, 'a', 'x');
        [$e, $guarda] = $this->poe($e, 'b', 'hexo'); // Hexo-Imune: o Tiro de a não pode mirar nela
        [$e, $outra] = $this->poe($e, 'b', 'x');
        $e['lados']['b']['mao'][] = ['i' => 9200, 'c' => 'imbuir'];
        $e = $this->joga($e, 'a', 'tiro');
        $this->assertNotContains($guarda, $e['escolha']['opcoes']);
        $e = Gladio::escolher($e, 'a', $minha);
        $e = Gladio::escolher($e, 'b', 9200);
        $this->assertSame('b', $e['escolha']['lado']);
        $this->assertContains($guarda, $e['escolha']['opcoes'], 'quem usa o Imbuir pode escolher as próprias Lendas');
        $this->assertContains($outra, $e['escolha']['opcoes']);
        $e = Gladio::escolher($e, 'b', $guarda);
        $this->assertNotNull(array_values(array_filter($e['lados']['a']['mesa'], fn ($m) => (int) $m['i'] === $minha))[0] ?? null, 'a Lenda de a foi poupada');
    }

    public function test_intoxicar_no_proprio_feitico(): void
    {
        $e = $this->mesa(['intox' => $this->c('Intoxicar', 'feitico', 'Responda ao feitiço alvo, ele tem como alvo todas as cartas do campo ao invés do comum.', ['subtipos' => 'forma hexomante'])]);
        [$e, $minha] = $this->poe($e, 'a', 'x');
        [$e, $d1] = $this->poe($e, 'b', 'x');
        [$e, $d2] = $this->poe($e, 'b', 'x');
        $e['lados']['a']['mao'][] = ['i' => 9100, 'c' => 'intox'];
        $e = $this->joga($e, 'a', 'tiro');
        $this->assertSame(['a', 'resposta', [9100]], [$e['escolha']['lado'], $e['escolha']['tipo'], $e['escolha']['opcoes']], 'quem conjurou pode Intoxicar');
        $e = Gladio::escolher($e, 'a', 9100);
        $this->assertSame([[], []], [$e['lados']['a']['mesa'], $e['lados']['b']['mesa']], 'o Tiro atingiu todas as Lendas');
    }

    public function test_calamidade_clonagem_controlado_e_homem_sino(): void
    {
        $e = $this->mesa([
            'cal'   => $this->c('Calamidade', 'feitico', 'destrua todas as cartas em campo, todo jogador embaralha a própria mão no próprio deck e compra 5 cartas.'),
            'clone' => $this->c('Clonagem', 'feitico', 'Clone a Lenda alvo para o seu lado do campo'),
            'ctrl'  => $this->c('Propriedade: Controlado', 'equipo', 'Tome controle da lenda alvo', ['ataque' => 0, 'vida' => 0]),
            'sino'  => $this->c('Homem sino criança', 'lenda', 'Todo jogador compra duas cartas ao invés de uma na própria fase de compras.'),
        ]);
        [$e, $dele] = $this->poe($e, 'b', 'x');
        $e = $this->joga($e, 'a', 'clone');
        $e = Gladio::escolher($e, 'a', $dele);
        $this->assertSame('x', $e['lados']['a']['mesa'][0]['c']);
        $e = $this->joga($e, 'a', 'ctrl');
        $e = Gladio::escolher($e, 'a', $dele);
        $this->assertCount(2, $e['lados']['a']['mesa'], 'tomou o controle');
        $this->assertCount(0, $e['lados']['b']['mesa']);
        $e = $this->joga($e, 'a', 'cal');
        $this->assertSame([[], [], 5, 5], [$e['lados']['a']['mesa'], $e['lados']['b']['mesa'], count($e['lados']['a']['mao']), count($e['lados']['b']['mao'])]);
        [$e] = $this->poe($e, 'a', 'sino');
        $mao = count($e['lados']['b']['mao']);
        $e = Gladio::passar($e, 'a');
        $this->assertSame($mao + 2, count($e['lados']['b']['mao']));
    }

    public function test_vitorias_de_vassago_e_lendas_de_vassago(): void
    {
        $lendas = $this->c('Lendas de Vassago', 'encanto', 'Quando você juntar em campo 4 cartas com este mesmo nome, ganhe o jogo.');
        $semTexto = $this->c('Lendas de vassago', 'encanto', '');
        $e = $this->mesa(['lv' => $lendas, 'lv2' => $semTexto]);
        foreach (['lv', 'lv2', 'lv2'] as $c) {
            $e = $this->joga($e, 'a', $c);
        }
        $this->assertNull($e['fim']);
        $e = $this->joga($e, 'a', 'lv2');
        $this->assertSame('a', $e['fim']['vencedor']);

    }

    public function test_vassago_segura_os_encantamentos_em_campo(): void
    {
        $e = $this->mesa([
            'vas' => $this->c('Vassago, O primeiro ascensionista', 'lenda', 'Seus encantamentos não podem sair de campo enquanto Vassago estiver vivo.'),
            'enc' => $this->c('Aura', 'encanto', ''),
            'eq'  => $this->c('Espada', 'equipo', ''),
        ]);
        $e = $this->joga($e, 'a', 'enc');
        $e = $this->joga($e, 'a', 'eq');
        [$e, $vas] = $this->poe($e, 'a', 'vas');
        [$enc, $eq] = array_column($e['lados']['a']['feiticos'], 'i');
        try {
            Gladio::encerrarFeitico($e, 'a', $enc);
            $this->fail('o encantamento não sai enquanto Vassago vive');
        } catch (\InvalidArgumentException $x) {
            $this->assertStringContainsString('Vassago', $x->getMessage());
        }
        $sem = Gladio::encerrarFeitico($e, 'a', $eq); // equipamento não é encantamento
        $this->assertSame([$enc], array_column($sem['lados']['a']['feiticos'], 'i'));
        // Vassago fora da mesa: o encantamento já pode sair
        $e['lados']['a']['mesa'] = array_values(array_filter($e['lados']['a']['mesa'], fn ($m) => $m['i'] !== $vas));
        $e = Gladio::encerrarFeitico($e, 'a', $enc);
        $this->assertSame([$eq], array_column($e['lados']['a']['feiticos'], 'i'));
    }

    public function test_kaique_moeda_lucas_quebra_e_amir_tranqueiras(): void
    {
        Gladio::$moeda = fn () => false; // a moeda sempre dá coroa errada
        try {
            $e = $this->mesa([
                'kai'   => $this->c('Kaique', 'lenda', 'Toda vez que um jogador fosse invocar uma ficha, gire uma moeda, ele só invoca se sair cara.'),
                'masc'  => $this->c('Máscara', 'feitico', 'Crie duas fichas de constructo 2/2'),
            ]);
            [$e] = $this->poe($e, 'b', 'kai');
            $e = $this->joga($e, 'a', 'masc');
            $this->assertSame([], $e['lados']['a']['mesa'], 'nenhuma ficha entrou');
        } finally {
            Gladio::$moeda = null;
        }
        $e = $this->mesa([
            'lucas' => $this->c('Lucas', 'lenda', 'Lucas pode destruir cartas indestrutíveis em combate.', ['ataque' => 5, 'vida' => 5]),
            'muro'  => $this->c('Muro', 'lenda', '', ['palavras' => 'indestrutivel', 'ataque' => 0, 'vida' => 2]),
            'amir'  => $this->c('Amir', 'lenda', 'Todo início de turno, invoque uma ficha de tranqueira 0/1. Toda carta com nome Amir ganha +1/+1 para cada ficha de tranqueira em campo'),
        ]);
        [$e, $lucas] = $this->poe($e, 'a', 'lucas');
        [$e, $muro] = $this->poe($e, 'b', 'muro');
        $e = Gladio::atacar($e, 'a', [$lucas], 'b');
        $e = Gladio::bloquear($e, 'b', $muro, $lucas);
        $e = Gladio::defender($e, 'b');
        $this->assertSame([], $e['lados']['b']['mesa'], 'o indestrutível caiu');
        [$e, $amir] = $this->poe($e, 'a', 'amir');
        $e = Gladio::passar($e, 'a');
        $e = Gladio::passar($e, 'b');
        $m = array_values(array_filter($e['lados']['a']['mesa'], fn ($x) => (int) $x['i'] === $amir))[0];
        $this->assertSame([3, 3], [$m['atk'], $m['max']], '+1/+1 pela tranqueira');
    }

    public function test_amir_novo_ganha_3_3_e_uma_palavra_por_tranqueira(): void
    {
        $e = $this->mesa(['amir' => $this->c('Amir', 'lenda', 'Todo início de turno, invoque uma ficha de tranqueira 0/1. Toda carta com nome Amir ganha +3/+3 e uma palavra chave para cada ficha de tranqueira em campo', ['ataque' => 1, 'vida' => 2])]);
        [$e, $amir] = $this->poe($e, 'a', 'amir');
        $e = Gladio::passar($e, 'a');
        $e = Gladio::passar($e, 'b'); // início do turno de a: entra uma tranqueira e o dono escolhe a palavra
        $this->assertSame(['a', 'palavra'], [$e['escolha']['lado'], $e['escolha']['tipo']]);
        $e = Gladio::escolher($e, 'a', 'voar');
        $m = array_values(array_filter($e['lados']['a']['mesa'], fn ($x) => (int) $x['i'] === $amir))[0];
        $this->assertSame([4, 5], [$m['atk'], $m['max']], '+3/+3 pela tranqueira');
        $this->assertContains('voar', $m['pal']);
        // a tranqueira sai: o bônus e a palavra vão embora
        $e['lados']['a']['mesa'] = array_values(array_filter($e['lados']['a']['mesa'], fn ($x) => (int) $x['i'] === $amir));
        $e = Gladio::passar($e, 'a');
        $m = array_values(array_filter($e['lados']['a']['mesa'], fn ($x) => (int) $x['i'] === $amir))[0];
        $this->assertSame(1, $m['atk']);
        $this->assertNotContains('voar', $m['pal']);
    }

    public function test_fantasma_dobra_o_mill_e_pode_ser_comandante(): void
    {
        $fantasma = $this->c('Fantasma das paredes', 'encanto', 'Essa carta pode ser seu comandante, mesmo sendo um encantamento. Toda vez que um jogador Mill, ele mila o dobro de cartas. Sempre que um jogador Mill, uma vez por turno, compre uma carta.');
        $this->assertTrue(Gladio::podeComandar($fantasma));
        $e = $this->mesa(['fan' => $fantasma, 'mill' => $this->c('Moer', 'feitico', 'Mill 2')]);
        $e = $this->joga($e, 'a', 'fan');
        $b = count($e['lados']['a']['baralho']);
        $e = $this->joga($e, 'a', 'mill');
        $this->assertSame($b - 4 - 1, count($e['lados']['a']['baralho']), 'mila 4 e compra 1');
        $this->assertCount(1, $e['lados']['a']['mao']);
    }
}
