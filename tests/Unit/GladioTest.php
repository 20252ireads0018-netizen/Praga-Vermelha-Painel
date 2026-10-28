<?php

namespace Tests\Unit;

use App\Jogos\Gladio;
use PHPUnit\Framework\TestCase;

class GladioTest extends TestCase
{
    private const CARTAS = [
        '1' => ['nome' => 'Gladiador', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 3, 'vida' => 2, 'efeito' => null, 'valor' => 0],
        '2' => ['nome' => 'Bola de fogo', 'tipo' => 'feitico', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'efeito' => 'dano', 'valor' => 4],
        '3' => ['nome' => 'Bênção', 'tipo' => 'feitico', 'custo' => 0, 'ataque' => 0, 'vida' => 0, 'efeito' => 'cura', 'valor' => 5],
    ];

    /** Sem sorte: as cartas na ordem do catálogo. */
    private function novo(int $aposta = 10, array $cartas = self::CARTAS): array
    {
        return Gladio::iniciar($cartas, $aposta, fn ($a) => $a);
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

    public function test_inicio_vida_e_aposta_maos_e_energia(): void
    {
        $e = $this->novo(50);
        $this->assertSame(50, $e['lados']['a']['vida']);
        $this->assertCount(3, $e['lados']['a']['mao']);
        $this->assertCount(4, $e['lados']['b']['mao']);
        $this->assertCount(17, $e['lados']['a']['baralho']);
        $this->assertSame(1, $e['lados']['a']['energia']);
        $this->assertSame(0, $e['lados']['b']['energiaMax']);
        $this->assertSame(Gladio::VIDA_AMISTOSO, $this->novo(0)['lados']['b']['vida']);
    }

    public function test_lenda_so_ataca_no_turno_seguinte_e_combate_e_mutuo(): void
    {
        $e = $this->novo();
        $ga = $this->primeira($e, 'a', '1');
        $e = Gladio::jogar($e, 'a', $ga);
        $this->assertSame(0, $e['lados']['a']['energia']);
        try {
            Gladio::atacar($e, 'a', $ga, 'inimigo');
            $this->fail('atacou no mesmo turno');
        } catch (\InvalidArgumentException) {
        }
        $e = Gladio::passar($e, 'a');
        $this->assertSame(1, $e['lados']['b']['energiaMax']);
        $this->assertCount(5, $e['lados']['b']['mao'], 'comprou 1 no início do turno');
        $gb = $this->primeira($e, 'b', '1');
        $e = Gladio::jogar($e, 'b', $gb);
        $e = Gladio::passar($e, 'b');
        // turno 3: a tem 2 de energia e o Gladiador de a pode atacar o de b (3 de ataque contra 2 de vida, os dois caem)
        $this->assertSame(2, $e['lados']['a']['energia']);
        $e = Gladio::atacar($e, 'a', $ga, $gb);
        $this->assertSame([], $e['lados']['a']['mesa']);
        $this->assertSame([], $e['lados']['b']['mesa']);
    }

    public function test_feitico_de_dano_vence_e_precisa_de_alvo(): void
    {
        $e = $this->novo(4);
        $fogo = $this->primeira($e, 'a', '2');
        try {
            Gladio::jogar($e, 'a', $fogo);
            $this->fail('feitiço de dano sem alvo');
        } catch (\InvalidArgumentException) {
        }
        $e = Gladio::jogar($e, 'a', $fogo, 'inimigo');
        $this->assertSame(0, $e['lados']['b']['vida']);
        $this->assertSame(['vencedor' => 'a', 'motivo' => 'vida'], $e['fim']);
        $this->expectException(\InvalidArgumentException::class);
        Gladio::passar($e, 'a');
    }

    public function test_cura_nao_passa_do_maximo_e_energia_e_cobrada(): void
    {
        $e = $this->novo(10);
        $e['lados']['a']['vida'] = 8;
        $e = Gladio::jogar($e, 'a', $this->primeira($e, 'a', '3'), 'eu');
        $this->assertSame(10, $e['lados']['a']['vida']);
        $e['lados']['a']['energia'] = 0;
        $this->expectExceptionMessage('Energia insuficiente.');
        Gladio::jogar($e, 'a', $this->primeira($e, 'a', '1'));
    }

    public function test_fora_da_vez_e_fadiga(): void
    {
        $e = $this->novo();
        try {
            Gladio::passar($e, 'b');
            $this->fail('passou fora da vez');
        } catch (\InvalidArgumentException) {
        }
        $e['lados']['b']['baralho'] = [];
        $e = Gladio::passar($e, 'a');
        $this->assertSame(9, $e['lados']['b']['vida']);
        $e = Gladio::passar($e, 'b');
        $e = Gladio::passar($e, 'a');
        $this->assertSame(7, $e['lados']['b']['vida'], 'a fadiga cresce: 1, depois 2');
    }

    public function test_visao_esconde_a_mao_do_outro(): void
    {
        $v = Gladio::visao($this->novo(), 'a');
        $this->assertIsArray($v['lados']['a']['mao']);
        $this->assertSame(4, $v['lados']['b']['mao']);
        $this->assertSame(17, $v['lados']['a']['baralho']);
        $s = Gladio::visao($this->novo(), null);
        $this->assertSame(3, $s['lados']['a']['mao']);
    }
}
