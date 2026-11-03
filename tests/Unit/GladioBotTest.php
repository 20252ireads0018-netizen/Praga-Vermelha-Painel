<?php

namespace Tests\Unit;

use App\Jogos\Gladio;
use App\Jogos\GladioBot;
use PHPUnit\Framework\TestCase;

/** Bot do Gladio: joga pelas regras do motor, decide mulligan, cartas, ataque, defesa e escolhas, e a partida termina. */
class GladioBotTest extends TestCase
{
    private const CARTAS = [
        '1' => ['nome' => 'Gladiador', 'tipo' => 'lenda', 'custo' => 1, 'ataque' => 3, 'vida' => 2, 'efeito' => null, 'valor' => 0, 'palavras' => '', 'descricao' => ''],
        '2' => ['nome' => 'Bola de fogo', 'tipo' => 'feitico', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'efeito' => 'dano', 'valor' => 4, 'palavras' => '', 'descricao' => ''],
        '3' => ['nome' => 'Muralha', 'tipo' => 'lenda', 'custo' => 2, 'ataque' => 1, 'vida' => 6, 'efeito' => null, 'valor' => 0, 'palavras' => '', 'descricao' => ''],
        '4' => ['nome' => 'Lucas', 'tipo' => 'lenda', 'custo' => 3, 'ataque' => 4, 'vida' => 5, 'efeito' => null, 'valor' => 0, 'palavras' => 'sobrepujar', 'descricao' => ''],
        '5' => ['nome' => 'Raio', 'tipo' => 'feitico', 'custo' => 2, 'ataque' => 0, 'vida' => 0, 'efeito' => null, 'valor' => 0, 'palavras' => '',
                'descricao' => 'Cause 3 de dano a uma lenda alvo'],
        '6' => ['nome' => 'Bênção', 'tipo' => 'feitico', 'custo' => 1, 'ataque' => 0, 'vida' => 0, 'efeito' => 'cura', 'valor' => 5, 'palavras' => '', 'descricao' => ''],
    ];

    private function partida(): array
    {
        $deck = array_merge(...array_fill(0, 4, ['1', '2', '3', '4', '5', '6']));

        return Gladio::abrirMulligan(Gladio::iniciar(self::CARTAS, 0, ['a' => $deck, 'b' => $deck], null, ['a' => 'Ana', 'b' => 'Bot']));
    }

    public function test_bot_mantem_a_mao_e_espera_a_pessoa(): void
    {
        $e = GladioBot::agir($this->partida(), 'b');
        $this->assertTrue($e['mull']['b']['ok']);
        $this->assertSame('mulligan', $e['fase'], 'a pessoa ainda não decidiu');
        $this->assertSame('a', Gladio::esperando($e));

        $e = GladioBot::agir(Gladio::manter($e, 'a', []), 'b'); // turno 1 é de 'a': o bot não mexe
        $this->assertSame(['principal', 'a'], [$e['fase'], $e['vez']]);
    }

    public function test_bot_joga_o_turno_inteiro_e_passa(): void
    {
        $e = Gladio::manter(Gladio::manter($this->partida(), 'a', []), 'b', []);
        $e = Gladio::passar($e, 'a', true); // vez do bot
        $this->assertSame('b', Gladio::esperando($e));
        $e['lados']['b']['mao'] = [['i' => 800, 'c' => '4'], ['i' => 801, 'c' => '1']]; // Lucas (3) não cabe; Gladiador (1) sim
        $e['lados']['b']['comando'] = []; // sem comandante sorteado (ele também poderia ser invocado com 1 de energia)
        $e['lados']['b']['cmd'] = null;
        $e = GladioBot::agir($e, 'b');
        $this->assertSame('a', $e['vez'], 'o bot jogou o que pôde e passou a vez');
        $this->assertSame([801], array_column($e['lados']['b']['mesa'], 'i'), 'pôs na mesa a Lenda que a energia pagava');
        $this->assertSame(800, $e['lados']['b']['mao'][0]['i'] ?? null);
    }

    public function test_bot_bloqueia_quando_sobrevive(): void
    {
        $e = Gladio::manter(Gladio::manter($this->partida(), 'a', []), 'b', []);
        $e['lados']['a']['mesa'][] = ['i' => 900, 'c' => '1', 'atk' => 3, 'vida' => 2, 'max' => 2, 'pode' => true, 'virada' => false, 'pal' => [], 'cont' => 0, 'nota' => ''];
        $e['lados']['b']['mesa'][] = ['i' => 901, 'c' => '3', 'atk' => 1, 'vida' => 6, 'max' => 6, 'pode' => true, 'virada' => false, 'pal' => [], 'cont' => 0, 'nota' => ''];
        $e = Gladio::atacar($e, 'a', [900], 'b');
        $e = GladioBot::agir($e, 'b');
        $this->assertSame(40, $e['lados']['b']['vida'], 'a Muralha segurou o ataque');
        $this->assertSame('principal', $e['fase']);
    }

    public function test_dois_bots_terminam_a_partida(): void
    {
        for ($jogo = 0; $jogo < 5; $jogo++) {
            $e = $this->partida();
            for ($n = 0; $n < 2000 && !$e['fim']; $n++) {
                $e = GladioBot::agir($e, Gladio::esperando($e));
            }
            $this->assertNotNull($e['fim'], "a partida $jogo terminou");
            $this->assertContains($e['fim']['vencedor'], ['a', 'b']);
        }
    }
}
