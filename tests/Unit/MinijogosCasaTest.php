<?php

namespace Tests\Unit;

use App\Jogos\Minijogos;
use PHPUnit\Framework\TestCase;

/** A Casa: teto de lucro de 50% na noite, isca nas primeiras rodadas e aperto de quem está ganhando. */
class MinijogosCasaTest extends TestCase
{
    public function test_condicoes_da_casa(): void
    {
        $this->assertSame(1500, Minijogos::teto(1000));
        $c = Minijogos::casa(1000, 100, 1000, 0);
        $this->assertSame([600, true, 0.0], [$c['premio_max'], $c['isca'], $c['lucro']]); // pode voltar a ter até 1500
        $c = Minijogos::casa(1250, 100, 1000, 5);
        $this->assertSame([350, false, 0.5], [$c['premio_max'], $c['isca'], $c['lucro']]);
        $this->assertSame(0, Minijogos::casa(1500, 1500, 1000, 9)['premio_max'] - 1500); // no teto: só a própria aposta de volta
    }

    public function test_aviator_cai_antes_do_teto(): void
    {
        $casa = Minijogos::casa(1000, 500, 1000, 9); // pode receber no máximo 1000 (2×)
        $this->assertSame(1.99, Minijogos::quedaDaCasa(50.0, $casa, 500, .5));
        $this->assertSame(1.4, Minijogos::quedaDaCasa(1.4, $casa, 500, .5)); // abaixo do teto, a sorte decide
        $this->assertGreaterThanOrEqual(1.6, Minijogos::quedaDaCasa(1.0, Minijogos::casa(1000, 10, 1000, 0), 10, .0)); // a isca
        $this->assertSame(1.0, Minijogos::quedaDaCasa(5.0, Minijogos::casa(1500, 100, 1000, 9), 100, .5)); // no teto: cai na hora
    }

    public function test_mines_poe_mina_onde_estouraria_o_teto(): void
    {
        $casa = Minijogos::casa(1000, 500, 1000, 9); // prêmio máximo 1000
        $this->assertFalse(Minijogos::minaDaCasa(false, 0, 3, 500, $casa, .99)); // 1,10× = 550: passa
        $this->assertFalse(Minijogos::minaDaCasa(false, 4, 3, 500, $casa, .99)); // 5 casas com 3 minas = 1,95× (975): ainda passa
        $this->assertTrue(Minijogos::minaDaCasa(false, 5, 3, 500, $casa, .99));  // a 6ª casa daria 2,3× > 1000: vira mina
        $this->assertFalse(Minijogos::minaDaCasa(true, 0, 3, 10, Minijogos::casa(1000, 10, 1000, 0), .99)); // a isca tira a mina do 1º clique
        $this->assertTrue(Minijogos::minaDaCasa(true, 1, 3, 10, Minijogos::casa(1000, 10, 1000, 0), .99));
    }

    public function test_saldo_nunca_passa_de_um_e_meio_da_base(): void
    {
        // muitas noites jogando tudo o que dá no caça-níquel e no Aviator (sacando alto): o saldo nunca passa do teto
        for ($noite = 0; $noite < 40; $noite++) {
            $base = $saldo = 1000;
            for ($rodada = 0; $rodada < 60 && $saldo > 0; $rodada++) {
                $aposta = max(1, (int) floor($saldo * .5));
                $casa = Minijogos::casa($saldo, $aposta, $base, $rodada);
                if ($rodada % 2) {
                    [$rolos] = Minijogos::rolosDaCasa(Minijogos::girar(), $aposta, $casa);
                    $saldo += $aposta * Minijogos::premioSlot($rolos) - $aposta;
                } else {
                    $queda = Minijogos::quedaDaCasa(Minijogos::sortearQueda(), $casa, $aposta);
                    $alvo = 3.0;
                    $saldo += ($queda > $alvo ? (int) floor($aposta * $alvo) : 0) - $aposta;
                }
                $this->assertLessThanOrEqual(1500, $saldo, "noite $noite, rodada $rodada");
            }
        }
    }

    public function test_quase_do_caca_niquel_nunca_paga(): void
    {
        $casa = Minijogos::casa(1000, 100, 1000, 9);
        for ($i = 0; $i < 300; $i++) {
            [$rolos, $quase] = Minijogos::rolosDaCasa(['rosa', 'olho', 'selo'], 100, $casa);
            if ($quase) {
                $this->assertSame(0, Minijogos::premioSlot($rolos));
                $this->assertSame(2, max(array_count_values($rolos)));
            }
        }
        [, $quase] = Minijogos::rolosDaCasa(['vassago', 'vassago', 'vassago'], 100, $casa); // 60 000 não cabe no teto
        $this->assertTrue($quase);
    }
}
