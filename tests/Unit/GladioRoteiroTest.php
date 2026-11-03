<?php

namespace Tests\Unit;

use App\Jogos\Gladio;
use PHPUnit\Framework\TestCase;

/** As cartas de produção com roteiro (GladioRoteiro): cada uma aplica no tabuleiro o que o texto diz. */
class GladioRoteiroTest extends TestCase
{
    use GladioVidaDaAposta;

    private int $seq = 5000;

    private const BASE = [
        'x'    => ['nome' => 'Soldado', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 1, 'vida' => 1, 'palavras' => '', 'descricao' => ''],
        'tiro' => ['nome' => 'Tiro', 'tipo' => 'feitico', 'custo' => 2, 'ataque' => 0, 'vida' => 0, 'palavras' => '', 'descricao' => 'Cause 3 de dano a um alvo'],
    ];

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

    private function naMao(array &$e, string $lado, string $c): int
    {
        $e['lados'][$lado]['mao'][] = ['i' => ++$this->seq, 'c' => $c];

        return $this->seq;
    }

    /** Joga uma Lenda (pagando) e deixa pronta para atacar. */
    private function poe(array $e, string $lado, string $c): array
    {
        $vez = $e['vez'];
        $e['vez'] = $lado;
        $i = $this->naMao($e, $lado, $c);
        $e = Gladio::jogar($e, $lado, $i);
        $e['vez'] = $vez;
        $k = array_search($i, array_column($e['lados'][$lado]['mesa'], 'i'), true);
        if ($k !== false) {
            $e['lados'][$lado]['mesa'][$k]['pode'] = true;
        }

        return [$e, $i];
    }

    /** Põe a carta na mão de $lado e joga (pagando). */
    private function joga(array $e, string $lado, string $c): array
    {
        $i = $this->naMao($e, $lado, $c);

        return Gladio::jogar($e, $lado, $i);
    }

    private function m(array $e, string $lado, int $i): ?array
    {
        foreach ($e['lados'][$lado]['mesa'] as $m) {
            if ((int) $m['i'] === $i) {
                return $m;
            }
        }

        return null;
    }

    private function noCemiterio(array $e, string $lado, int $i): bool
    {
        return in_array($i, array_map('intval', array_column($e['lados'][$lado]['cemiterio'], 'i')), true);
    }

    public function test_mulligan_troca_a_mao_e_poe_cartas_no_fundo_antes_de_comecar(): void
    {
        $deck = array_merge(array_fill(0, 10, 'x'), array_fill(0, 10, 'tiro'));
        $e = Gladio::abrirMulligan($this->iniciar(self::BASE, 50, ['a' => $deck, 'b' => $deck], fn ($a) => $a, [], ['a' => 'x', 'b' => 'x']));
        $this->assertSame('mulligan', $e['fase']);
        try {
            Gladio::passar($e, 'a');
            $this->fail('ninguém joga durante o mulligan');
        } catch (\InvalidArgumentException) {
        }
        $e = Gladio::mulligan($e, 'a', fn ($b) => array_reverse($b));
        $this->assertCount(5, $e['lados']['a']['mao']);
        try {
            Gladio::manter($e, 'a');
            $this->fail('precisa escolher 1 carta para o fundo');
        } catch (\InvalidArgumentException) {
        }
        $fundo = $e['lados']['a']['mao'][0]['i'];
        $e = Gladio::manter($e, 'a', [$fundo]);
        $this->assertSame([4, $fundo, 'mulligan'], [count($e['lados']['a']['mao']), end($e['lados']['a']['baralho'])['i'], $e['fase']]);
        $this->assertSame('b', Gladio::esperando($e));
        $e = Gladio::manter($e, 'b');
        $this->assertSame(['principal', 'a', 19], [$e['fase'], $e['vez'], count($e['lados']['a']['baralho']) + count($e['lados']['a']['mao']) + 0]);
    }

    public function test_banlist_no_deck_sorteado_e_na_lista_do_deck(): void
    {
        $cartas = self::BASE + ['aguinha' => ['nome' => 'Aguinha', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1, 'palavras' => '', 'descricao' => '', 'limite' => 1]];
        $cartas['tiro']['limite'] = 0;
        $e = $this->iniciar($cartas, 50, ['a' => null, 'b' => null], fn ($a) => $a);
        $todas = array_merge(...array_values(array_map(fn ($l) => array_column(array_merge($l["baralho"], $l["mao"], $l["comando"]), "c"), $e["lados"])));
        $this->assertNotContains('tiro', $todas, 'banida não entra no deck sorteado');
        $this->assertSame(1, count(array_filter(array_column(array_merge($e['lados']['a']['baralho'], $e['lados']['a']['mao'], $e['lados']['a']['comando']), 'c'), fn ($c) => $c === 'aguinha')));
        $deck = new \App\Models\GladioDeck(['cartas' => ['aguinha' => 3, 'x' => 2, 'tiro' => 2]]);
        $this->assertSame(['aguinha', 'x', 'x'], $deck->lista($cartas), 'deck antigo: limitada fica em 1 e banida sai');
    }

    public function test_no_maximo_100_lendas_na_mesa_de_cada_jogador(): void
    {
        $e = $this->mesa(['zezin' => ['nome' => 'Zezin', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 1, 'vida' => 1, 'palavras' => '',
            'descricao' => 'Invoque uma ficha de mão 1/1']]);
        for ($k = 0; $k < Gladio::MESA_MAX - 1; $k++) {
            $e['lados']['a']['mesa'][] = ['i' => 90000 + $k, 'c' => null, 'nome' => 'Ficha', 'atk' => 1, 'vida' => 1, 'max' => 1, 'pode' => false, 'virada' => false, 'pal' => []];
        }
        $e = $this->joga($e, 'a', 'zezin'); // entra a 100ª; a ficha dela não cabe
        $this->assertCount(Gladio::MESA_MAX, $e['lados']['a']['mesa']);
        $this->assertStringContainsString('máximo de 100 Lendas', implode(' ', $e['log']));
        $this->expectExceptionMessage('máximo por jogador');
        $this->joga($e, 'a', 'x');
    }

    public function test_vida_padrao_de_40_e_aposta_nao_muda_as_cartas(): void
    {
        $deck = array_fill(0, 20, 'x');
        foreach ([0, 50, 300] as $aposta) {
            $e = Gladio::iniciar(self::BASE, $aposta, ['a' => $deck, 'b' => $deck], fn ($a) => $a, [], ['a' => 'x', 'b' => 'x']);
            $this->assertSame([40, 40, 40, 80], [$e['lados']['a']['vida'], $e['lados']['a']['max'], $e['lados']['b']['vida'], $e['total']], "aposta $aposta");
            $this->assertSame([1, 1], [$e['cartas']['x']['ataque'], $e['cartas']['x']['vida']], 'sem multiplicador de aposta');
        }
    }

    public function test_lesma_da_vida_pela_mao_e_bob_da_toque_aos_hexomantes(): void
    {
        $e = $this->mesa([
            'lesma' => ['nome' => 'Lesma', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1, 'palavras' => '',
                        'descricao' => 'Todas as suas lendas ganham +0/+X, onde X é o número de cartas da sua mão'],
            'bob'   => ['nome' => 'Bob, o diretor', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 3, 'palavras' => 'resistencia',
                        'subtipos' => 'hexomante, diretor', 'descricao' => 'Todos os seus hexomantes ganham toque da morte e inicativa'],
            'aluno' => ['nome' => 'Zezin', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 1, 'vida' => 1, 'palavras' => '', 'subtipos' => 'hexomante, aluno', 'descricao' => ''],
        ]);
        [$e, $lesma] = $this->poe($e, 'a', 'lesma');
        $this->naMao($e, 'a', 'x');
        $this->naMao($e, 'a', 'x');
        $e = Gladio::passar($e, 'a'); // recalcula
        $this->assertSame(3, $this->m($e, 'a', $lesma)['max'], '1 + 2 cartas na mão');
        [$e, $bob] = $this->poe($e, 'b', 'bob');
        [$e, $z] = $this->poe($e, 'b', 'aluno');
        $e = Gladio::passar($e, 'b');
        $this->assertSame(['toque', 'iniciativa'], array_values(array_diff($this->m($e, 'b', $z)['pal'], [])));
        $this->assertContains('toque', $this->m($e, 'b', $bob)['pal'], 'Bob também é hexomante');
        $this->assertNotContains('toque', $this->m($e, 'a', $lesma)['pal']);
    }

    public function test_regenerar_volta_de_pe_virado_n_vezes(): void
    {
        $e = $this->mesa(['maldito' => ['nome' => 'Maldito', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 2, 'vida' => 2, 'palavras' => '',
            'descricao' => 'Regenerar 1. Quando Maldito ataca, ele ataca todas as Lendas do jogador alvo']]);
        [$e, $m] = $this->poe($e, 'a', 'maldito');
        $t = $this->naMao($e, 'a', 'tiro');
        $e = Gladio::jogar($e, 'a', $t);
        $e = Gladio::escolher($e, 'a', $m);
        $this->assertSame([2, true, 0], [$this->m($e, 'a', $m)['vida'], $this->m($e, 'a', $m)['virada'], $this->m($e, 'a', $m)['regen']]);
        $e = $this->joga($e, 'a', 'tiro');
        $e = Gladio::escolher($e, 'a', $m);
        $this->assertNull($this->m($e, 'a', $m), 'na segunda vez cai');
    }

    public function test_maldito_ataca_todas_as_lendas_do_jogador(): void
    {
        $e = $this->mesa(['maldito' => ['nome' => 'Maldito', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 2, 'vida' => 9, 'palavras' => '',
            'descricao' => 'Regenerar 1. Quando Maldito ataca, ele ataca todas as Lendas do jogador alvo']]);
        [$e, $m] = $this->poe($e, 'a', 'maldito');
        [$e, $b1] = $this->poe($e, 'b', 'x');
        [$e, $b2] = $this->poe($e, 'b', 'x');
        $e = Gladio::atacar($e, 'a', [$m], 'b');
        $e = Gladio::defender($e, 'b');
        $this->assertSame([null, null, 99], [$this->m($e, 'b', $b1), $this->m($e, 'b', $b2), $e['lados']['b']['vida']]);
        $this->assertSame(7, $this->m($e, 'a', $m)['vida']);
    }

    public function test_gato_armado_barateia_feitico_de_dano(): void
    {
        $e = $this->mesa(['gato' => ['nome' => 'Gato armado', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1, 'palavras' => '',
            'descricao' => 'Diminua o custo de seus feitiços que causam dano em -1']]);
        [$e] = $this->poe($e, 'a', 'gato');
        $e['lados']['a']['energia'] = 1;
        $e = $this->joga($e, 'a', 'tiro');
        $this->assertSame(0, $e['lados']['a']['energia'], 'Tiro custou 1');
    }

    public function test_vergonha_de_dante_nega_a_invocacao_fora_do_turno(): void
    {
        $e = $this->mesa([
            'vergonha' => ['nome' => 'Vergonha de Dante', 'tipo' => 'feitico', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'palavras' => '', 'descricao' => 'Negue a invocação da carta alvo'],
            'barill'   => ['nome' => 'Barill', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 2, 'vida' => 2, 'palavras' => '', 'descricao' => 'Quando invocado, compre duas cartas'],
        ]);
        $v = $this->naMao($e, 'b', 'vergonha');
        $barill = $this->naMao($e, 'a', 'barill');
        try {
            Gladio::jogar(array_merge($e, ['vez' => 'b']), 'b', $v);
            $this->fail('só em resposta');
        } catch (\InvalidArgumentException) {
        }
        $e = Gladio::jogar($e, 'a', $barill);
        $this->assertSame(['b', 'resposta', [$v]], [$e['escolha']['lado'], $e['escolha']['tipo'], $e['escolha']['opcoes']]);
        $this->assertSame('pode responder', Gladio::visao($e, 'a')['escolha']['texto'], 'quem joga não vê a carta de quem pode responder');
        $e = Gladio::escolher($e, 'b', $v);
        $this->assertTrue($this->noCemiterio($e, 'a', $barill));
        $this->assertCount(0, $e['lados']['a']['mao'], 'o "quando invocado" não aconteceu');
        $this->assertSame(19, $e['lados']['b']['energia']);
        // sem responder: entra normalmente
        $e = $this->joga($e, 'a', 'barill');
        $this->assertNull($e['escolha'], 'b não tem mais a carta');
        $this->assertCount(2, $e['lados']['a']['mao']);
    }

    public function test_imbuir_troca_o_alvo_do_efeito(): void
    {
        $e = $this->mesa(['imbuir' => ['nome' => 'Imbuir', 'tipo' => 'feitico', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'palavras' => '', 'subtipos' => 'forma hexomante',
            'descricao' => 'Troque o alvo de um efeito ou ataque ocorrendo']]);
        [$e, $minha] = $this->poe($e, 'a', 'x');
        $e['lados']['b']['energia'] = 20;
        [$e, $dele] = $this->poe($e, 'b', 'x');
        $im = $this->naMao($e, 'b', 'imbuir');
        $e = $this->joga($e, 'a', 'tiro');
        $e = Gladio::escolher($e, 'a', $dele);
        $this->assertSame(['b', 'resposta'], [$e['escolha']['lado'], $e['escolha']['tipo']]);
        $e = Gladio::escolher($e, 'b', $im);
        $this->assertSame('b', $e['escolha']['lado'], 'quem respondeu escolhe o novo alvo');
        $e = Gladio::escolher($e, 'b', $minha);
        $this->assertNull($this->m($e, 'a', $minha));
        $this->assertNotNull($this->m($e, 'b', $dele));
    }

    public function test_cronometro_mata_no_fim_do_proximo_turno_do_jogador(): void
    {
        $e = $this->mesa(['crono' => ['nome' => 'Cronômetro', 'tipo' => 'feitico', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
            'descricao' => 'No fim do próximo turno do jogador alvo, a lenda alvo morre.']]);
        [$e, $dele] = $this->poe($e, 'b', 'x');
        $e = $this->joga($e, 'a', 'crono');
        $e = Gladio::escolher($e, 'a', 'j:b');
        $e = Gladio::escolher($e, 'a', $dele);
        $e = Gladio::passar($e, 'a');
        $this->assertNotNull($this->m($e, 'b', $dele));
        $e = Gladio::passar($e, 'b');
        $this->assertNull($this->m($e, 'b', $dele));
    }

    public function test_silhueta_protege_e_o_dono_paga_1_para_desvirar(): void
    {
        $e = $this->mesa(['silhueta' => ['nome' => 'Silhueta', 'tipo' => 'feitico', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
            'descricao' => 'Vire a lenda alvo de cabeça para baixo. Enquanto estiver assim, ela é hexo-imune e indestrutível e não pode atacar. O dono dela pode pagar 1 em aura para desvirá-la.']]);
        [$e, $dele] = $this->poe($e, 'b', 'x');
        $e = $this->joga($e, 'a', 'silhueta');
        $e = Gladio::escolher($e, 'a', $dele);
        $this->assertContains('indestrutivel', $this->m($e, 'b', $dele)['pal']);
        $e = Gladio::passar($e, 'a');
        try {
            Gladio::atacar($e, 'b', [$dele], 'a');
            $this->fail('de cabeça para baixo não ataca');
        } catch (\InvalidArgumentException) {
        }
        $energia = $e['lados']['b']['energia'];
        $e = Gladio::habilidade($e, 'b', $dele, 'silhueta');
        $this->assertSame([$energia - 1, []], [$e['lados']['b']['energia'], $this->m($e, 'b', $dele)['pal']]);
    }

    public function test_josael_vira_paga_e_faz_beber(): void
    {
        $e = $this->mesa(['josael' => ['nome' => 'Josael', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1, 'palavras' => '',
            'descricao' => 'Virar: Perca 10$, o jogador alvo vira 2 shots.']]);
        [$e, $j] = $this->poe($e, 'a', 'josael');
        $e = Gladio::habilidade($e, 'a', $j, '0');
        $e = Gladio::escolher($e, 'a', 'j:b');
        $this->assertSame([89, 2, true], [$e['lados']['a']['vida'], $e['lados']['b']['shots'], $this->m($e, 'a', $j)['virada']]);
        $this->expectException(\InvalidArgumentException::class);
        Gladio::habilidade($e, 'a', $j, '0');
    }

    public function test_estripador_paga_por_copias_e_depois_funde(): void
    {
        $e = $this->mesa(['estri' => ['nome' => 'Estripador', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 2, 'vida' => 2, 'palavras' => '',
            'descricao' => 'Quando invocado, você pode pagar +1 aura quantas vezes quiser para invocar mais estripadores em valor equivalente. No início de seu turno, você pode sacrificar todos os seus estripadores e invocar um Estripador com ataque e defessa igual ao dobro do ataque dos estripadores sacrificados']]);
        [$e] = $this->poe($e, 'a', 'estri');
        $e = Gladio::escolher($e, 'a', 'sim');
        $e = Gladio::escolher($e, 'a', 'sim');
        $e = Gladio::escolher($e, 'a', null);
        $this->assertCount(3, $e['lados']['a']['mesa']);
        $this->assertSame(17, $e['lados']['a']['energia']);
        $e = Gladio::passar($e, 'a');
        $e = Gladio::passar($e, 'b');
        $e = Gladio::escolher($e, 'a', 'sim'); // o início do turno do primeiro Estripador
        $this->assertCount(1, $e['lados']['a']['mesa']);
        $this->assertSame([12, 12], [$e['lados']['a']['mesa'][0]['atk'], $e['lados']['a']['mesa'][0]['max']]);
    }

    public function test_carneiro_morto_em_combate_leva_quem_lutou_e_valete_nao_morre(): void
    {
        $e = $this->mesa([
            'carneiro' => ['nome' => 'Carneiro', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1, 'palavras' => '',
                           'descricao' => 'Se Carneiro morrer em combate, mate a carta que combateu com ele. Sempre que uma Lenda morrer, roube 10$.'],
            'valete'   => ['nome' => 'Guarda real - Valete', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 5, 'vida' => 5, 'palavras' => '',
                           'descricao' => 'Não pode morrer em combate. Toda vez que atacar, recebe +3/+3.'],
        ]);
        [$e, $c] = $this->poe($e, 'a', 'carneiro');
        [$e, $grande] = $this->poe($e, 'b', 'x');
        $e['lados']['b']['mesa'][0]['vida'] = $e['lados']['b']['mesa'][0]['max'] = 9;
        $e = Gladio::atacar($e, 'a', [$c], 'b');
        $e = Gladio::bloquear($e, 'b', $grande, $c);
        $e = Gladio::defender($e, 'b');
        $this->assertNull($this->m($e, 'b', $grande), 'Carneiro levou junto quem o bloqueou');
        [$e, $v] = $this->poe($e, 'a', 'valete');
        [$e, $muro] = $this->poe($e, 'b', 'x');
        $e['lados']['b']['mesa'][0]['atk'] = 50;
        $e['atacou'] = false;
        $e = Gladio::atacar($e, 'a', [$v], 'b');
        $this->assertSame(8, $this->m($e, 'a', $v)['atk']);
        $e = Gladio::bloquear($e, 'b', $muro, $v);
        $e = Gladio::defender($e, 'b');
        $this->assertNotNull($this->m($e, 'a', $v), 'Valete não morre em combate');
    }

    public function test_isaque_ordena_o_topo_e_scry_manda_para_o_fundo(): void
    {
        $e = $this->mesa([
            'isaque' => ['nome' => 'Isaque', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1, 'palavras' => '',
                         'descricao' => 'Ao invocar Isaque, olhe as 3 cartas do topo do seu baralho. Mude a ordem entre elas como preferir e as coloque de volta.'],
            'roy'    => ['nome' => 'Roy Fernandez', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1, 'palavras' => '',
                         'descricao' => 'Ganha indestrutível se houver um hexomante no campo inimigo. Sempre que você ativar um feitiço, scry 3.'],
        ]);
        $topo = array_column(array_slice($e['lados']['a']['baralho'], 0, 3), 'i');
        [$e] = $this->poe($e, 'a', 'isaque');
        $e = Gladio::escolher($e, 'a', $topo[2]);
        $e = Gladio::escolher($e, 'a', $topo[0]);
        $this->assertSame([$topo[2], $topo[0], $topo[1]], array_column(array_slice($e['lados']['a']['baralho'], 0, 3), 'i'));
        [$e] = $this->poe($e, 'a', 'roy');
        [$e, $alvo] = $this->poe($e, 'b', 'x');
        $e = $this->joga($e, 'a', 'tiro');
        $e = Gladio::escolher($e, 'a', $alvo);
        $primeira = $e['lados']['a']['baralho'][0]['i'];
        $e = Gladio::escolher($e, 'a', $primeira); // scry: manda a de cima para o fundo
        $e = Gladio::escolher($e, 'a', null);
        $this->assertSame($primeira, end($e['lados']['a']['baralho'])['i']);
    }

    public function test_zequinha_proibe_a_letra_e_olhos_cancelam_duas(): void
    {
        $e = $this->mesa([
            'zeq'   => ['nome' => 'Zequinha', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1, 'palavras' => '',
                        'descricao' => 'Escolha uma letra, cartas de feitiço com essa letra não podem ser invocadas'],
            'olhos' => ['nome' => 'Olhos de Rarakoara', 'tipo' => 'encanto', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                        'descricao' => 'As próximas duas cartas conjuradas pelo jogador alvo são canceladas.'],
        ]);
        [$e] = $this->poe($e, 'b', 'zeq');
        $e = Gladio::escolher($e, 'b', 'T');
        try {
            $this->joga($e, 'a', 'tiro');
            $this->fail('Tiro tem T');
        } catch (\InvalidArgumentException $x) {
            $this->assertStringContainsString('letra T', $x->getMessage());
        }
        $e = $this->mesa(['olhos' => ['nome' => 'Olhos de Rarakoara', 'tipo' => 'encanto', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                        'descricao' => 'As próximas duas cartas conjuradas pelo jogador alvo são canceladas.']]);
        $e = $this->joga($e, 'a', 'olhos');
        $e = Gladio::escolher($e, 'a', 'j:b');
        $e = Gladio::passar($e, 'a');
        [$e, $minha] = $this->poe($e, 'a', 'x');
        $e['lados']['b']['energia'] = 20;
        $e = $this->joga($e, 'b', 'tiro');
        $e = $this->joga($e, 'b', 'tiro');
        $this->assertNull($e['escolha'], 'as duas foram canceladas');
        $this->assertSame([], $e['lados']['a']['feiticos'], 'Olhos vão para o cemitério depois das duas');
        $e = $this->joga($e, 'b', 'tiro');
        $this->assertSame('qualquer', $e['escolha']['tipo']);

        // Lendas também são canceladas
        $e = $this->mesa(['olhos' => ['nome' => 'Olhos de Rarakoara', 'tipo' => 'encanto', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                        'descricao' => 'As próximas duas cartas conjuradas pelo jogador alvo são canceladas.']]);
        $e = $this->joga($e, 'a', 'olhos');
        $e = Gladio::escolher($e, 'a', 'j:b');
        $e = Gladio::passar($e, 'a');
        $e['lados']['b']['energia'] = 20;
        $e = $this->joga($e, 'b', 'x');
        $this->assertSame([], $e['lados']['b']['mesa'], 'a Lenda foi cancelada');
        $this->assertSame(['x'], array_column($e['lados']['b']['cemiterio'], 'c'));
        $this->assertSame(1, $e['lados']['a']['feiticos'][0]['mk']['cancela'], 'falta cancelar uma');
    }

    public function test_caveirao_pede_sacrificio_e_amir_vence_em_3_ataques_diretos(): void
    {
        $e = $this->mesa([
            'cav'  => ['nome' => 'Caveirão', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 4, 'vida' => 4, 'palavras' => '',
                       'descricao' => 'Sacrifique uma lenda para invocar Caveirão. Quando ele matar uma lenda, invoque uma ficha de carniçal com status e palavras chave iguais aos da lenda morta.'],
            'amir' => ['nome' => 'Amir', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1, 'palavras' => '',
                       'descricao' => 'Se amir atacar diretamente um jogador 3 vezes, aquele jogador perde'],
        ]);
        try {
            $this->joga($e, 'a', 'cav');
            $this->fail('sem Lenda para sacrificar');
        } catch (\InvalidArgumentException) {
        }
        $e['lados']['a']['mao'] = [];
        [$e, $amir] = $this->poe($e, 'a', 'amir');
        for ($k = 0; $k < 3; $k++) {
            $e['vez'] = 'a';
            $e['fase'] = 'principal';
            $e['atacou'] = false;
            $e['lados']['a']['mesa'][0]['pode'] = true;
            $e = Gladio::atacar($e, 'a', [$amir], 'b');
            $e = Gladio::defender($e, 'b');
        }
        $this->assertSame(['vencedor' => 'a', 'motivo' => 'vida'], $e['fim']);
    }

    public function test_vassago_vence_daqui_a_6_turnos_e_drixopata_se_divide(): void
    {
        $e = $this->mesa([
            'vassago' => ['nome' => 'Nascimento de Vassago', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 0, 'vida' => 5, 'palavras' => '', 'descricao' => 'Daqui a 6 turnos, ganhe o jogo'],
            'drixo'   => ['nome' => 'Drixopata', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 2, 'vida' => 6, 'palavras' => '',
                          'descricao' => 'Quando morrer, invoque dois drixopatas com metade da defesa. Perde este efeito se tiver defesa igual ou menor a 2.'],
        ]);
        [$e, $d] = $this->poe($e, 'b', 'drixo');
        $e['lados']['b']['mesa'][0]['morto'] = true;
        $e = Gladio::passar($e, 'a');
        $this->assertSame([[2, 3], [2, 3]], array_map(fn ($m) => [$m['atk'], $m['max']], $e['lados']['b']['mesa']));
        [$e] = $this->poe($e, 'a', 'vassago');
        for ($k = 0; $k < 6 && !$e['fim']; $k++) {
            $e = Gladio::passar($e, $e['vez']);
        }
        $this->assertSame('a', $e['fim']['vencedor'] ?? null);
    }

    public function test_monolisco_enfraquece_e_cria_ficha_de_quem_morre(): void
    {
        $e = $this->mesa(['mono' => ['nome' => 'Monolisco', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 2, 'vida' => 2, 'palavras' => '',
            'descricao' => 'Todo início de turno, as Lendas inimigas recebem -1/-1. Sempre que uma Lenda morrer dessa forma, invoque uma ficha de Monolisco com o Primeiro efeito dessa lenda']]);
        [$e] = $this->poe($e, 'a', 'mono');
        [$e, $frágil] = $this->poe($e, 'b', 'x');
        $e = Gladio::passar($e, 'a');
        $e = Gladio::passar($e, 'b');
        $this->assertNull($this->m($e, 'b', $frágil));
        $this->assertCount(2, $e['lados']['a']['mesa']);
        $ficha = $e['lados']['a']['mesa'][1];
        $this->assertSame([true, 1], [$ficha['ficha'], $ficha['so']]);
    }

    public function test_maria_mostra_a_mao_do_oponente_e_fisiomancia_animal_aplica_a_propriedade(): void
    {
        $e = $this->mesa([
            'maria' => ['nome' => 'Maria', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1, 'palavras' => '',
                        'descricao' => 'Enquanto estiver no campo, veja a carta do topo do seu deck ou a mão de um oponente.'],
            'fa'    => ['nome' => 'Fisiomancia animal', 'tipo' => 'feitico', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                        'descricao' => 'Invoque três fichas de animal 1/1. Busque no seu baralho por uma propriedade fisiomantica, ela afeta as três fichas.'],
            'duro'  => ['nome' => 'Propriedade: Duro', 'tipo' => 'equipo', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'palavras' => '', 'descricao' => 'A carta equipada ganha +0/5'],
        ]);
        $this->naMao($e, 'b', 'x');
        [$e] = $this->poe($e, 'a', 'maria');
        $e = Gladio::escolher($e, 'a', 'j:b');
        $this->assertSame('x', Gladio::visao($e, 'a')['lados']['b']['mao'][0]['c']);
        $this->assertTrue(Gladio::visao($e, 'b')['lados']['a']['mao'] === [] || !isset(Gladio::visao($e, 'b')['lados']['a']['mao'][0]['c']));
        $e['lados']['a']['baralho'][] = ['i' => 7777, 'c' => 'duro'];
        $e = $this->joga($e, 'a', 'fa');
        $e = Gladio::escolher($e, 'a', 7777);
        $animais = array_values(array_filter($e['lados']['a']['mesa'], fn ($m) => ($m['nome'] ?? '') === 'Animal'));
        $this->assertSame([[1, 6], [1, 6], [1, 6]], array_map(fn ($m) => [$m['atk'], $m['max']], $animais));
        $this->assertTrue($this->noCemiterio($e, 'a', 7777));
    }

    public function test_azazyel_pede_dois_sacrificios_e_o_dano_pode_ir_nas_lendas(): void
    {
        $e = $this->mesa(['aza' => ['nome' => 'Parasita Temporal, Azazyel', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 5, 'vida' => 5, 'palavras' => '',
            'descricao' => 'sacrifique duas lendas para invocar Azazyel. Todo início de turno, todo outro jogador sofre o poder de Azazyel em dano, que pode redistribuir entre as próprias lendas para evitar.']]);
        [$e, $s1] = $this->poe($e, 'a', 'x');
        [$e, $s2] = $this->poe($e, 'a', 'x');
        [$e, $aza] = $this->poe($e, 'a', 'aza');
        $e = Gladio::escolher($e, 'a', $s1);
        $e = Gladio::escolher($e, 'a', $s2);
        $this->assertSame([$aza], array_map('intval', array_column($e['lados']['a']['mesa'], 'i')));
        [$e, $escudo] = $this->poe($e, 'b', 'x');
        $e = Gladio::passar($e, 'a');
        $e = Gladio::passar($e, 'b');
        $this->assertSame('b', $e['escolha']['lado']);
        $e = Gladio::escolher($e, 'b', $escudo); // segura 1
        $e = Gladio::escolher($e, 'b', 'j:b');   // o resto (4) vai nele
        $this->assertSame(95, $e['lados']['b']['vida']);
    }

    public function test_lucas_e_friga_da_golpe_extra_e_flavinho_volta_do_cemiterio(): void
    {
        $e = $this->mesa([
            'lf'  => ['nome' => 'Lucas e Friga', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 2, 'vida' => 2, 'palavras' => 'duplo',
                      'descricao' => 'Lendas com golpe duplo atacam uma vez adicional.'],
            'fla' => ['nome' => 'Flavinho', 'tipo' => 'lenda', 'custo' => 5, 'ataque' => 1, 'vida' => 1, 'palavras' => 'toque',
                      'descricao' => 'Se Flavinho estiver em seu cemitério, você pode descartar uma carta para invocá-lo sem pagar seus custos'],
        ]);
        [$e, $lf] = $this->poe($e, 'a', 'lf');
        $e = Gladio::atacar($e, 'a', [$lf], 'b');
        $e = Gladio::defender($e, 'b');
        $this->assertSame(93, $e['lados']['b']['vida'], '3 golpes de 2');
        $e['lados']['a']['cemiterio'][] = ['i' => 8888, 'c' => 'fla'];
        $lixo = $this->naMao($e, 'a', 'x');
        $e = Gladio::habilidade($e, 'a', 8888, '0');
        $e = Gladio::escolher($e, 'a', $lixo);
        $this->assertNotNull($this->m($e, 'a', 8888));
        $this->assertTrue($this->noCemiterio($e, 'a', $lixo));
    }

    public function test_plautus_recupera_feitico_encantamento_ou_equipamento(): void
    {
        $c = fn ($n, $t, $d) => ['nome' => $n, 'tipo' => $t, 'custo' => 1, 'ataque' => 1, 'vida' => 1, 'palavras' => '', 'descricao' => $d];
        $cartas = [
            'x' => $c('Soldado', 'lenda', ''),
            'pl' => $c('Diretor Plautus', 'lenda', 'Recupere um feitiço de seu cemitério para sua mão quando for invocado'),
            'enc' => $c('Encanto', 'encanto', ''),
            'eq' => $c('Espada', 'equipo', ''),
            'fe' => $c('Bola', 'feitico', ''),
        ];
        $deck = array_fill(0, 20, 'x');
        $e = $this->iniciar($cartas, 0, ['a' => $deck, 'b' => $deck], fn ($a) => $a, ['a' => 'Ana', 'b' => 'Bia'], ['a' => 'x', 'b' => 'x']);
        foreach (['a', 'b'] as $l) {
            if ($e['fase'] === 'mulligan') {
                $e = Gladio::manter($e, $l, []);
            }
        }
        $this->assertStringContainsString('encantamento ou equipamento', $e['cartas']['pl']['auto']);
        $e['lados']['a']['energia'] = 10;
        $e['lados']['a']['cemiterio'] = [['i' => 801, 'c' => 'enc'], ['i' => 802, 'c' => 'eq'], ['i' => 803, 'c' => 'fe'], ['i' => 804, 'c' => 'x']];
        $e['lados']['a']['mao'][] = ['i' => 990, 'c' => 'pl'];
        $e = Gladio::jogar($e, 'a', 990);
        $this->assertSame([801, 802, 803], $e['escolha']['opcoes']);
        $e = Gladio::escolher($e, 'a', '802');
        $this->assertContains('eq', array_column($e['lados']['a']['mao'], 'c'));
    }

    public function test_comandante_encantamento_volta_para_a_zona_de_comando(): void
    {
        $c = fn ($n, $t, $d, $custo = 1) => ['nome' => $n, 'tipo' => $t, 'custo' => $custo, 'ataque' => 0, 'vida' => 0, 'palavras' => '', 'descricao' => $d];
        $cartas = [
            'x' => $c('Soldado', 'lenda', ''),
            'fa' => $c('Fantasma das paredes', 'encanto', 'Essa carta pode ser seu comandante, mesmo sendo um encantamento. Toda vez que um jogador Mill, ele mila o dobro de cartas. Sempre que um jogador Mill, uma vez por turno, compre uma carta.', 3),
        ];
        $e = $this->iniciar($cartas, 0, ['a' => array_merge(['fa'], array_fill(0, 19, 'x')), 'b' => array_fill(0, 20, 'x')], fn ($a) => $a,
            ['a' => 'Ana', 'b' => 'Bia'], ['a' => 'fa', 'b' => 'x']);
        foreach (['a', 'b'] as $l) {
            if ($e['fase'] === 'mulligan') {
                $e = Gladio::manter($e, $l, []);
            }
        }
        $e['lados']['a']['energia'] = 10;
        $i = $e['lados']['a']['comando'][0]['i'];
        $e = Gladio::comandar($e, 'a');
        $this->assertSame([$i], array_column($e['lados']['a']['feiticos'], 'i'));
        $e = Gladio::encerrarFeitico($e, 'a', $i);
        $this->assertSame([$i], array_column($e['lados']['a']['comando'], 'i'));
        $this->assertSame([], $e['lados']['a']['cemiterio']);
        $this->assertSame(5, Gladio::custoComandante($e, 'a'));
    }

    public function test_aporium_descarta_para_nao_sofrer_dano_nem_ser_destruido(): void
    {
        $e = $this->mesa([
            'apo'  => ['nome' => 'Aporium', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 2, 'vida' => 3, 'palavras' => '',
                'descricao' => 'Uma vez por turno, você pode descartar uma carta, se fizer isso, Aporium não pode sofrer dano este turno. Todo início de turno, dobre o ataque de Aporium.'],
            'mata' => ['nome' => 'Morte', 'tipo' => 'feitico', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'palavras' => '', 'descricao' => 'Destrua uma lenda alvo'],
        ]);
        [$e, $apo] = $this->poe($e, 'a', 'apo');
        $this->naMao($e, 'a', 'x');
        // b conjura Morte no turno dele mirando o Aporium: a pode responder com a habilidade
        $e['vez'] = 'b';
        $e = $this->joga($e, 'b', 'mata');
        $e = Gladio::escolher($e, 'b', (string) $apo);
        $this->assertSame(['a', 'resposta'], [$e['escolha']['lado'], $e['escolha']['tipo']]);
        $this->assertContains("hab:$apo:0", $e['escolha']['opcoes']);
        $e = Gladio::escolher($e, 'a', "hab:$apo:0");
        $this->assertSame(['a', 'mao'], [$e['escolha']['lado'], $e['escolha']['tipo']], 'paga descartando uma carta');
        $e = Gladio::escolher($e, 'a', (string) $e['escolha']['opcoes'][0]);
        $this->assertNotNull($this->m($e, 'a', $apo), 'protegido, a Morte não o destrói');
        $this->assertSame([], $e['lados']['a']['mao']);

        // usada no próprio turno: o Tiro não causa dano
        $e = $this->mesa(['apo' => ['nome' => 'Aporium', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 2, 'vida' => 3, 'palavras' => '',
            'descricao' => 'Uma vez por turno, você pode descartar uma carta, se fizer isso, Aporium não pode sofrer dano este turno. Todo início de turno, dobre o ataque de Aporium.']]);
        [$e, $apo] = $this->poe($e, 'a', 'apo');
        $this->naMao($e, 'a', 'x');
        $e = Gladio::habilidade($e, 'a', $apo, '0');
        $e = Gladio::escolher($e, 'a', (string) $e['escolha']['opcoes'][0]);
        $e = $this->joga($e, 'a', 'tiro');
        $e = Gladio::escolher($e, 'a', (string) $apo);
        $this->assertSame(3, $this->m($e, 'a', $apo)['vida']);
    }

    public function test_barill_so_compra_se_sacrificar(): void
    {
        $barill = ['nome' => 'Barill', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 4, 'palavras' => '', 'descricao' => 'Quando invocado, sacrifique uma lenda, compre duas cartas'];
        $e = $this->mesa(['bar' => $barill]);
        $e = $this->joga($e, 'a', 'bar'); // sozinho: não tem o que sacrificar
        $this->assertSame([], $e['lados']['a']['mao'], 'sem sacrifício, não compra');

        $e = $this->mesa(['bar' => $barill]);
        [$e, $x] = $this->poe($e, 'a', 'x');
        $e = $this->joga($e, 'a', 'bar');
        $this->assertSame([$x], $e['escolha']['opcoes']);
        $e = Gladio::escolher($e, 'a', null); // pulou o sacrifício
        $this->assertSame([], $e['lados']['a']['mao']);

        $e = $this->mesa(['bar' => $barill]);
        [$e, $x] = $this->poe($e, 'a', 'x');
        $e = $this->joga($e, 'a', 'bar');
        $e = Gladio::escolher($e, 'a', (string) $x);
        $this->assertCount(2, $e['lados']['a']['mao'], 'sacrificou: compra duas');
    }

    public function test_mundo_conjurado_ataca_as_lendas_de_todos_os_inimigos(): void
    {
        $cartas = self::BASE + [
            'big'   => ['nome' => 'Gigante', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 3, 'vida' => 10, 'palavras' => '', 'descricao' => ''],
            'mundo' => ['nome' => 'Mundo Conjurado', 'tipo' => 'encanto', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                'descricao' => 'No início de seu turno, pague 5 em aura, caso contrário, perca essa carta. Toda vez que uma lenda sua atacar, ataque todas as lendas de todos os inimigos'],
        ];
        $deck = array_fill(0, 20, 'x');
        $e = $this->iniciar($cartas, 0, ['a' => $deck, 'b' => $deck, 'c' => $deck], fn ($a) => $a, [], ['a' => 'x', 'b' => 'x', 'c' => 'x']);
        $e['lados']['a']['energia'] = 20;
        $e = $this->joga($e, 'a', 'mundo');
        [$e, $big] = $this->poe($e, 'a', 'big');
        foreach (['b' => [801, 802, 803], 'c' => [811, 812]] as $l => $is) {
            foreach ($is as $i) {
                $e['lados'][$l]['mesa'][] = ['i' => $i, 'c' => 'x', 'atk' => 1, 'vida' => 1, 'max' => 1, 'pode' => true, 'virada' => false, 'pal' => []];
            }
        }
        $this->assertContains('ataca_todas', $this->m($e, 'a', $big)['fl'], 'o encantamento dá a marca às Lendas do dono');
        $e = Gladio::atacar($e, 'a', [$big], 'b');
        $e = Gladio::defender($e, 'b');
        $this->assertSame([[], []], [$e['lados']['b']['mesa'], $e['lados']['c']['mesa']], 'as do atacado e as do outro inimigo caíram');
        $this->assertSame(5, $this->m($e, 'a', $big)['vida'], 'e as cinco revidaram');
        $this->assertSame([Gladio::VIDA, Gladio::VIDA], [$e['lados']['b']['vida'], $e['lados']['c']['vida']], 'com Lendas para atacar, ninguém leva dano direto');
    }

    public function test_aporium_passa_para_o_atacado_e_cobra_no_fim_do_turno(): void
    {
        $e = $this->mesa(['apo' => ['nome' => 'Aporium', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 3, 'vida' => 3, 'palavras' => '',
            'descricao' => 'Quando Aporium atacar, ele se torna posse do jogador defensor. Se Aporium terminou seu turno em seu campo, perca 10$']]);
        [$e, $apo] = $this->poe($e, 'a', 'apo');
        $e = Gladio::atacar($e, 'a', [$apo], 'b');
        $this->assertNotNull($this->m($e, 'b', $apo));
        $e = Gladio::defender($e, 'b');
        $e = Gladio::passar($e, 'a');
        $this->assertSame(99, $e['lados']['a']['vida']);
        $e = Gladio::passar($e, 'b');
        $this->assertSame(89, $e['lados']['b']['vida']);
    }
}
