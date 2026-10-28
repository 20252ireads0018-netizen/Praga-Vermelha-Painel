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

    /* ---------- palavras-chave ---------- */

    private int $seqTeste = 900;

    /** Põe uma Lenda pronta direto na mesa, com as palavras-chave dadas. Devolve [estado, id]. */
    private function por(array $e, string $lado, string $nome, int $atk, int $vida, array $pal = []): array
    {
        $i = ++$this->seqTeste;
        $e['lados'][$lado]['mesa'][] = ['i' => $i, 'c' => null, 'nome' => $nome, 'atk' => $atk, 'vida' => $vida, 'max' => $vida,
                                        'pode' => true, 'virada' => false, 'pal' => $pal];

        return [$e, $i];
    }

    private function vida(array $e, string $lado, int $i): ?int
    {
        foreach ($e['lados'][$lado]['mesa'] as $m) {
            if ($m['i'] === $i) {
                return $m['vida'];
            }
        }

        return null; // caiu
    }

    /** a ataca com $atacante; b bloqueia com $bloqueadores; devolve o estado depois do combate. */
    private function combate(array $e, int $atacante, array $bloqueadores = []): array
    {
        $e = Gladio::atacar($e, 'a', [$atacante]);
        foreach ($bloqueadores as $b) {
            $e = Gladio::bloquear($e, 'b', $b, $atacante);
        }

        return Gladio::defender($e, 'b');
    }

    public function test_voar_so_bloqueado_por_voar_ou_alcance(): void
    {
        [$e, $dragao] = $this->por($this->novo(), 'a', 'Dragão', 3, 3, ['voar']);
        [$e, $chao] = $this->por($e, 'b', 'Soldado', 1, 1);
        [$e, $arqueiro] = $this->por($e, 'b', 'Arqueiro', 1, 4, ['alcance']);
        $e = Gladio::atacar($e, 'a', [$dragao]);
        try {
            Gladio::bloquear($e, 'b', $chao, $dragao);
            $this->fail('soldado sem Alcance bloqueou quem voa');
        } catch (\InvalidArgumentException) {
        }
        $e = Gladio::bloquear($e, 'b', $arqueiro, $dragao);
        $e = Gladio::defender($e, 'b');
        $this->assertSame([1, 2], [$this->vida($e, 'b', $arqueiro), $this->vida($e, 'a', $dragao)]);
    }

    public function test_toque_mortal_derruba_com_qualquer_dano_e_indestrutivel_resiste(): void
    {
        [$e, $cobra] = $this->por($this->novo(), 'a', 'Cobra', 1, 1, ['toque']);
        [$e, $gigante] = $this->por($e, 'b', 'Gigante', 0, 10);
        $e = $this->combate($e, $cobra, [$gigante]);
        $this->assertNull($this->vida($e, 'b', $gigante), 'o gigante caiu com 1 de dano');

        [$e, $cobra2] = $this->por($this->novo(), 'a', 'Cobra', 5, 1, ['toque']);
        [$e, $golem] = $this->por($e, 'b', 'Golem', 0, 3, ['indestrutivel']);
        $e = $this->combate($e, $cobra2, [$golem]);
        $this->assertSame(1, $this->vida($e, 'b', $golem), 'indestrutível fica de pé com 1 de vida');
    }

    public function test_toque_mortal_com_sobrepujar_passa_quase_tudo(): void
    {
        [$e, $a] = $this->por($this->novo(), 'a', 'Hidra', 6, 6, ['toque', 'sobrepujar']);
        [$e, $b] = $this->por($e, 'b', 'Muralha', 0, 8);
        $e = $this->combate($e, $a, [$b]);
        // basta 1 de dano para derrubar a muralha: os outros 5 vão para o jogador
        $this->assertSame(5, $e['lados']['b']['vida']);
        $this->assertNull($this->vida($e, 'b', $b));
    }

    public function test_vinculo_com_a_vida_cura_quem_controla(): void
    {
        $e = $this->novo();
        $e['lados']['a']['vida'] = 4;
        [$e, $v] = $this->por($e, 'a', 'Vampiro', 3, 3, ['vinculo']);
        $e = $this->combate($e, $v);
        $this->assertSame([7, 7], [$e['lados']['a']['vida'], $e['lados']['b']['vida']]);
    }

    public function test_iniciativa_derruba_antes_de_apanhar(): void
    {
        [$e, $esgrimista] = $this->por($this->novo(), 'a', 'Esgrimista', 2, 1, ['iniciativa']);
        [$e, $bruto] = $this->por($e, 'b', 'Bruto', 5, 2);
        $e = $this->combate($e, $esgrimista, [$bruto]);
        $this->assertNull($this->vida($e, 'b', $bruto));
        $this->assertSame(1, $this->vida($e, 'a', $esgrimista), 'o bruto caiu antes de bater');
    }

    public function test_golpe_duplo_bate_duas_vezes(): void
    {
        [$e, $d] = $this->por($this->novo(), 'a', 'Gêmeas', 3, 3, ['duplo']);
        $e = $this->combate($e, $d);
        $this->assertSame(4, $e['lados']['b']['vida'], 'sem bloqueio: 3 + 3');

        [$e, $d2] = $this->por($this->novo(), 'a', 'Gêmeas', 2, 5, ['duplo']);
        [$e, $b] = $this->por($e, 'b', 'Escudeiro', 1, 4);
        $e = $this->combate($e, $d2, [$b]);
        $this->assertNull($this->vida($e, 'b', $b), '2 no primeiro passo e 2 no normal');
        $this->assertSame(4, $this->vida($e, 'a', $d2));
    }

    public function test_virada_vigilancia_e_defensor(): void
    {
        [$e, $a1] = $this->por($this->novo(), 'a', 'Guerreiro', 1, 5);
        [$e, $a2] = $this->por($e, 'a', 'Sentinela', 1, 5, ['vigilancia']);
        [$e, $muro] = $this->por($e, 'a', 'Muro', 0, 9, ['defensor']);
        try {
            Gladio::atacar($e, 'a', [$muro]);
            $this->fail('Defensor atacou');
        } catch (\InvalidArgumentException) {
        }
        $e = Gladio::defender(Gladio::atacar($e, 'a', [$a1, $a2]), 'b');
        $e = Gladio::passar($e, 'a');
        // turno da b: ela ataca e a só pode bloquear com quem não ficou virada
        [$e, $inv] = $this->por($e, 'b', 'Invasor', 1, 1);
        $e = Gladio::atacar($e, 'b', [$inv]);
        try {
            Gladio::bloquear($e, 'a', $a1, $inv);
            $this->fail('Lenda virada bloqueou');
        } catch (\InvalidArgumentException) {
        }
        $e = Gladio::bloquear($e, 'a', $a2, $inv); // Vigilância: não virou
        $e = Gladio::bloquear($e, 'a', $muro, $inv);
        $e = Gladio::defender($e, 'a');
        $this->assertNull($this->vida($e, 'b', $inv));
        // no turno seguinte da a, todas desviram
        $e = Gladio::passar($e, 'b');
        $this->assertSame([false, false, false], array_column($e['lados']['a']['mesa'], 'virada'));
    }

    public function test_ameaca_exige_dois_bloqueadores(): void
    {
        [$e, $lobo] = $this->por($this->novo(), 'a', 'Lobo', 2, 2, ['ameaca']);
        [$e, $b1] = $this->por($e, 'b', 'Cão', 1, 1);
        [$e, $b2] = $this->por($e, 'b', 'Cão', 1, 1);
        $e = Gladio::atacar($e, 'a', [$lobo]);
        $e = Gladio::bloquear($e, 'b', $b1, $lobo);
        try {
            Gladio::defender($e, 'b');
            $this->fail('Ameaça bloqueada por uma só');
        } catch (\InvalidArgumentException) {
        }
        $e = Gladio::defender(Gladio::bloquear($e, 'b', $b2, $lobo), 'b');
        $this->assertNull($this->vida($e, 'a', $lobo));
    }

    public function test_resistencia_a_magia_protege_dos_feiticos_do_adversario(): void
    {
        $e = $this->novo(10, ['a' => array_merge(['2'], array_fill(0, 19, '1'))]);
        [$e, $monge] = $this->por($e, 'b', 'Monge', 1, 3, ['resistencia']);
        $this->expectExceptionMessage('Resistência a magia');
        Gladio::jogar($e, 'a', $this->primeira($e, 'a', '2'), (string) $monge);
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
