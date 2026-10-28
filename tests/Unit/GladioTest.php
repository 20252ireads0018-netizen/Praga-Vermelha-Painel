<?php

namespace Tests\Unit;

use App\Jogos\Gladio;
use PHPUnit\Framework\TestCase;

class GladioTest extends TestCase
{
    private const CARTAS = [
        '1' => ['nome' => 'Gladiador', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 3, 'vida' => 2, 'efeito' => null, 'valor' => 0, 'palavras' => ''],
        '2' => ['nome' => 'Bola de fogo', 'tipo' => 'feitico', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'efeito' => 'dano', 'valor' => 4, 'palavras' => ''],
        '3' => ['nome' => 'Bênção', 'tipo' => 'feitico', 'custo' => 0, 'ataque' => 0, 'vida' => 0, 'efeito' => 'cura', 'valor' => 5, 'palavras' => ''],
        '4' => ['nome' => 'Lucas', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 4, 'vida' => 5, 'efeito' => null, 'valor' => 0, 'palavras' => 'sobrepujar'],
        '5' => ['nome' => 'Corredor', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1, 'efeito' => null, 'valor' => 0, 'palavras' => 'impeto'],
    ];

    /** Sem sorte: decks na ordem dada. */
    private function novo(int $aposta = 10, array $decks = []): array
    {
        $decks += ['a' => array_fill(0, 20, '1'), 'b' => array_fill(0, 20, '1')];

        return Gladio::iniciar(self::CARTAS, $aposta, $decks, fn ($a) => $a);
    }

    private function primeira(array $e, string $lado, string $carta): int
    {
        foreach ($e['lados'][$lado]['mao'] as $c) {
            if ($c['c'] === $carta) {
                return $c['i'];
            }
        }
        $this->fail("carta $carta não está na mão de $lado");
    }

    /** Joga uma Lenda e já a deixa pronta para atacar (pula o turno do outro). */
    private function lendaPronta(array $e, string $lado, string $carta): array
    {
        $e['lados'][$lado]['energia'] = 10;
        $e = Gladio::jogar($e, $lado, $this->primeira($e, $lado, $carta));
        $k = array_key_last($e['lados'][$lado]['mesa']);
        $e['lados'][$lado]['mesa'][$k]['pode'] = true;

        return $e;
    }

    public function test_inicio_vida_aposta_maos_energia_e_deck(): void
    {
        $e = $this->novo(50, ['a' => array_merge(['4', '5'], array_fill(0, 13, '1'))]);
        $this->assertSame(50, $e['lados']['a']['vida']);
        $this->assertCount(5, $e['lados']['a']['mao']);
        $this->assertCount(5, $e['lados']['b']['mao']);
        $this->assertCount(10, $e['lados']['a']['baralho'], 'deck de 15 menos 5 na mão');
        $this->assertSame(['4', '5'], array_column(array_slice($e['lados']['a']['mao'], 0, 2), 'c'));
        $this->assertSame([1, 0], [$e['lados']['a']['energia'], $e['lados']['b']['energiaMax']]);
        $this->assertSame(Gladio::VIDA_AMISTOSO, $this->novo(0)['lados']['b']['vida']);
        // sem deck: 20 cartas sorteadas do catálogo
        $s = Gladio::iniciar(self::CARTAS, 0, [], fn ($a) => $a);
        $this->assertCount(15, $s['lados']['b']['baralho']);
    }

    public function test_energia_sobe_a_cada_rodada_e_compra_no_turno(): void
    {
        $e = $this->novo();
        $e = Gladio::passar($e, 'a');
        $this->assertSame([1, 1], [$e['lados']['b']['energia'], $e['lados']['b']['energiaMax']]);
        $this->assertCount(6, $e['lados']['b']['mao']);
        $e = Gladio::passar($e, 'b');
        $e = Gladio::passar($e, 'a');
        $this->assertSame(2, $e['lados']['b']['energiaMax']);
        $this->assertSame(2, $e['lados']['a']['energiaMax']);
    }

    public function test_lenda_so_ataca_no_turno_seguinte_e_impeto_ataca_ja(): void
    {
        $e = $this->novo(10, ['a' => array_merge(['5'], array_fill(0, 19, '1'))]);
        $e['lados']['a']['energia'] = 5;
        $g = $this->primeira($e, 'a', '1');
        $e = Gladio::jogar($e, 'a', $g);
        try {
            Gladio::atacar($e, 'a', [$g]);
            $this->fail('atacou no mesmo turno');
        } catch (\InvalidArgumentException) {
        }
        $c = $this->primeira($e, 'a', '5');
        $e = Gladio::jogar($e, 'a', $c);
        $e = Gladio::atacar($e, 'a', [$c]); // Ímpeto
        $this->assertSame('defesa', $e['fase']);
    }

    public function test_ataque_sem_bloqueio_fere_o_jogador_e_espera_a_defesa(): void
    {
        $e = $this->lendaPronta($this->novo(), 'a', '1');
        $g = $e['lados']['a']['mesa'][0]['i'];
        $e = Gladio::atacar($e, 'a', [$g]);
        // enquanto a defesa não vem, quem atacou não age
        try {
            Gladio::passar($e, 'a');
            $this->fail('passou durante a defesa');
        } catch (\InvalidArgumentException) {
        }
        $e = Gladio::defender($e, 'b');
        $this->assertSame([7, 'principal'], [$e['lados']['b']['vida'], $e['fase']]);
        $this->assertFalse($e['lados']['a']['mesa'][0]['pode'], 'quem atacou fica cansado');
    }

    public function test_defensor_joga_lenda_da_mao_e_bloqueia(): void
    {
        $e = $this->lendaPronta($this->novo(), 'a', '1');
        $atk = $e['lados']['a']['mesa'][0]['i'];
        $e = Gladio::atacar($e, 'a', [$atk]);
        // o defensor ainda não tem energia; com 1 de energia sobrando, invoca um Gladiador na defesa
        $e['lados']['b']['energia'] = 1;
        $nova = $this->primeira($e, 'b', '1');
        $e = Gladio::jogar($e, 'b', $nova);
        $this->assertSame(0, $e['lados']['b']['energia']);
        $e = Gladio::bloquear($e, 'b', $nova, $atk);
        $e = Gladio::defender($e, 'b');
        // 3 de ataque contra 2 de vida dos dois lados: os dois caem e vão para o cemitério
        $this->assertSame(10, $e['lados']['b']['vida']);
        $this->assertSame([], $e['lados']['a']['mesa']);
        $this->assertSame([], $e['lados']['b']['mesa']);
        $this->assertSame(['1'], array_column($e['lados']['a']['cemiterio'], 'c'));
        $this->assertSame(['1'], array_column($e['lados']['b']['cemiterio'], 'c'));
    }

    public function test_na_defesa_so_lendas_e_pagando_energia(): void
    {
        $e = $this->lendaPronta($this->novo(10, ['b' => array_merge(['2'], array_fill(0, 19, '1'))]), 'a', '1');
        $e = Gladio::atacar($e, 'a', [$e['lados']['a']['mesa'][0]['i']]);
        $e['lados']['b']['energia'] = 5;
        $this->expectExceptionMessage('Na defesa só dá para jogar Lendas.');
        Gladio::jogar($e, 'b', $this->primeira($e, 'b', '2'), 'inimigo');
    }

    public function test_sobrepujar_com_bloqueio(): void
    {
        $e = $this->lendaPronta($this->novo(10, ['a' => array_merge(['4'], array_fill(0, 19, '1'))]), 'a', '4');
        $lucas = $e['lados']['a']['mesa'][0]['i'];
        $e = Gladio::atacar($e, 'a', [$lucas]);
        $e['lados']['b']['energia'] = 1;
        $bloq = $this->primeira($e, 'b', '1');
        $e = Gladio::jogar($e, 'b', $bloq);
        $e = Gladio::bloquear($e, 'b', $bloq, $lucas);
        $e = Gladio::defender($e, 'b');
        // Lucas 4/5 contra Gladiador 3/2: 2 para derrubar o bloqueador e 2 passam para o jogador
        $this->assertSame(8, $e['lados']['b']['vida']);
        $this->assertSame(2, $e['lados']['a']['mesa'][0]['vida']);
        $this->assertSame([], $e['lados']['b']['mesa']);
    }

    public function test_feitico_de_dano_vence_vai_ao_cemiterio_e_precisa_de_alvo(): void
    {
        $e = $this->novo(4, ['a' => array_merge(['2'], array_fill(0, 19, '1'))]);
        $fogo = $this->primeira($e, 'a', '2');
        try {
            Gladio::jogar($e, 'a', $fogo);
            $this->fail('feitiço de dano sem alvo');
        } catch (\InvalidArgumentException) {
        }
        $e = Gladio::jogar($e, 'a', $fogo, 'inimigo');
        $this->assertSame(['vencedor' => 'a', 'motivo' => 'vida'], $e['fim']);
        $this->assertSame(['2'], array_column($e['lados']['a']['cemiterio'], 'c'));
    }

    public function test_ferramentas_de_baralho(): void
    {
        $deck = ['1', '1', '1', '1', '1', '2', '3', '4', '5', '1'];
        $e = $this->novo(10, ['a' => $deck]);
        $ordem = array_column($e['lados']['a']['baralho'], 'i');
        $this->assertSame(['2', '3', '4', '5', '1'], array_column($e['lados']['a']['baralho'], 'c'));
        $e = Gladio::reordenar($e, 'a', array_reverse($ordem));
        $this->assertSame(['1', '5', '4', '3', '2'], array_column($e['lados']['a']['baralho'], 'c'));
        try {
            Gladio::reordenar($e, 'a', array_slice($ordem, 1));
            $this->fail('ordem sem todas as cartas');
        } catch (\InvalidArgumentException) {
        }
        $e = Gladio::comprar($e, 'a');
        $this->assertSame('1', end($e['lados']['a']['mao'])['c']);
        $e = Gladio::embaralhar($e, 'a', fn ($a) => array_reverse($a));
        $this->assertSame(['2', '3', '4', '5'], array_column($e['lados']['a']['baralho'], 'c'));
        $i = end($e['lados']['a']['mao'])['i'];
        $e = Gladio::descartar($e, 'a', $i);
        $this->assertSame([$i], array_column($e['lados']['a']['cemiterio'], 'i'));
        $e = Gladio::recuperar($e, 'a', $i);
        $this->assertSame([], $e['lados']['a']['cemiterio']);
        // ferramentas só no próprio turno
        $this->expectException(\InvalidArgumentException::class);
        Gladio::comprar($e, 'b');
    }

    public function test_ficha_ajuste_copia_sacrificio_e_jogar_sem_pagar(): void
    {
        $e = $this->novo(10, ['a' => array_merge(['4'], array_fill(0, 19, '1'))]);
        $e['lados']['a']['energia'] = 0;
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', '4'), null, true);
        $this->assertSame(0, $e['lados']['a']['energia']);
        $e = Gladio::ficha($e, 'a', 'Olhinho', 1, 1);
        $olhinho = $e['lados']['a']['mesa'][1];
        $this->assertSame([null, 'Olhinho', 1, 1], [$olhinho['c'], $olhinho['nome'], $olhinho['atk'], $olhinho['vida']]);
        $e = Gladio::ajustar($e, 'a', $olhinho['i'], 1, 1);
        $this->assertSame([2, 2, 2], [$e['lados']['a']['mesa'][1]['atk'], $e['lados']['a']['mesa'][1]['vida'], $e['lados']['a']['mesa'][1]['max']]);
        // a ficha vira cópia do Lucas (4/5, Sobrepujar)
        $e = Gladio::copiar($e, 'a', $olhinho['i'], $e['lados']['a']['mesa'][0]['i']);
        $copia = $e['lados']['a']['mesa'][1];
        $this->assertSame(['4', 4, 5, ['sobrepujar']], [$copia['c'], $copia['atk'], $copia['vida'], $copia['pal']]);
        $e = Gladio::sacrificar($e, 'a', $copia['i']);
        $this->assertCount(1, $e['lados']['a']['mesa']);
        $this->assertSame(['4'], array_column($e['lados']['a']['cemiterio'], 'c'));
        // ajuste que zera a vida derruba a Lenda
        $e = Gladio::ajustar($e, 'a', $e['lados']['a']['mesa'][0]['i'], 0, -5);
        $this->assertSame([], $e['lados']['a']['mesa']);
    }

    public function test_fadiga_e_mao_cheia_vai_ao_cemiterio(): void
    {
        $e = $this->novo();
        $e['lados']['b']['baralho'] = [];
        $e = Gladio::passar($e, 'a');
        $this->assertSame(9, $e['lados']['b']['vida']);
        $e['lados']['a']['mao'] = array_fill(0, 10, ['i' => 999, 'c' => '1']);
        $e = Gladio::passar($e, 'b');
        $this->assertCount(10, $e['lados']['a']['mao']);
        $this->assertCount(1, $e['lados']['a']['cemiterio']);
    }

    public function test_visao_esconde_mao_e_baralho_do_outro(): void
    {
        $v = Gladio::visao($this->novo(), 'a');
        $this->assertIsArray($v['lados']['a']['mao']);
        $this->assertIsArray($v['lados']['a']['baralho'], 'cada um consulta o próprio baralho');
        $this->assertSame(5, $v['lados']['b']['mao']);
        $this->assertSame(15, $v['lados']['b']['baralho']);
        $this->assertIsArray($v['lados']['b']['cemiterio'], 'cemitério é público');
    }
}
