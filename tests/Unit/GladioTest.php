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
        $this->assertCount(9, $e['lados']['a']['baralho'], 'deck de 15 menos 5 na mão e o comandante');
        $this->assertSame(['4', '5'], array_column(array_slice($e['lados']['a']['mao'], 0, 2), 'c'));
        $this->assertSame([1, 0], [$e['lados']['a']['energia'], $e['lados']['b']['energiaMax']]);
        $this->assertSame(Gladio::VIDA_AMISTOSO, $this->novo(0)['lados']['b']['vida']);
        // sem deck: 20 cartas sorteadas do catálogo
        $s = Gladio::iniciar(self::CARTAS, 0, [], fn ($a) => $a);
        $this->assertCount(14, $s['lados']['b']['baralho']);
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

    public function test_defensor_bloqueia_com_a_lenda_que_ja_tem(): void
    {
        $e = $this->lendaPronta($this->novo(), 'a', '1');
        $atk = $e['lados']['a']['mesa'][0]['i'];
        [$e, $nova] = $this->por($e, 'b', 'Gladiador', 3, 2);
        $e = Gladio::atacar($e, 'a', [$atk]);
        $e = Gladio::bloquear($e, 'b', $nova, $atk);
        $e = Gladio::defender($e, 'b');
        // 3 de ataque contra 2 de vida dos dois lados: os dois caem (a ficha some; a carta vai para o cemitério)
        $this->assertSame(10, $e['lados']['b']['vida']);
        $this->assertSame([], $e['lados']['a']['mesa']);
        $this->assertSame([], $e['lados']['b']['mesa']);
        $this->assertSame(['1'], array_column($e['lados']['a']['cemiterio'], 'c'));
    }

    public function test_na_defesa_nao_se_joga_cartas_nem_lendas(): void
    {
        $e = $this->lendaPronta($this->novo(10, ['b' => array_merge(['2'], array_fill(0, 19, '1'))]), 'a', '1');
        $e = Gladio::atacar($e, 'a', [$e['lados']['a']['mesa'][0]['i']]);
        $e['lados']['b']['energia'] = 5;
        try {
            Gladio::jogar($e, 'b', $this->primeira($e, 'b', '1'));
            $this->fail('Lenda no turno do adversário');
        } catch (\InvalidArgumentException $x) {
            $this->assertSame('Não é a sua vez.', $x->getMessage());
        }
        try {
            Gladio::invocar($e, 'b', $this->primeira($e, 'b', '1'), 'mao');
            $this->fail('invocar sem custo também é só no próprio turno');
        } catch (\InvalidArgumentException $x) {
            $this->assertStringContainsString('só entram em jogo no seu turno', $x->getMessage());
        }
    }

    public function test_sobrepujar_com_bloqueio(): void
    {
        $e = $this->lendaPronta($this->novo(10, ['a' => array_merge(['4'], array_fill(0, 19, '1'))]), 'a', '4');
        $lucas = $e['lados']['a']['mesa'][0]['i'];
        [$e, $bloq] = $this->por($e, 'b', 'Gladiador', 3, 2);
        $e = Gladio::atacar($e, 'a', [$lucas]);
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
        $this->assertSame(['2'], array_column($e['lados']['a']['cemiterio'], 'c'), 'feitiço resolve e vai para o cemitério');
    }

    public function test_ferramentas_de_baralho(): void
    {
        $deck = ['1', '1', '1', '1', '1', '2', '3', '4', '5', '1', '1']; // o último '1' vira o comandante
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
        // agora a qualquer momento: o outro também compra fora da vez
        $this->assertCount(6, Gladio::comprar($e, 'b')['lados']['b']['mao']);
    }

    /** Peças do motor usadas pelos efeitos das cartas (a mesa não oferece mais nada disso à mão). */
    public function test_motor_ficha_ajuste_copia_sacrificio_e_jogar_sempre_pagando(): void
    {
        $e = $this->novo(10, ['a' => array_merge(['4'], array_fill(0, 19, '1'))]);
        $e['lados']['a']['energia'] = 0;
        try {
            Gladio::jogar($e, 'a', $this->primeira($e, 'a', '4'), null, true);
            $this->fail('jogou sem energia pedindo "grátis"');
        } catch (\InvalidArgumentException $x) {
            $this->assertSame('Energia insuficiente.', $x->getMessage());
        }
        $e['lados']['a']['energia'] = 1;
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

    public function test_hexo_imune_protege_dos_feiticos_do_adversario(): void
    {
        $e = $this->novo(10, ['a' => array_merge(['2'], array_fill(0, 19, '1'))]);
        [$e, $monge] = $this->por($e, 'b', 'Monge', 1, 3, ['resistencia']);
        $this->expectExceptionMessage('Hexo-Imune');
        Gladio::jogar($e, 'a', $this->primeira($e, 'a', '2'), (string) $monge);
    }

    public function test_feitico_com_texto_ignora_o_efeito_fixo_antigo_e_ninguem_escolhe_efeito(): void
    {
        // carta antiga com "dano 1" guardado: o texto manda (Máscara de constructo criava 1 de dano em vez das fichas)
        $cartas = self::CARTAS + [
            'mascara' => ['nome' => 'Máscara de constructo', 'tipo' => 'feitico', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'efeito' => 'dano', 'valor' => 1,
                          'palavras' => '', 'descricao' => 'Crie duas fichas de constructo 2/2'],
            'sopro' => ['nome' => 'Sopro', 'tipo' => 'feitico', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'efeito' => null, 'valor' => 0,
                        'palavras' => '', 'descricao' => 'Algo que o jogo não entende'],
        ];
        $e = Gladio::iniciar($cartas, 10, ['a' => array_merge(['mascara', 'sopro'], array_fill(0, 18, '1')), 'b' => array_fill(0, 20, '1')], fn ($a) => $a, [], ['a' => '1', 'b' => '1']);
        $e['lados']['a']['energia'] = 5;
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'mascara'));
        $this->assertSame([['Constructo', 2, 2], ['Constructo', 2, 2]],
            array_map(fn ($m) => [$m['nome'], $m['atk'], $m['vida']], $e['lados']['a']['mesa']));
        $this->assertSame(10, $e['lados']['b']['vida'], 'o "dano 1" antigo não acontece');
        // texto não entendido: é conjurado, vai para o cemitério e não faz nada; o efeito "escolhido" é ignorado
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'sopro'), 'inimigo', true, 'dano', 9);
        $this->assertSame(10, $e['lados']['b']['vida']);
        $this->assertSame(3, $e['lados']['a']['energia'], 'pagou o custo mesmo pedindo "grátis"');
        $this->assertSame(['mascara', 'sopro'], array_column($e['lados']['a']['cemiterio'], 'c'));
    }

    /* ---------- vários jogadores, pote, shots e ferramentas novas ---------- */

    private function tres(int $aposta = 10): array
    {
        $deck = array_fill(0, 20, '1');

        return Gladio::iniciar(self::CARTAS, $aposta, ['a' => $deck, 'b' => $deck, 'c' => $deck], fn ($x) => $x, ['a' => 'Ana', 'b' => 'Bia', 'c' => 'Caio']);
    }

    public function test_tres_jogadores_em_roda_e_ataque_escolhe_o_alvo(): void
    {
        $e = $this->tres();
        $this->assertSame(['a', 'b', 'c'], $e['ordem']);
        $this->assertSame(30, $e['total']);
        $e = Gladio::passar($e, 'a');
        $this->assertSame('b', $e['vez']);
        $e = Gladio::passar($e, 'b');
        $this->assertSame('c', $e['vez']);
        $e = Gladio::passar($e, 'c');
        $this->assertSame(['a', 2], [$e['vez'], $e['lados']['a']['energiaMax']], 'volta para a Ana com +1 de energia');

        [$e, $g] = $this->por($e, 'a', 'Gladiador', 3, 2);
        try {
            Gladio::atacar($e, 'a', [$g]);
            $this->fail('com dois adversários precisa escolher o alvo');
        } catch (\InvalidArgumentException) {
        }
        $e = Gladio::atacar($e, 'a', [$g], 'c');
        $this->assertSame('c', $e['ataque']['defensor']);
        try {
            Gladio::defender($e, 'b');
            $this->fail('quem não foi atacado defendeu');
        } catch (\InvalidArgumentException) {
        }
        $e = Gladio::defender($e, 'c');
        $this->assertSame([10, 7], [$e['lados']['b']['vida'], $e['lados']['c']['vida']]);
        $this->assertSame(3, Gladio::visao($e, 'a')['pote'], 'a vida perdida vai para o pote');
    }

    public function test_eliminado_sai_da_roda_e_ultimo_de_pe_vence(): void
    {
        $e = $this->tres(5);
        $e['lados']['b']['vida'] = 0;
        $e = Gladio::passar($e, 'a'); // b começaria, mas está sem vida: conferir tira b
        $this->assertTrue($e['lados']['b']['fora']);
        $this->assertNull($e['fim']);
        $this->assertSame('c', $e['vez'], 'quem saiu é pulado');
        $e = Gladio::desistir($e, 'c');
        $this->assertSame(['vencedor' => 'a', 'motivo' => 'desistencia'], $e['fim']);
        $this->assertSame(10, Gladio::pote($e), 'Ana leva o pote: as apostas de Bia e Caio');
    }

    public function test_shots_a_qualquer_momento_e_o_setimo_elimina(): void
    {
        $e = $this->tres();
        $e = Gladio::shot($e, 'b', 2); // fora da vez dela
        $e = Gladio::shot($e, 'b', -1);
        $this->assertSame(1, $e['lados']['b']['shots']);
        for ($n = 0; $n < 6; $n++) {
            $e = Gladio::shot($e, 'b');
        }
        $this->assertTrue($e['lados']['b']['fora']);
        $this->assertStringContainsString('7 shots', implode(' ', $e['log']));
        $this->assertNull($e['fim'], 'ainda sobram dois');
    }

    public function test_revelar_carta_da_mao_e_descartar_fora_da_vez(): void
    {
        $e = $this->novo();
        $carta = $e['lados']['b']['mao'][0]['i'];
        $e = Gladio::revelar($e, 'b', $carta); // não é a vez dela
        $v = Gladio::visao($e, 'a');
        $this->assertSame(['i' => $carta, 'c' => '1', 'rev' => true], $v['lados']['b']['mao'][0]);
        $this->assertSame(['oculta' => true], $v['lados']['b']['mao'][1]);
        $e = Gladio::descartar($e, 'b', $carta);
        $this->assertCount(4, $e['lados']['b']['mao']);
        $this->assertSame([$carta], array_column($e['lados']['b']['cemiterio'], 'i'));
    }

    public function test_ajustar_lenda_inimiga_contadores_e_anotacoes(): void
    {
        [$e, $inimiga] = $this->por($this->novo(), 'b', 'Fera', 3, 3);
        $e = Gladio::ajustar($e, 'a', $inimiga, -1, -1);
        $this->assertSame([2, 2], [$e['lados']['b']['mesa'][0]['atk'], $e['lados']['b']['mesa'][0]['vida']]);
        $e = Gladio::marcar($e, 'b', $inimiga, 3, 'envenenada');
        $e = Gladio::marcar($e, 'a', $inimiga, -1);
        $this->assertSame([2, 'envenenada'], [$e['lados']['b']['mesa'][0]['cont'], $e['lados']['b']['mesa'][0]['nota']]);
        $this->expectException(\InvalidArgumentException::class);
        Gladio::marcar($e, 'a', 999, 1);
    }

    public function test_anotacao_numa_carta_em_jogo(): void
    {
        [$e, $lenda] = $this->por($this->novo(), 'a', 'Fera', 3, 3);
        $e = Gladio::marcar($e, 'b', $lenda, 0, 'envenenada');
        $this->assertSame('envenenada', $e['lados']['a']['mesa'][0]['nota']);
        $this->assertSame(3, $e['lados']['a']['mesa'][0]['atk'], 'a anotação não muda nada no jogo');
    }

    public function test_estado_antigo_de_dois_jogadores_continua_valendo(): void
    {
        $e = $this->novo();
        unset($e['ordem'], $e['total'], $e['nomes']);
        foreach (['a', 'b'] as $l) {
            unset($e['lados'][$l]['feiticos'], $e['lados'][$l]['shots'], $e['lados'][$l]['fora']);
        }
        $e = Gladio::completar($e);
        $this->assertSame(['a', 'b'], $e['ordem']);
        $this->assertSame(20, $e['total']);
        $e = Gladio::passar($e, 'a');
        $this->assertSame('b', $e['vez']);
    }

    public function test_visao_esconde_mao_e_baralho_do_outro(): void
    {
        $v = Gladio::visao($this->novo(), 'a');
        $this->assertIsArray($v['lados']['a']['mao']);
        $this->assertIsArray($v['lados']['a']['baralho'], 'cada um consulta o próprio baralho');
        $this->assertSame(array_fill(0, 5, ['oculta' => true]), $v['lados']['b']['mao'], 'a mão do outro vem virada');
        $this->assertSame(14, $v['lados']['b']['baralho']);
        $this->assertIsArray($v['lados']['b']['cemiterio'], 'cemitério é público');
    }

    public function test_comandante_escolhido_fica_no_comando_e_paga_taxa(): void
    {
        $deck = array_merge(['4'], array_fill(0, 19, '1'));
        $e = Gladio::iniciar(self::CARTAS, 10, ['a' => $deck, 'b' => array_fill(0, 20, '1')], fn ($a) => $a, [], ['a' => '4']);
        $a = $e['lados']['a'];
        $this->assertSame(['4'], array_column($a['comando'], 'c'), 'o comandante começa na zona de comando');
        $this->assertNotContains('4', array_column(array_merge($a['mao'], $a['baralho']), 'c'), 'e sai do baralho');
        $this->assertSame(19, count($a['mao']) + count($a['baralho']));
        $this->assertSame('1', $e['lados']['b']['comando'][0]['c'], 'sem escolha: uma Lenda do deck');
        $this->assertSame(1, Gladio::custoComandante($e, 'a'));

        $e = Gladio::comandar($e, 'a');
        $cmd = $e['lados']['a']['cmd'];
        $this->assertSame([$cmd], array_column($e['lados']['a']['mesa'], 'i'));
        $this->assertSame([0, 1], [$e['lados']['a']['energia'], $e['lados']['a']['taxa']]);
        // caiu: volta para a zona de comando, e agora custa 1 + 2
        $e = Gladio::ajustar($e, 'b', $cmd, 0, -99);
        $this->assertSame([], $e['lados']['a']['mesa']);
        $this->assertSame([], $e['lados']['a']['cemiterio']);
        $this->assertSame([$cmd], array_column($e['lados']['a']['comando'], 'i'));
        $this->assertSame(3, Gladio::custoComandante($e, 'a'));
        try {
            Gladio::comandar($e, 'a');
            $this->fail('energia insuficiente para a taxa');
        } catch (\InvalidArgumentException) {
        }
        // sem pagar, a qualquer momento (a taxa sobe igual); sacrificado, volta ao comando
        $e = Gladio::invocar($e, 'a', $cmd, 'comando');
        $this->assertSame(2, $e['lados']['a']['taxa']);
        $e = Gladio::sacrificar($e, 'a', $cmd);
        $this->assertSame([$cmd], array_column($e['lados']['a']['comando'], 'i'));
        // se parar no cemitério (eliminado), dá para devolvê-lo ao comando
        $e['lados']['a']['cemiterio'][] = array_pop($e['lados']['a']['comando']);
        try {
            Gladio::paraComando($e, 'a', $e['lados']['a']['mao'][0]['i']);
            $this->fail('só o comandante vai para o comando');
        } catch (\InvalidArgumentException) {
        }
        $e = Gladio::paraComando($e, 'a', $cmd);
        $this->assertSame([], $e['lados']['a']['cemiterio']);
    }

    public function test_mesa_livre_energia_vida_buscar_e_invocar(): void
    {
        $e = $this->novo(10, ['b' => array_merge(['2', '5'], array_fill(0, 18, '1'))]);
        // fora da vez, a Bia mexe na própria energia e na vida
        $e = Gladio::energia($e, 'b', 3, 12);
        $this->assertSame([3, 12], [$e['lados']['b']['energia'], $e['lados']['b']['energiaMax']]);
        $e = Gladio::vida($e, 'b', -4);
        $this->assertSame(6, $e['lados']['b']['vida']);
        $this->assertSame(4, Gladio::pote($e), 'a vida perdida vai para o pote');
        $e = Gladio::vida($e, 'b', 99);
        $this->assertSame(105, $e['lados']['b']['vida'], 'ganha 4 do pote e 95 de dinheiro novo: pode passar do apostado');
        $this->assertSame(0, Gladio::pote($e));
        // Lendas só entram no próprio turno: a vez passa para a Bia; aí ela invoca sem custo da mão e do cemitério e busca no baralho
        $e = Gladio::passar($e, 'a');
        $corredor = $this->primeira($e, 'b', '5');
        $e = Gladio::invocar($e, 'b', $corredor, 'mao');
        $this->assertSame([$corredor], array_column($e['lados']['b']['mesa'], 'i'));
        $fogo = $this->primeira($e, 'b', '2');
        $e = Gladio::descartar($e, 'b', $fogo);
        $e = Gladio::invocar($e, 'b', $fogo, 'cemiterio');
        $this->assertSame([$fogo], array_column($e['lados']['b']['feiticos'], 'i'), 'feitiço vai para o campo, sem efeito');
        $this->assertSame(10, $e['lados']['a']['vida']);
        $alvo = end($e['lados']['b']['baralho'])['i'];
        $e = Gladio::buscar($e, 'b', $alvo);
        $this->assertSame($alvo, end($e['lados']['b']['mao'])['i']);
        // energia máxima acima de 10 não caiu na virada do turno
        $this->assertSame(12, $e['lados']['b']['energiaMax']);
        // vida zerada à mão: está fora
        $e = Gladio::vida($e, 'a', -10);
        $this->assertSame('b', $e['fim']['vencedor']);
    }

    /* ---------- fases e textos das cartas ---------- */

    private const TEXTOS = [
        'barill' => ['nome' => 'Barill', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 4, 'palavras' => '',
                     'descricao' => 'Quando invocado, sacrifique uma lenda, compre duas cartas'],
        'cabrito' => ['nome' => 'Cabrito', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 1, 'vida' => 1, 'palavras' => '',
                      'descricao' => 'Quando morrer, recupere 30$'],
        'daiki' => ['nome' => 'Daiki', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 1, 'vida' => 1, 'palavras' => '',
                    'descricao' => 'Todo início de turno, todo jogador perde 10$'],
        'brinde' => ['nome' => 'Brinde', 'tipo' => 'feitico', 'custo' => 0, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                     'descricao' => 'O jogador alvo vira um shot e compra uma carta'],
        'chorao' => ['nome' => 'Chorão', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 0, 'vida' => 2, 'palavras' => 'voar,resistencia',
                     'descricao' => 'Aplique hexo-imune a uma Lenda alvo'],
        'abel' => ['nome' => 'Abel', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 3, 'vida' => 1, 'palavras' => 'iniciativa',
                   'descricao' => 'Saque uma propriedade fisiomante de seu baralho'],
        'prop' => ['nome' => 'Propriedade fisiomante: Sangue', 'tipo' => 'feitico', 'custo' => 0, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                   'descricao' => 'Algo que o leitor não entende'],
        'raio' => ['nome' => 'Raio', 'tipo' => 'feitico', 'custo' => 0, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                   'descricao' => 'Cause 3 de dano a uma Lenda inimiga.'],
        'tiinho' => ['nome' => 'Tiinho', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 1, 'vida' => 1, 'palavras' => '',
                     'descricao' => 'Todo início de turno, invoque um Cabrito'],
        'kaique' => ['nome' => 'Kaique', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 1, 'vida' => 1, 'palavras' => '',
                     'descricao' => "Quando Kaique entrar ou morrer, invoque uma ficha de carniçal 2/2 com Toque mortífero\r\nSe equipado com Amplificação, os carniçais são 5/5"],
        'dividido' => ['nome' => 'O Dividido', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 1, 'vida' => 1, 'palavras' => '',
                       'descricao' => 'jogue ao cemitério as 10 primeiras cartas do seu deck. Todo início de turno, ative algum feitiço no seu cemitério sem pagar os custos.'],
        'aguinha' => ['nome' => 'Aguinha', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 1, 'vida' => 1, 'palavras' => '',
                      'descricao' => 'Quando invocado ou quando atacar, mate uma lenda alvo. Se essa lenda morrer, devolva as lendas mortas dessa forma.'],
        'espada' => ['nome' => 'Espada', 'tipo' => 'feitico', 'custo' => 0, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                     'descricao' => 'Equipe a uma lenda sua: ela ganha +2/+1 e voar.'],
        'faxina' => ['nome' => 'Faxina', 'tipo' => 'feitico', 'custo' => 0, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                     'descricao' => 'Descarte sua mão.'],
        'tesouro' => ['nome' => 'Tesouro', 'tipo' => 'feitico', 'custo' => 0, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                      'descricao' => 'Recupere 20$.'],
        'papelada' => ['nome' => 'Hexo-papelada', 'tipo' => 'feitico', 'custo' => 0, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                       'descricao' => 'Compre uma carta, descarte uma carta, pague 10$'],
        'confia' => ['nome' => 'Confiança', 'tipo' => 'encanto', 'custo' => 0, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                     'descricao' => 'Todo início de turno, suas lendas ganham +0/+2'],
        'manto' => ['nome' => 'Manto', 'tipo' => 'equipo', 'custo' => 0, 'ataque' => 1, 'vida' => 2, 'palavras' => 'voar',
                    'descricao' => ''],
        'diego' => ['nome' => 'Diego', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 3, 'vida' => 2, 'palavras' => '',
                    'descricao' => 'Ao invocar Diego, transforme-o em uma cópia de uma lenda que você controlar.'],
        'selos' => ['nome' => 'Selos de Lucius', 'tipo' => 'encanto', 'custo' => 0, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                    'descricao' => 'Todo início de turno, você pode sacrificar uma lenda e ganhar 2 de energia'],
        'abner' => ['nome' => 'Abner', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 0, 'vida' => 12, 'palavras' => 'defensor',
                    'descricao' => 'Quando bloquear, mate a Lenda atacante'],
        'cicero' => ['nome' => 'Cícero', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 4, 'vida' => 4, 'palavras' => '',
                     'descricao' => 'Quando matar uma lenda, ganhe uma aura e adicione +2/+2 a cícero'],
        'pedra' => ['nome' => 'Pedra', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 1, 'vida' => 2, 'palavras' => '',
                    'descricao' => 'Toda vez que uma lenda sua morrer, compre uma carta e adicione 1 de ataque a essa lenda'],
        'baguinho' => ['nome' => 'Baguinho', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 1, 'vida' => 1, 'palavras' => '',
                       'descricao' => 'Sempre que um feitiço for lançado, o jogador que o lançou paga 5$, exceto você.'],
        'carneiro' => ['nome' => 'Carneiro', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 5, 'vida' => 3, 'palavras' => '',
                       'descricao' => 'Sempre que uma Lenda morrer, roube 10$.'],
        'armeiro' => ['nome' => 'Hexo-armeiro', 'tipo' => 'feitico', 'custo' => 0, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                      'descricao' => 'Duas cartas alvo ganham uma palavra chave a sua escolha'],
        'gosma' => ['nome' => 'Mãos de gosma', 'tipo' => 'feitico', 'custo' => 0, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                    'descricao' => 'A lenda alvo não pode atacar até que morra'],
        'lamina' => ['nome' => 'Lâmina envenenada', 'tipo' => 'feitico', 'custo' => 0, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                     'descricao' => 'A Lenda alvo não causa mais dano até morrer, e sofre 3 de dano'],
        'ruina' => ['nome' => 'Ruína', 'tipo' => 'feitico', 'custo' => 0, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                    'descricao' => 'Destrua todas as cartas em campo'],
        'troca' => ['nome' => 'Troca', 'tipo' => 'feitico', 'custo' => 0, 'ataque' => 0, 'vida' => 0, 'palavras' => '',
                    'descricao' => 'Troque de mão com o jogador alvo'],
        'armazem' => ['nome' => 'O Armazém', 'tipo' => 'lenda', 'custo' => 0, 'ataque' => 2, 'vida' => 1, 'palavras' => '',
                      'descricao' => 'Todo início de turno, todo jogador mill X, onde X é o poder dessa Lenda'],
    ] + self::CARTAS;

    public function test_diego_vira_copia_de_uma_lenda_sua(): void
    {
        $e = $this->comTextos(['diego']);
        [$e, $modelo] = $this->por($e, 'a', 'Gigante', 7, 7, ['voar']);
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'diego'));
        $this->assertSame([$modelo], $e['escolha']['opcoes'], 'só as outras Lendas suas servem de modelo');
        $e = Gladio::escolher($e, 'a', $modelo);
        $diego = end($e['lados']['a']['mesa']);
        $this->assertSame(['Gigante', 7, 7, ['voar']], [$diego['nome'], $diego['atk'], $diego['max'], $diego['pal']]);
    }

    public function test_voce_pode_confirma_ou_recusa_a_frase_inteira(): void
    {
        $e = $this->comTextos(['selos']);
        [$e, $lenda] = $this->por($e, 'a', 'Bicho', 1, 1);
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'selos'));
        $e = Gladio::passar(Gladio::passar($e, 'a'), 'b');
        $this->assertSame('confirmar', $e['escolha']['tipo']);
        $energia = $e['lados']['a']['energia'];
        $e = Gladio::escolher($e, 'a', null); // recusou: não sacrifica nem ganha energia
        $this->assertCount(1, $e['lados']['a']['mesa']);
        $this->assertSame($energia, $e['lados']['a']['energia']);
        $e = Gladio::passar(Gladio::passar($e, 'a'), 'b');
        $energia = $e['lados']['a']['energia']; // recarregou de novo no turno seguinte
        $e = Gladio::escolher($e, 'a', 'sim');
        $e = Gladio::escolher($e, 'a', $lenda);
        $this->assertSame([], $e['lados']['a']['mesa']);
        $this->assertSame($energia + 2, $e['lados']['a']['energia']);
    }

    public function test_gatilhos_de_bloquear_matar_morrer_e_feitico_de_outro(): void
    {
        $e = $this->comTextos(['cicero', 'pedra'], ['abner', 'baguinho', 'carneiro']);
        $e = $this->lendaPronta($e, 'a', 'cicero');
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'pedra'));
        [$e, $bicho] = $this->por($e, 'a', 'Bicho', 1, 1);
        $e = Gladio::passar($e, 'a');
        $e = Gladio::jogar($e, 'b', $this->primeira($e, 'b', 'abner'));
        $e = Gladio::jogar($e, 'b', $this->primeira($e, 'b', 'baguinho'));
        $e = Gladio::jogar($e, 'b', $this->primeira($e, 'b', 'carneiro'));
        $e = Gladio::passar($e, 'b');
        // Cícero ataca; Abner bloqueia e mata a Lenda atacante (Cícero não mata ninguém)
        $cicero = $e['lados']['a']['mesa'][0]['i'];
        $abner = $e['lados']['b']['mesa'][0]['i'];
        $e = Gladio::atacar($e, 'a', [$cicero]);
        $e = Gladio::bloquear($e, 'b', $abner, $cicero);
        $mao = count($e['lados']['a']['mao']);
        $vidaB = $e['lados']['b']['vida'];
        $e = Gladio::defender($e, 'b');
        $this->assertNotContains('cicero', array_column($e['lados']['a']['mesa'], 'c'), 'Abner matou Cícero');
        $this->assertSame($mao + 1, count($e['lados']['a']['mao']), 'Pedra: uma Lenda sua morreu, comprou');
        $this->assertSame(2, $e['lados']['a']['mesa'][0]['atk'], 'Pedra ganhou +1 de ataque');
        $this->assertSame($vidaB + 10, $e['lados']['b']['vida'], 'Carneiro roubou 10 de quem perdeu a Lenda');
        $this->assertSame(99 - 10, $e['lados']['a']['vida']);
        // feitiço de a: Baguinho (de b) cobra 5$ de quem conjurou
        $e['lados']['a']['mao'][] = ['i' => 900, 'c' => 'raio'];
        $e = Gladio::jogar($e, 'a', 900);
        $e = Gladio::escolher($e, 'a', null);
        $this->assertSame(84, $e['lados']['a']['vida']);
    }

    public function test_cicero_mata_em_combate_e_cresce(): void
    {
        $e = $this->comTextos(['cicero']);
        $e = $this->lendaPronta($e, 'a', 'cicero');
        [$e, $fraco] = $this->por($e, 'b', 'Fraco', 1, 1);
        $cicero = $e['lados']['a']['mesa'][0]['i'];
        $e = Gladio::atacar($e, 'a', [$cicero]);
        $e = Gladio::bloquear($e, 'b', $fraco, $cicero);
        $energia = $e['lados']['a']['energia'];
        $e = Gladio::defender($e, 'b');
        $this->assertSame([6, 6, $energia + 1], [$e['lados']['a']['mesa'][0]['atk'], $e['lados']['a']['mesa'][0]['max'], $e['lados']['a']['energia']]);
    }

    public function test_feiticos_de_palavra_chave_prender_zerar_trocar_e_ruina(): void
    {
        $e = $this->comTextos(['armeiro', 'gosma', 'lamina', 'troca', 'ruina']);
        [$e, $x] = $this->por($e, 'a', 'X', 3, 5);
        [$e, $y] = $this->por($e, 'b', 'Y', 3, 5);
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'armeiro'));
        $e = Gladio::escolher($e, 'a', $x);
        $this->assertSame('palavra', $e['escolha']['tipo']);
        $e = Gladio::escolher($e, 'a', 'voar');
        $e = Gladio::escolher($e, 'a', $y);
        $e = Gladio::escolher($e, 'a', 'toque');
        $this->assertSame([['voar'], ['toque']], [$e['lados']['a']['mesa'][0]['pal'], $e['lados']['b']['mesa'][0]['pal']]);
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'gosma'));
        $e = Gladio::escolher($e, 'a', $y);
        $this->assertTrue($e['lados']['b']['mesa'][0]['preso']);
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'lamina'));
        $e = Gladio::escolher($e, 'a', $y);
        $this->assertSame([0, 2], [$e['lados']['b']['mesa'][0]['atk'], $e['lados']['b']['mesa'][0]['vida']], 'zerou o ataque e sofreu 3 na mesma Lenda');
        $maoA = array_column($e['lados']['a']['mao'], 'c');
        $maoB = array_column($e['lados']['b']['mao'], 'c');
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'troca'));
        $this->assertSame([$maoB, array_values(array_diff($maoA, ['troca']))], [array_column($e['lados']['a']['mao'], 'c'), array_column($e['lados']['b']['mao'], 'c')]);
        $e['lados']['a']['mao'][] = ['i' => 901, 'c' => 'ruina'];
        $e = Gladio::jogar($e, 'a', 901);
        $this->assertSame([[], []], [$e['lados']['a']['mesa'], $e['lados']['b']['mesa']]);
    }

    public function test_x_e_o_poder_da_lenda_e_tempo_da_vez(): void
    {
        $e = $this->comTextos(['armazem']);
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'armazem'));
        $baralhoB = count($e['lados']['b']['baralho']);
        $e = Gladio::passar(Gladio::passar($e, 'a'), 'b');
        $this->assertSame($baralhoB - 3, count($e['lados']['b']['baralho']), 'b comprou 1 no turno dela e mill X com X = 2 (o ataque do Armazém)');
        // tempo esgotado: o turno de a passa sozinho
        try {
            Gladio::tempo($e, 'b', $e['desde'] + 10);
            $this->fail('ainda no tempo');
        } catch (\InvalidArgumentException) {
        }
        $e = Gladio::tempo($e, 'b', $e['desde'] + Gladio::TEMPO_VEZ);
        $this->assertSame('b', $e['vez']);
    }

    public function test_hexo_papelada_compra_descarta_e_paga(): void
    {
        $e = $this->comTextos(['papelada']);
        $mao = count($e['lados']['a']['mao']);
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'papelada'));
        $this->assertSame(['a', 'mao'], [$e['escolha']['lado'], $e['escolha']['tipo']], 'comprou e pede o descarte da mão');
        $this->assertCount($mao, $e['lados']['a']['mao'], 'jogou uma, comprou uma');
        $e = Gladio::escolher($e, 'a', $e['escolha']['opcoes'][0]);
        $this->assertCount($mao - 1, $e['lados']['a']['mao']);
        $this->assertSame(89, $e['lados']['a']['vida'], 'pagou 10$');
        $this->assertSame('papelada', $e['lados']['a']['cemiterio'][0]['c'], 'feitiço vai para o cemitério');
    }

    public function test_tipos_encantamento_fica_e_equipamento_usa_campos_e_palavras(): void
    {
        $e = $this->comTextos(['confia', 'manto']);
        $e = $this->lendaPronta($e, 'a', '1');
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'confia'));
        $this->assertSame('confia', end($e['lados']['a']['feiticos'])['c'], 'encantamento fica no campo');
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'manto'));
        $lenda = $e['lados']['a']['mesa'][0]['i'];
        $e = Gladio::escolher($e, 'a', $lenda);
        $m = $e['lados']['a']['mesa'][0];
        $this->assertSame([4, 4], [$m['atk'], $m['max']], 'bônus de ataque/vida do equipamento');
        $this->assertContains('voar', $m['pal'], 'palavra-chave da carta de equipamento');
        // o encantamento age no início do turno do dono
        $e = Gladio::passar(Gladio::passar($e, 'a'), 'b');
        $this->assertSame(6, $e['lados']['a']['mesa'][0]['max']);
    }

    public function test_sem_limite_na_mesa_e_palavra_chave_a_mao(): void
    {
        $e = $this->novo();
        for ($k = 0; $k < 10; $k++) {
            $e = Gladio::ficha($e, 'a', 'F', 1, 1);
        }
        $this->assertCount(10, $e['lados']['a']['mesa']);
        $i = $e['lados']['a']['mesa'][0]['i'];
        $e = Gladio::palavra($e, 'b', $i, 'voar');
        $this->assertContains('voar', $e['lados']['a']['mesa'][0]['pal']);
        $e = Gladio::palavra($e, 'b', $i, 'voar');
        $this->assertNotContains('voar', $e['lados']['a']['mesa'][0]['pal'], 'clicar de novo tira');
    }

    public function test_uma_onda_de_ataque_por_turno(): void
    {
        $e = $this->lendaPronta($this->novo(), 'a', '1');
        $e = $this->lendaPronta($e, 'a', '1');
        [$x, $y] = array_column($e['lados']['a']['mesa'], 'i');
        $e = Gladio::atacar($e, 'a', [$x]);
        $e = Gladio::defender($e, 'b');
        try {
            Gladio::atacar($e, 'a', [$y]);
            $this->fail('depois da onda de ataque não dá para atacar de novo no mesmo turno');
        } catch (\InvalidArgumentException $ex) {
            $this->assertStringContainsString('já atacou', $ex->getMessage());
        }
        $e = Gladio::passar(Gladio::passar($e, 'a'), 'b');
        $this->assertFalse($e['atacou'], 'no turno seguinte pode atacar de novo');
    }

    public function test_recuperar_dinheiro_com_pote_vazio_da_mesmo_assim(): void
    {
        $e = $this->comTextos(['tesouro', 'tesouro'], [], 100);
        $this->assertSame(0, Gladio::pote($e));
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'tesouro'));
        $this->assertSame(120, $e['lados']['a']['vida'], 'o pote estava vazio e ela recebeu os 20 mesmo assim');
        $this->assertSame(0, Gladio::pote($e), 'o dinheiro novo não sai do pote');
        // com 10 no pote: 10 saem dele e 10 entram de novo
        $e['lados']['b']['vida'] = 90;
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'tesouro'));
        $this->assertSame([140, 0], [$e['lados']['a']['vida'], Gladio::pote($e)]);
    }

    public function test_descarte_e_sempre_da_mao(): void
    {
        $e = $this->comTextos(['faxina', 'cabrito', 'barill']);
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'cabrito'));
        $mesa = count($e['lados']['a']['mesa']);
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'faxina'));
        $this->assertSame([], $e['lados']['a']['mao'], 'a mão inteira foi para o cemitério');
        $this->assertCount($mesa, $e['lados']['a']['mesa'], 'o campo não foi tocado');
        $this->assertNull($e['escolha']);
    }

    public function test_equipamento_fica_vinculado_a_lenda(): void
    {
        $e = $this->comTextos(['espada', 'espada']);
        $e = $this->lendaPronta($e, 'a', '1');
        $lenda = end($e['lados']['a']['mesa'])['i'];
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'espada'));
        $this->assertSame(['a', 'lenda', [$lenda]], [$e['escolha']['lado'], $e['escolha']['tipo'], $e['escolha']['opcoes']]);
        $e = Gladio::escolher($e, 'a', $lenda);
        $m = end($e['lados']['a']['mesa']);
        $this->assertSame([5, 3, 3], [$m['atk'], $m['vida'], $m['max']]);
        $this->assertContains('voar', $m['pal']);
        $f = end($e['lados']['a']['feiticos']);
        $this->assertSame($lenda, $f['em'], 'a Espada fica no campo vinculada à Lenda');
        // mandar a Espada ao cemitério tira o bônus
        $e = Gladio::encerrarFeitico($e, 'a', $f['i']);
        $m = end($e['lados']['a']['mesa']);
        $this->assertSame([3, 2], [$m['atk'], $m['max']]);
        $this->assertNotContains('voar', $m['pal']);
        // a outra Espada cai junto com a Lenda
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'espada'));
        $e = Gladio::escolher($e, 'a', $lenda);
        $e = Gladio::sacrificar($e, 'a', $lenda);
        $this->assertSame([], $e['lados']['a']['feiticos']);
        $this->assertSame(['espada', 'espada', '1'], array_slice(array_column($e['lados']['a']['cemiterio'], 'c'), -3));
    }

    public function test_apressar_quem_demora(): void
    {
        $e = $this->novo();
        try {
            Gladio::apressar($e, 'b', $e['desde'] + 10);
            $this->fail('ainda no tempo');
        } catch (\InvalidArgumentException) {
        }
        try {
            Gladio::apressar($e, 'a', $e['desde'] + 100);
            $this->fail('quem tem a vez não se apressa');
        } catch (\InvalidArgumentException) {
        }
        $e = Gladio::apressar($e, 'b', $e['desde'] + 100);
        $this->assertSame(['b', 'a'], [$e['apressado']['por'], $e['apressado']['alvo']]);
        $this->assertStringContainsString('apressando', end($e['log']));
    }

    private function comTextos(array $a, array $b = [], int $aposta = 99): array
    {
        $a = array_merge($a, array_fill(0, 20 - count($a), '1'));
        $b = array_merge($b, array_fill(0, 20 - count($b), '1'));

        return Gladio::iniciar(self::TEXTOS, $aposta, ['a' => $a, 'b' => $b], fn ($x) => $x, [], ['a' => '1', 'b' => '1']);
    }

    public function test_fases_compra_automatica_limite_de_mao_e_limpeza(): void
    {
        $e = $this->comTextos([], ['4']);
        $this->assertSame('principal', $e['fase']);
        // Lenda ferida: o dano some na limpeza do fim do turno
        $e = $this->lendaPronta($e, 'a', '1');
        $e['lados']['a']['mesa'][0]['vida'] = 1;
        $e = Gladio::passar($e, 'a');
        $this->assertSame(2, $e['lados']['a']['mesa'][0]['vida'], 'o dano nas Lendas some no fim do turno');
        $this->assertSame(['b', 'principal'], [$e['vez'], $e['fase']], 'a compra é automática');
        $this->assertCount(6, $e['lados']['b']['mao']);
        // com 9 cartas no fim do turno, descarta 2 (não dá para pular) antes de passar a vez
        $e['lados']['b']['mao'] = array_merge($e['lados']['b']['mao'], array_slice($e['lados']['b']['baralho'], 0, 3));
        $e['lados']['b']['baralho'] = array_slice($e['lados']['b']['baralho'], 3);
        $e = Gladio::passar($e, 'b');
        $this->assertSame(['b', 'final', 'mao'], [$e['vez'], $e['fase'], $e['escolha']['tipo']]);
        try {
            Gladio::escolher($e, 'b', null);
            $this->fail('o descarte do fim do turno é obrigatório');
        } catch (\InvalidArgumentException) {
        }
        $e = Gladio::escolher($e, 'b', $e['escolha']['opcoes'][0]);
        $this->assertSame('mao', $e['escolha']['tipo'], 'falta mais um');
        $e = Gladio::escolher($e, 'b', $e['escolha']['opcoes'][0]);
        $this->assertSame(['a', 'principal', null], [$e['vez'], $e['fase'], $e['escolha']]);
        $this->assertCount(Gladio::MAO_FIM, $e['lados']['b']['mao']);
        $this->assertCount(2, $e['lados']['b']['cemiterio']);
    }

    public function test_quando_invocado_sacrifica_e_compra_e_quando_morrer_recupera(): void
    {
        $e = $this->comTextos(['cabrito', 'barill']);
        $e['lados']['a']['vida'] = 50;
        $e['lados']['a']['energia'] = 5;
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'cabrito'));
        $cabrito = end($e['lados']['a']['mesa'])['i'];
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'barill'));
        $this->assertSame(['a', 'lenda'], [$e['escolha']['lado'], $e['escolha']['tipo']]);
        try {
            Gladio::passar($e, 'a');
            $this->fail('com uma escolha pendente, o jogo espera');
        } catch (\InvalidArgumentException) {
        }
        $mao = count($e['lados']['a']['mao']);
        $e = Gladio::escolher($e, 'a', $cabrito);
        $this->assertSame(['barill'], array_column($e['lados']['a']['mesa'], 'c'));
        $this->assertSame(80, $e['lados']['a']['vida'], 'Cabrito caiu e recuperou 30');
        $this->assertSame($mao + 2, count($e['lados']['a']['mao']), 'Barill comprou duas');
        $this->assertNull($e['escolha']);
    }

    public function test_feitico_com_jogador_alvo_e_inicio_de_turno(): void
    {
        $e = $this->comTextos(['brinde', 'daiki']);
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'brinde'));
        $this->assertSame(['j:a', 'j:b'], $e['escolha']['opcoes']);
        $maoB = count($e['lados']['b']['mao']);
        $e = Gladio::escolher($e, 'a', 'j:b');
        $this->assertSame(1, $e['lados']['b']['shots']);
        $this->assertSame($maoB + 1, count($e['lados']['b']['mao']), 'o mesmo jogador alvo compra');
        $this->assertSame('brinde', end($e['lados']['a']['cemiterio'])['c'], 'feitiço resolvido vai para o cemitério');
        // Daiki: "todo início de turno, todo jogador perde 10" vale só no início do turno do dono (a)
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'daiki'));
        $e = Gladio::passar($e, 'a');
        $this->assertSame([99, 99], [$e['lados']['a']['vida'], $e['lados']['b']['vida']], 'no turno de b não dispara');
        $e = Gladio::passar($e, 'b');
        $this->assertSame([89, 89], [$e['lados']['a']['vida'], $e['lados']['b']['vida']]);
    }

    public function test_hexo_imune_concedido_protege_dos_alvos_do_adversario(): void
    {
        $e = $this->comTextos(['raio'], ['chorao', 'abel', '1', '1', '1', '1', 'prop']);
        $e = Gladio::passar($e, 'a');
        $e = Gladio::jogar($e, 'b', $this->primeira($e, 'b', 'abel'));
        // Abel: "saque uma propriedade fisiomante": só a carta que combina com o texto
        $this->assertSame('baralho', $e['escolha']['tipo']);
        $this->assertCount(1, $e['escolha']['opcoes']);
        $e = Gladio::escolher($e, 'b', $e['escolha']['opcoes'][0]);
        $this->assertSame('prop', end($e['lados']['b']['mao'])['c']);
        $abel = end($e['lados']['b']['mesa'])['i'];
        $e = Gladio::jogar($e, 'b', $this->primeira($e, 'b', 'chorao'));
        $e = Gladio::escolher($e, 'b', $abel);
        $this->assertContains('resistencia', $e['lados']['b']['mesa'][0]['pal']);
        $e = Gladio::passar($e, 'b');
        // o Raio de a só pode mirar Lendas inimigas sem Hexo-Imune: nenhuma sobra
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'raio'));
        $this->assertNull($e['escolha']);
        $this->assertSame(1, $e['lados']['b']['mesa'][0]['vida']);
    }

    public function test_leitor_deixa_condicoes_a_mao_e_entende_varios_gatilhos(): void
    {
        $e = $this->comTextos([]);
        $this->assertSame(['entrar', 'atacar', 'morrer'], array_column($e['cartas']['aguinha']['hab'], 'g'), 'Aguinha tem roteiro');
        $this->assertFalse($e['cartas']['aguinha']['parcial']);
        $this->assertSame(['entrar', 'morrer'], array_column($e['cartas']['kaique']['hab'], 'g'));
        $this->assertSame([], $e['cartas']['prop']['hab']);
    }

    public function test_invoca_carta_pelo_nome_fichas_com_palavra_e_mill(): void
    {
        $e = $this->comTextos(['tiinho', 'kaique', 'dividido']);
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'kaique'));
        $ficha = end($e['lados']['a']['mesa']);
        $this->assertSame(['Carniçal', 2, ['toque']], [$ficha['nome'], $ficha['atk'], $ficha['pal']]);
        $baralho = count($e['lados']['a']['baralho']);
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'dividido'));
        $this->assertSame($baralho - 10, count($e['lados']['a']['baralho']));
        $this->assertCount(10, $e['lados']['a']['cemiterio']);
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', 'tiinho'));
        $e = Gladio::passar($e, 'a');
        $this->assertNotContains('cabrito', array_column($e['lados']['a']['mesa'], 'c'), 'no turno do adversário não dispara');
        $e = Gladio::passar($e, 'b');
        $this->assertContains('cabrito', array_column($e['lados']['a']['mesa'], 'c'), 'Tiinho invocou um Cabrito no início do turno do dono');
        $insts = [];
        foreach (['mao', 'baralho', 'mesa', 'cemiterio'] as $z) {
            array_push($insts, ...array_column($e['lados']['a'][$z], 'i'), ...array_column($e['lados']['b'][$z], 'i'));
        }
        $this->assertSame(count($insts), count(array_unique($insts)), 'cada carta invocada ganha uma instância nova');
    }
}
