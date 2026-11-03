<?php

namespace Tests\Unit;

use App\Jogos\PelaDama;
use PHPUnit\Framework\TestCase;

class PelaDamaTest extends TestCase
{
    private function novo(?array $exB = null, ?array $exP = null, array $extra = []): array
    {
        $tipos = PelaDama::tiposPadrao() + $extra;

        return PelaDama::iniciar($tipos, $exB ?? PelaDama::exercitoPadrao(), $exP ?? PelaDama::exercitoPadrao());
    }

    /** Joga uma lista de lances em notação "e2e4". */
    private function jogar(array $e, string ...$lances): array
    {
        foreach ($lances as $l) {
            $c = fn ($i) => [ord($l[$i]) - 97, (int) $l[$i + 1] - 1];
            [$x, $y] = $c(0);
            [$tx, $ty] = $c(2);
            $e = PelaDama::mover($e, $e['vez'], $x, $y, $tx, $ty);
        }

        return $e;
    }

    public function test_abertura_tem_20_lances(): void
    {
        $e = $this->novo();
        $total = array_sum(array_map('count', PelaDama::mapaLegais($e)));
        $this->assertSame(20, $total);
    }

    public function test_mate_do_pastor(): void
    {
        $e = $this->jogar($this->novo(), 'e2e4', 'e7e5', 'f1c4', 'b8c6', 'd1h5', 'g8f6', 'h5f7');
        $this->assertSame(['motivo' => 'xeque-mate', 'vencedor' => 'b'], $e['fim']);
        $this->assertSame([], PelaDama::mapaLegais($e));
    }

    public function test_nao_pode_jogar_fora_da_vez_nem_lance_ilegal(): void
    {
        $e = $this->novo();
        $this->expectException(\InvalidArgumentException::class);
        PelaDama::mover($e, 'p', 4, 6, 4, 4);
    }

    public function test_lance_que_deixa_o_rei_em_xeque_e_proibido(): void
    {
        // 1.e4 f6 2.a3 a6 3.b3 b6 4.Dh5+: a diagonal h5-e8 está aberta (as pretas já fizeram 3 jogadas: o Rei não está mais protegido)
        $e = $this->jogar($this->novo(), 'e2e4', 'f7f6', 'a2a3', 'a7a6', 'b2b3', 'b7b6', 'd1h5');
        $this->assertTrue($e['xeque']);
        $this->assertArrayNotHasKey('7,6', PelaDama::mapaLegais($e), 'h7-h6 não tira do xeque');
        $this->assertSame([[6, 5]], PelaDama::mapaLegais($e)['6,6'], 'g7-g6 bloqueia (g7-g5 não)');
        // as pretas só podem sair do xeque: todo lance legal resolve o xeque
        foreach (PelaDama::mapaLegais($e) as $de => $destinos) {
            [$x, $y] = array_map('intval', explode(',', $de));
            foreach ($destinos as [$tx, $ty]) {
                $n = PelaDama::mover($e, 'p', $x, $y, $tx, $ty);
                $this->assertFalse(PelaDama::emXeque($n, 'p'));
            }
        }
    }

    public function test_rei_protegido_de_xeque_nas_3_primeiras_jogadas_do_dono(): void
    {
        // mate do louco: 1.f3 e5 2.g4 Dh4# — as brancas só fizeram 2 jogadas, então a Dama não pode dar xeque
        $e = $this->jogar($this->novo(), 'f2f3', 'e7e5', 'g2g4');
        $this->assertTrue(PelaDama::protegido($e, 'b'));
        $this->assertNotContains([7, 3], PelaDama::mapaLegais($e)['3,7'] ?? [], 'Dd8-h4 daria xeque: proibido');
        $this->expectExceptionMessage('não pode ir');
        PelaDama::mover($e, 'p', 3, 7, 7, 3);
    }

    public function test_depois_da_terceira_jogada_o_xeque_vale(): void
    {
        $e = $this->jogar($this->novo(), 'f2f3', 'e7e5', 'g2g4', 'a7a6', 'a2a3');
        $this->assertSame(3, PelaDama::jogadas($e, 'b'));
        $this->assertFalse(PelaDama::protegido($e, 'b'));
        $e = $this->jogar($e, 'd8h4');
        $this->assertSame(['motivo' => 'xeque-mate', 'vencedor' => 'p'], $e['fim']);
    }

    public function test_roque_curto_move_a_torre(): void
    {
        $e = $this->jogar($this->novo(), 'e2e4', 'e7e5', 'g1f3', 'b8c6', 'f1c4', 'g8f6', 'e1g1');
        $this->assertSame('rei', $e['tab'][0][6]['t']);
        $this->assertSame('torre', $e['tab'][0][5]['t']);
        $this->assertNull($e['tab'][0][7]);
    }

    public function test_en_passant_e_promocao(): void
    {
        $e = $this->jogar($this->novo(), 'e2e4', 'a7a6', 'e4e5', 'd7d5', 'e5d6');
        $this->assertNull($e['tab'][4][3], 'o peão de d5 foi capturado en passant');
        $e = $this->jogar($e, 'a6a5', 'd6c7', 'a5a4', 'c7b8');
        $this->assertSame('dama', $e['tab'][7][1]['t']);
        $this->assertSame('b', $e['tab'][7][1]['c']);
    }

    public function test_peca_customizada_com_salto_e_so_captura(): void
    {
        // "Arqueiro": anda 1 para frente (só mover) e captura saltando 2 casas na diagonal da frente
        $arq = ['nome' => 'Arqueiro', 'simbolo' => 'A', 'especial' => null, 'regras' => PelaDama::limparRegras([
            ['dx' => 0, 'dy' => 1, 'm' => 'mover'],
            ['dx' => 2, 'dy' => 2, 'm' => 'capturar'],
            ['dx' => -2, 'dy' => 2, 'm' => 'capturar'],
        ])];
        $exB = PelaDama::exercitoPadrao();
        $exB[8 + 3] = 'arq'; // no lugar do peão d2
        $e = $this->novo($exB, null, ['arq' => $arq]);
        $legais = PelaDama::legais($e, 3, 1);
        $this->assertSame([[3, 2, '']], $legais, 'só anda 1 para frente; as diagonais estão vazias');
        // para as pretas a mesma regra aponta para baixo
        $exP = PelaDama::exercitoPadrao();
        $exP[8 + 3] = 'arq';
        $e2 = $this->novo(null, $exP, ['arq' => $arq]);
        $e2 = $this->jogar($e2, 'a2a3');
        $this->assertSame([[3, 5, '']], PelaDama::legais($e2, 3, 6));
    }

    public function test_limpar_regras_descarta_lixo(): void
    {
        $r = PelaDama::limparRegras([['dx' => 0, 'dy' => 0], ['dx' => 9, 'dy' => 1], ['dx' => 1, 'dy' => 0, 'd' => 1, 'alc' => 99, 'm' => 'xx'], ['dx' => 1, 'dy' => 0], 'lixo']);
        $this->assertSame([['dx' => 1, 'dy' => 0, 'd' => true, 'alc' => 7, 'm' => 'ambos']], $r);
    }

    public function test_exercito_precisa_de_um_rei(): void
    {
        $t = PelaDama::tiposPadrao();
        $ex = PelaDama::exercitoPadrao();
        $this->assertNull(PelaDama::validarExercito($ex, $t));
        $ex[4] = 'dama';
        $this->assertNotNull(PelaDama::validarExercito($ex, $t));
        $this->assertNotNull(PelaDama::validarExercito(['rei'], $t));
    }

    public function test_afogamento_e_empate(): void
    {
        // posição montada: rei preto em a8, dama branca em b6, rei branco em c1; vez das pretas sem lances
        $e = $this->novo();
        $e['tab'] = array_fill(0, 8, array_fill(0, 8, null));
        $e['tab'][7][0] = ['t' => 'rei', 'c' => 'p', 'm' => true];
        $e['tab'][0][2] = ['t' => 'rei', 'c' => 'b', 'm' => true];
        $e['tab'][5][2] = ['t' => 'dama', 'c' => 'b', 'm' => true]; // c6
        $e = PelaDama::mover($e, 'b', 2, 5, 1, 5);                // Dc6-b6: afoga
        $this->assertSame(['motivo' => 'afogamento', 'vencedor' => null], $e['fim']);
    }
}
