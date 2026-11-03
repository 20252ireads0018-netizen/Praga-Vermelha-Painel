<?php

namespace Tests\Unit;

use App\Jogos\Gladio;
use PHPUnit\Framework\TestCase;

/** Cada alvo guarda quem o mirou (a tela mostra "mirada por …", inclusive de feitiços que já resolveram). */
class GladioMiradaTest extends TestCase
{
    private function mirar(array &$e, array $ef, array $lendas, array $jogadores = []): void
    {
        $m = new \ReflectionMethod(Gladio::class, 'mirar');
        $m->setAccessible(true);
        $m->invokeArgs(null, [&$e, $ef, $lendas, $jogadores]);
    }

    public function test_alvo_guarda_quem_o_mirou(): void
    {
        $e = ['turno' => 3, 'brilho' => [], 'lados' => [
            'a' => ['mesa' => [['i' => 10, 'c' => '1']], 'feiticos' => []],
            'b' => ['mesa' => [['i' => 20, 'c' => '1'], ['i' => 21, 'c' => '1']], 'feiticos' => []],
        ]];
        $this->mirar($e, ['lado' => 'a', 'c' => '7', 'i' => 30], [20], ['b']);
        $this->assertSame([['c' => '7', 'lado' => 'a', 't' => 3]], $e['lados']['b']['mesa'][0]['alvo_de']);
        $this->assertSame([['c' => '7', 'lado' => 'a', 't' => 3]], $e['lados']['b']['alvo_de']);
        $this->assertArrayNotHasKey('alvo_de', $e['lados']['b']['mesa'][1]);

        // a mesma carta de novo não repete; guarda só as 4 últimas
        $e['turno'] = 4;
        foreach (['7', '8', '9', '11', '12'] as $c) {
            $this->mirar($e, ['lado' => 'a', 'c' => $c, 'i' => null], [20]);
        }
        $this->assertSame(['8', '9', '11', '12'], array_column($e['lados']['b']['mesa'][0]['alvo_de'], 'c'));

        // o próprio dono do efeito não é marcado como alvo do próprio efeito
        $this->mirar($e, ['lado' => 'a', 'c' => '5', 'i' => 10], [10], ['a']);
        $this->assertArrayNotHasKey('alvo_de', $e['lados']['a']['mesa'][0]);
        $this->assertArrayNotHasKey('alvo_de', $e['lados']['a']);
    }
}
