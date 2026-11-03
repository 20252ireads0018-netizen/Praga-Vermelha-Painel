<?php

namespace App\Support;

use App\Models\Campanha;
use App\Models\Personagem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Plano da conta (config/planos.php):
 *  - vitalicio: comprou o livro do sistema (users.livro_comprado_em);
 *  - teste:     até 45 dias depois de criar a conta (ou do lançamento dos planos, para contas antigas);
 *  - gratis:    o resto; vê anúncios e tem limite de fichas e campanhas.
 * Visitante (sem conta) não vê anúncios: eles são só do plano grátis, depois do teste.
 */
class Plano
{
    public const VITALICIO = 'vitalicio';
    public const TESTE     = 'teste';
    public const GRATIS    = 'gratis';

    private static ?bool $colunas = null;

    /** As colunas novas já existem? (o site segue funcionando antes do SQL ser rodado) */
    public static function temColunas(): bool
    {
        if (self::$colunas === null) {
            try {
                self::$colunas = Esquema::coluna('users', 'livro_comprado_em') && Esquema::coluna('users', 'objetivo');
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

    public static function de(?User $user): string
    {
        if (!$user) {
            return self::GRATIS;
        }
        if (self::comprouLivro($user)) {
            return self::VITALICIO;
        }

        return self::testeAte($user)->isFuture() ? self::TESTE : self::GRATIS;
    }

    public static function comprouLivro(?User $user): bool
    {
        return $user && self::temColunas() && !empty($user->livro_comprado_em);
    }

    /** Fim do teste: 45 dias a partir da criação da conta, nunca antes de 45 dias do lançamento dos planos. */
    public static function testeAte(User $user): Carbon
    {
        // sem data de criação (conta inserida à mão), o teste conta do lançamento: nunca vira teste eterno
        $inicio     = $user->created_at ? Carbon::parse($user->created_at) : Carbon::createFromTimestamp(0);
        $lancamento = Carbon::parse((string) config('planos.lancamento', '2026-10-29'))->startOfDay();

        return $inicio->max($lancamento)->copy()->addDays((int) config('planos.dias_teste', 45));
    }

    /** Dias que faltam no teste (0 fora do teste). */
    public static function diasDeTeste(User $user): int
    {
        $fim = self::testeAte($user);

        return $fim->isFuture() ? (int) ceil(Carbon::now()->diffInHours($fim) / 24) : 0;
    }

    /** Anúncios só para a conta no plano grátis (teste acabou e não comprou o livro); visitante não vê. */
    public static function veAnuncios(?User $user): bool
    {
        return $user !== null && self::de($user) === self::GRATIS;
    }

    public static function nome(string $plano): string
    {
        return [
            self::VITALICIO => 'Vitalício',
            self::TESTE     => 'Teste completo',
            self::GRATIS    => 'Grátis',
        ][$plano] ?? 'Grátis';
    }

    /** Limite do plano grátis para 'fichas' ou 'campanhas'; null = sem limite. */
    public static function limite(?User $user, string $recurso): ?int
    {
        if (self::de($user) !== self::GRATIS) {
            return null;
        }
        $n = config('planos.limites_gratis.' . $recurso);

        return $n === null ? null : (int) $n;
    }

    public static function fichas(User $user): int
    {
        return Personagem::withoutGlobalScope('dono')->where('user_id', $user->id)->count();
    }

    /** Campanhas em que a pessoa é mestre (entrar como jogador na campanha de outra pessoa não conta). */
    public static function campanhas(User $user): int
    {
        return Campanha::where('mestre_id', $user->id)->count();
    }

    /** Quantas fichas ainda cabem no plano (null = sem limite). */
    public static function fichasRestantes(User $user): ?int
    {
        $limite = self::limite($user, 'fichas');

        return $limite === null ? null : max(0, $limite - self::fichas($user));
    }

    public static function podeCriarCampanha(User $user): bool
    {
        $limite = self::limite($user, 'campanhas');

        return $limite === null || self::campanhas($user) < $limite;
    }

    public static function mensagemLimite(string $recurso): string
    {
        $n = (int) config('planos.limites_gratis.' . $recurso);

        return $recurso === 'campanhas'
            ? "O plano grátis permite ser mestre de $n campanha. Compre o livro do sistema para liberar campanhas sem limite."
            : "O plano grátis permite até $n fichas. Compre o livro do sistema para liberar fichas sem limite.";
    }
}
