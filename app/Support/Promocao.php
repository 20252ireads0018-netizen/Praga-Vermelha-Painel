<?php

namespace App\Support;

/**
 * Promoção relâmpago do livro do sistema: um administrador liga (metade do preço ou 1/4) por alguns minutos e o
 * preço volta sozinho quando o prazo acaba. Fica num arquivo em storage/app (sem tabela nova); a última promoção
 * continua lá depois de acabar, para honrar o Pix gerado durante ela (ver precoDoPedido).
 */
class Promocao
{
    /** fator => nome, na ordem do painel */
    public const FATORES = ['0.5' => 'Metade do preço', '0.25' => '1/4 do preço'];
    public const MINUTOS_MIN = 1;
    public const MINUTOS_MAX = 1440;
    /** quem gerou o Pix na promoção ainda paga o preço dela por este tempo depois do fim */
    public const TOLERANCIA = 1800;

    public static function arquivo(): string
    {
        return storage_path('app/promocao.json');
    }

    /** A última promoção ligada (ativa ou não): { fator, inicio, fim, por }. */
    public static function ultima(): ?array
    {
        $j = @file_get_contents(self::arquivo());
        $p = $j ? json_decode($j, true) : null;

        return is_array($p) && isset($p['fator'], $p['inicio'], $p['fim']) ? $p : null;
    }

    /** A promoção valendo agora, ou null. */
    public static function atual(?int $agora = null): ?array
    {
        $p = self::ultima();
        $agora ??= time();

        return $p && $agora >= (int) $p['inicio'] && $agora < (int) $p['fim'] ? $p : null;
    }

    public static function ligar(float $fator, int $minutos, ?int $por = null): array
    {
        if (!array_key_exists((string) $fator, self::FATORES)) {
            throw new \InvalidArgumentException('Escolha metade ou 1/4 do preço.');
        }
        $minutos = max(self::MINUTOS_MIN, min(self::MINUTOS_MAX, $minutos));
        $p = ['fator' => $fator, 'inicio' => time(), 'fim' => time() + $minutos * 60, 'por' => $por];
        @mkdir(dirname(self::arquivo()), 0755, true);
        file_put_contents(self::arquivo(), json_encode($p), LOCK_EX);

        return $p;
    }

    /** Encerra agora (o Pix já gerado nela ainda vale pela tolerância). */
    public static function encerrar(): void
    {
        $p = self::ultima();
        if ($p && time() < (int) $p['fim']) {
            $p['fim'] = time();
            file_put_contents(self::arquivo(), json_encode($p), LOCK_EX);
        }
    }

    /** Preço com o desconto, arredondado ao centavo (6,99 → 3,50 na metade; 1,75 em 1/4). */
    public static function aplicar(float $base, float $fator): float
    {
        return round($base * $fator + 1e-9, 2);
    }

    /** Preço do livro agora: com a promoção, se houver. */
    public static function preco(float $base, ?int $agora = null): float
    {
        $p = self::atual($agora);

        return $p ? self::aplicar($base, (float) $p['fator']) : $base;
    }

    /**
     * Preço de um Pix ainda não pago, criado em $criado: o atual, mas quem gerou o Pix durante a última promoção
     * paga o preço dela até TOLERANCIA segundos depois do fim.
     */
    public static function precoDoPedido(float $base, int $criado, ?int $agora = null): float
    {
        $agora ??= time();
        $atual = self::preco($base, $agora);
        $p = self::ultima();
        if ($p && $criado >= (int) $p['inicio'] && $criado <= (int) $p['fim'] && $agora < (int) $p['fim'] + self::TOLERANCIA) {
            return min($atual, self::aplicar($base, (float) $p['fator']));
        }

        return $atual;
    }
}
