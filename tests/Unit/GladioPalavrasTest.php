<?php

namespace Tests\Unit;

use App\Jogos\Gladio;
use PHPUnit\Framework\TestCase;

/** Pressa, Desafiador e as palavras-chave do Magic (Imbloqueável, Sombra, Furtividade, Flanquear, Exaltado…). */
class GladioPalavrasTest extends TestCase
{
    use GladioVidaDaAposta;

    private int $seq = 5000;

    private const CARTAS = [
        'x'    => ['nome' => 'Soldado', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1, 'palavras' => '', 'descricao' => ''],
        'cura' => ['nome' => 'Estudo', 'tipo' => 'feitico', 'custo' => 0, 'ataque' => 0, 'vida' => 0, 'palavras' => '', 'descricao' => 'Compre uma carta'],
    ];

    private function novo(): array
    {
        $e = $this->iniciar(self::CARTAS, 99, ['a' => array_fill(0, 20, 'x'), 'b' => array_fill(0, 20, 'x')], fn ($a) => $a);
        foreach (['a', 'b'] as $l) {
            $e['lados'][$l]['mesa'] = [];
            $e['lados'][$l]['energia'] = 10;
        }

        return $e;
    }

    /** Lenda pronta direto na mesa. Devolve [estado, inst]. */
    private function por(array $e, string $lado, int $atk, int $vida, array $pal = [], string $nome = 'Lenda'): array
    {
        $i = ++$this->seq;
        $e['lados'][$lado]['mesa'][] = ['i' => $i, 'c' => null, 'nome' => $nome, 'atk' => $atk, 'vida' => $vida, 'max' => $vida,
                                        'pode' => true, 'virada' => false, 'pal' => $pal];

        return [$e, $i];
    }

    private function lenda(array $e, string $lado, int $i): ?array
    {
        foreach ($e['lados'][$lado]['mesa'] as $m) {
            if ((int) $m['i'] === $i) {
                return $m;
            }
        }

        return null;
    }

    private function naoBloqueia(array $e, int $b, int $a, string $trecho): void
    {
        try {
            Gladio::bloquear($e, 'b', $b, $a);
            $this->fail("deveria recusar: $trecho");
        } catch (\InvalidArgumentException $x) {
            $this->assertStringContainsString($trecho, $x->getMessage());
        }
    }

    public function test_pressa_e_o_antigo_impeto(): void
    {
        $this->assertSame('Pressa', Gladio::PALAVRAS['impeto']);
        $cartas = self::CARTAS + ['p' => ['nome' => 'Veloz', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 1, 'vida' => 1, 'palavras' => 'impeto', 'descricao' => '']];
        $e = $this->iniciar($cartas, 0, ['a' => array_fill(0, 20, 'x'), 'b' => array_fill(0, 20, 'x')], fn ($a) => $a);
        $e['lados']['a']['mao'][] = ['i' => 990, 'c' => 'p'];
        $e = Gladio::jogar($e, 'a', 990);
        $this->assertTrue(end($e['lados']['a']['mesa'])['pode'], 'com Pressa ataca no turno em que entra');
    }

    public function test_imbloqueavel_sombra_e_furtividade(): void
    {
        $e = $this->novo();
        [$e, $imb] = $this->por($e, 'a', 2, 2, ['imbloqueavel']);
        [$e, $som] = $this->por($e, 'a', 2, 2, ['sombra']);
        [$e, $fur] = $this->por($e, 'a', 2, 2, ['furtividade']);
        [$e, $com] = $this->por($e, 'a', 2, 2);
        [$e, $forte] = $this->por($e, 'b', 5, 5);
        [$e, $fraco] = $this->por($e, 'b', 1, 1);
        [$e, $sombraB] = $this->por($e, 'b', 1, 1, ['sombra']);
        $e = Gladio::atacar($e, 'a', [$imb, $som, $fur, $com], 'b');
        $this->naoBloqueia($e, $fraco, $imb, 'Imbloqueável');
        $this->naoBloqueia($e, $fraco, $som, 'Sombra');
        $this->naoBloqueia($e, $sombraB, $com, 'só bloqueia Lendas com Sombra');
        $this->naoBloqueia($e, $forte, $fur, 'Furtividade');
        $e = Gladio::bloquear($e, 'b', $sombraB, $som);
        $e = Gladio::bloquear($e, 'b', $fraco, $fur);
        $this->assertSame([$sombraB => $som, $fraco => $fur], array_map('intval', (array) $e['ataque']['bloqueios']));
    }

    public function test_desafiador_obriga_a_bloquear(): void
    {
        $e = $this->novo();
        [$e, $des] = $this->por($e, 'a', 3, 3, ['desafiador']);
        [$e, $alvo] = $this->por($e, 'b', 1, 1);
        [$e, $outra] = $this->por($e, 'b', 1, 1);
        $e = Gladio::atacar($e, 'a', [$des], 'b');
        $this->assertSame(['a', 'lenda'], [$e['escolha']['lado'], $e['escolha']['tipo']]);
        $this->assertEqualsCanonicalizing([$alvo, $outra], $e['escolha']['opcoes']);
        $e = Gladio::escolher($e, 'a', (string) $alvo);
        $this->assertSame([$alvo => $des], array_map('intval', (array) $e['ataque']['bloqueios']));
        try {
            Gladio::bloquear($e, 'b', $alvo, null);
            $this->fail('a desafiada não sai do bloqueio');
        } catch (\InvalidArgumentException $x) {
            $this->assertStringContainsString('desafiada', $x->getMessage());
        }
        $e = Gladio::defender($e, 'b');
        $this->assertNull($this->lenda($e, 'b', $alvo), 'a desafiada lutou e caiu');
    }

    public function test_exaltado_e_grito_de_guerra(): void
    {
        $e = $this->novo();
        [$e, $so] = $this->por($e, 'a', 2, 2, ['exaltado']);
        [$e] = $this->por($e, 'a', 1, 1, ['exaltado', 'defensor']);
        $e = Gladio::atacar($e, 'a', [$so], 'b');
        $this->assertSame([4, 4], [$this->lenda($e, 'a', $so)['atk'], $this->lenda($e, 'a', $so)['vida']], 'duas Lendas com Exaltado: +2/+2');

        $e = $this->novo();
        [$e, $grito] = $this->por($e, 'a', 2, 2, ['grito']);
        [$e, $junto] = $this->por($e, 'a', 1, 1);
        $e = Gladio::atacar($e, 'a', [$grito, $junto], 'b');
        $this->assertSame([2, 2, 1], [$this->lenda($e, 'a', $grito)['atk'], $this->lenda($e, 'a', $junto)['atk'], $this->lenda($e, 'a', $junto)['vida']]);
    }

    public function test_bushido_e_flanquear(): void
    {
        $e = $this->novo();
        [$e, $bu] = $this->por($e, 'a', 2, 2, ['bushido']);
        [$e, $bl] = $this->por($e, 'b', 2, 2);
        $e = Gladio::atacar($e, 'a', [$bu], 'b');
        $e = Gladio::bloquear($e, 'b', $bl, $bu);
        $e = Gladio::defender($e, 'b');
        $this->assertNotNull($this->lenda($e, 'a', $bu), 'com Bushido ficou 3/3 e aguentou os 2 de dano');
        $this->assertNull($this->lenda($e, 'b', $bl));

        $e = $this->novo();
        [$e, $fl] = $this->por($e, 'a', 1, 3, ['flanquear']);
        [$e, $bl] = $this->por($e, 'b', 1, 1);
        $e = Gladio::atacar($e, 'a', [$fl], 'b');
        $e = Gladio::bloquear($e, 'b', $bl, $fl);
        $e = Gladio::defender($e, 'b');
        $this->assertNull($this->lenda($e, 'b', $bl), 'Flanquear: o bloqueador 1/1 virou 0/0');
        $this->assertSame(3, $this->lenda($e, 'a', $fl)['vida'], 'e nem chegou a causar dano');
    }

    public function test_persistir_e_imortal(): void
    {
        $e = $this->novo();
        [$e, $pe] = $this->por($e, 'b', 2, 2, ['persistir']);
        [$e, $im] = $this->por($e, 'b', 2, 2, ['imortal']);
        [$e, $at] = $this->por($e, 'a', 5, 5, ['duplo']);
        [$e, $at2] = $this->por($e, 'a', 5, 5);
        $e = Gladio::atacar($e, 'a', [$at, $at2], 'b');
        $e = Gladio::bloquear($e, 'b', $pe, $at2);
        $e = Gladio::bloquear($e, 'b', $im, $at);
        $e = Gladio::defender($e, 'b');
        $this->assertSame([1, 1], [$this->lenda($e, 'b', $pe)['atk'], $this->lenda($e, 'b', $pe)['max']], 'Persistir: voltou com -1/-1');
        $this->assertSame([3, 3], [$this->lenda($e, 'b', $im)['atk'], $this->lenda($e, 'b', $im)['max']], 'Imortal voltou com +1/+1 (mesmo levando golpe duplo)');
        // segunda vez: não volta
        $e = Gladio::passar($e, 'a');
        $e['lados']['b']['mesa'] = array_map(fn ($m) => (int) $m['i'] === $pe ? ['vida' => 0] + $m : $m, $e['lados']['b']['mesa']);
        $e = Gladio::passar($e, 'b');
        $this->assertNull($this->lenda($e, 'b', $pe));
    }

    public function test_bravura(): void
    {
        $e = $this->novo();
        [$e, $br] = $this->por($e, 'a', 1, 1, ['bravura']);
        $e['lados']['a']['mao'][] = ['i' => 991, 'c' => 'cura'];
        $e = Gladio::jogar($e, 'a', 991);
        $this->assertSame([2, 2], [$this->lenda($e, 'a', $br)['atk'], $this->lenda($e, 'a', $br)['vida']]);
        $e = Gladio::passar($e, 'a');
        $this->assertSame([1, 1], [$this->lenda($e, 'a', $br)['atk'], $this->lenda($e, 'a', $br)['max']], 'só até o fim do turno');
    }
}
