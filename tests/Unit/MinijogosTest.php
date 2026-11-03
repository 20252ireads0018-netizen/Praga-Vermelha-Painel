<?php

namespace Tests\Unit;

use App\Jogos\Minijogos;
use PHPUnit\Framework\TestCase;

class MinijogosTest extends TestCase
{
    public function test_minas_sorteadas_sao_distintas_e_dentro_da_grade(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $m = Minijogos::sortearMinas(7);
            $this->assertCount(7, array_unique($m));
            $this->assertGreaterThanOrEqual(0, min($m));
            $this->assertLessThanOrEqual(24, max($m));
        }
    }

    public function test_multiplicador_do_mines_e_justo_menos_a_margem(): void
    {
        $this->assertSame(1.0, Minijogos::multMines(3, 0));
        $this->assertSame(1.1, Minijogos::multMines(3, 1));      // 0,97 × 25/22 = 1,102
        $this->assertSame(24.25, Minijogos::multMines(1, 24));   // abriu tudo com 1 mina: 0,97 × 25
        $this->assertGreaterThan(Minijogos::multMines(5, 3), Minijogos::multMines(10, 3)); // mais minas, mais prêmio
    }

    public function test_queda_do_aviator(): void
    {
        $this->assertSame(1.0, Minijogos::sortearQueda(0.0));    // 0,97 → cai na hora
        $this->assertSame(1.94, Minijogos::sortearQueda(0.5));
        $this->assertSame(1000.0, Minijogos::sortearQueda(0.99999));
        $this->assertSame(1.0, Minijogos::multAviao(0));
        $this->assertSame(2.0, Minijogos::multAviao(Minijogos::tempoAte(2.0) + 0.001));
        // em média, quem sempre saca em 2× recebe cerca de 97%
        $ganhou = 0;
        for ($i = 0; $i < 20000; $i++) {
            $ganhou += Minijogos::sortearQueda() > 2.0 ? 2 : 0;
        }
        $this->assertEqualsWithDelta(0.97, $ganhou / 20000, 0.04);
    }

    public function test_caca_niquel_paga_cerca_de_96_por_cento(): void
    {
        $this->assertSame(600, Minijogos::premioSlot(['vassago', 'vassago', 'vassago']));
        $this->assertSame(1, Minijogos::premioSlot(['rosa', 'rosa', 'olho']));
        $this->assertSame(0, Minijogos::premioSlot(['rosa', 'olho', 'rosa']));
        $r = Minijogos::retornoSlot();
        $this->assertGreaterThan(0.93, $r);
        $this->assertLessThan(0.98, $r);
        $this->assertCount(3, Minijogos::girar());
    }
}
