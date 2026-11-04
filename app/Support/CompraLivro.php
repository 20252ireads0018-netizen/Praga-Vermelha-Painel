<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Compra do info-produto do livro do sistema (R$ config planos.preco_livro) pelo Asaas.
 * Pago uma vez, a conta vira vitalícia para sempre (users.livro_comprado_em): nada aqui tira o plano depois.
 */
class CompraLivro
{
    private static ?bool $colunas = null;

    public static function temColunas(): bool
    {
        if (self::$colunas === null) {
            try {
                self::$colunas = Plano::temColunas()
                    && Esquema::coluna('users', 'cpf_cnpj') && Esquema::coluna('users', 'asaas_cobranca_id');
            } catch (\Throwable $e) {
                self::$colunas = false;
            }
        }

        return self::$colunas;
    }

    /** Só para os testes, que criam a tabela depois do primeiro acesso. */
    public static function esquecerColunas(): void
    {
        self::$colunas = null;
    }

    public static function disponivel(): bool
    {
        return self::temColunas() && AsaasCliente::configurado();
    }

    /** Preço cheio do livro (sem promoção). */
    public static function precoCheio(): float
    {
        return (float) config('planos.preco_livro', 6.99);
    }

    /** Preço de agora: com a promoção relâmpago, se houver uma valendo. */
    public static function preco(): float
    {
        return Promocao::preco(self::precoCheio());
    }

    public static function precoTexto(?float $valor = null): string
    {
        return 'R$ ' . number_format($valor ?? self::preco(), 2, ',', '.');
    }

    /** Referência da cobrança no Asaas: o webhook acha a conta por ela. */
    public static function referencia(User $user): string
    {
        return "livro:user:{$user->id}";
    }

    public static function usuarioDaReferencia(?string $ref): ?User
    {
        return $ref && preg_match('/^livro:user:(\d+)$/', $ref, $m) ? User::find((int) $m[1]) : null;
    }

    /** Marca a compra (uma vez só; quem já comprou continua com a data da primeira compra). */
    public static function liberar(User $user, ?string $cobrancaId = null): void
    {
        if (Plano::comprouLivro($user)) {
            return;
        }
        $user->forceFill(['livro_comprado_em' => now()])->save();
        Log::info('Livro do sistema comprado.', ['user' => $user->id, 'cobranca' => $cobrancaId]);
    }

    /**
     * Quando a pessoa volta da fatura: confere a cobrança aberta direto no Asaas, sem esperar o webhook.
     * Devolve true se a compra foi liberada agora.
     */
    public static function conferir(User $user, AsaasCliente $asaas): bool
    {
        if (!self::disponivel() || Plano::comprouLivro($user) || empty($user->asaas_cobranca_id)) {
            return false;
        }
        try {
            $cobranca = $asaas->buscarCobranca($user->asaas_cobranca_id);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
        if ($cobranca && in_array($cobranca['status'] ?? '', AsaasCliente::PAGOS, true)
            && ($cobranca['externalReference'] ?? '') === self::referencia($user)) {
            self::liberar($user, $cobranca['id'] ?? null);

            return true;
        }

        return false;
    }
}
