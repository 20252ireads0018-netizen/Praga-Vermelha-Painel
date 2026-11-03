<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pix direto na chave do dono do site (config/pix.php): monta o "Pix copia e cola" (BR Code estático, padrão EMV do
 * Banco Central) com valor e código do pedido, e diz quem pode aprovar os pagamentos.
 */
class Pix
{
    private static ?bool $tabela = null;

    public static function configurado(): bool
    {
        return trim((string) config('pix.chave')) !== '';
    }

    /** Compra por Pix ligada: chave definida, colunas de planos e a tabela pix_pedidos (SQL da migração 000025). */
    public static function disponivel(): bool
    {
        if (!self::configurado() || !Plano::temColunas()) {
            return false;
        }
        if (self::$tabela === null) {
            try {
                self::$tabela = Schema::hasTable('pix_pedidos');
            } catch (\Throwable $e) {
                self::$tabela = false;
            }
        }

        return self::$tabela;
    }

    /** Só para os testes, que criam a tabela depois do primeiro acesso. */
    public static function esquecerTabela(): void
    {
        self::$tabela = null;
    }

    public static function admin(?User $user): bool
    {
        return $user && in_array(mb_strtolower($user->email), array_map('mb_strtolower', (array) config('pix.admins')), true);
    }

    /** Código do pedido: vai no Pix (identificador da transação) e aparece para os dois lados conferirem. */
    public static function novoCodigo(): string
    {
        return 'PV' . strtoupper(Str::random(8));
    }

    /** Texto do Pix copia e cola (é o mesmo que vai no QR Code). */
    public static function copiaECola(float $valor, string $codigo): string
    {
        $campo = fn (string $id, string $v) => $id . str_pad((string) strlen($v), 2, '0', STR_PAD_LEFT) . $v;
        $limpo = fn (string $s, int $max) => mb_substr(trim(preg_replace('/[^A-Za-z0-9 ]/', '', Str::ascii($s))), 0, $max) ?: 'X';

        $conta = $campo('00', 'br.gov.bcb.pix') . $campo('01', trim((string) config('pix.chave')));
        $txid = substr(preg_replace('/[^A-Za-z0-9]/', '', $codigo), 0, 25) ?: '***';

        $payload = $campo('00', '01')
            . $campo('26', $conta)
            . $campo('52', '0000')
            . $campo('53', '986')
            . $campo('54', number_format($valor, 2, '.', ''))
            . $campo('58', 'BR')
            . $campo('59', strtoupper($limpo((string) config('pix.nome'), 25)))
            . $campo('60', strtoupper($limpo((string) config('pix.cidade'), 15)))
            . $campo('62', $campo('05', $txid))
            . '6304';

        return $payload . self::crc16($payload);
    }

    /** CRC16-CCITT (polinômio 0x1021, início 0xFFFF), exigido no fim do BR Code. */
    public static function crc16(string $s): string
    {
        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $crc ^= ord($s[$i]) << 8;
            for ($b = 0; $b < 8; $b++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
