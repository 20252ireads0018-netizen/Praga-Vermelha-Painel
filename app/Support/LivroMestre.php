<?php

namespace App\Support;

use App\Models\Campanha;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Livro do mestre sem chave: basta clicar em "Sou mestre". Quem joga em alguma campanha (como jogador) precisa da
 * permissão de cada mestre dessas campanhas, para não ler os segredos da própria mesa sem o mestre saber.
 */
class LivroMestre
{
    public const LIVRE = 'livre', PENDENTE = 'pendente', RECUSADO = 'recusado', SEM_PEDIDO = 'sem_pedido';

    private static ?bool $tabela = null;

    public static function temTabela(): bool
    {
        if (self::$tabela === null) {
            try {
                self::$tabela = Esquema::tabela('livro_mestre_pedidos');
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

    /** Mestres das campanhas em que a pessoa joga (participação ativa), sem ela mesma. */
    public static function mestres(User $user): Collection
    {
        return Campanha::with('mestre')
            ->whereHas('participantes', fn ($q) => $q->where('user_id', $user->id)->where('status', 'ativo'))
            ->where('mestre_id', '!=', $user->id)
            ->get()->pluck('mestre')->filter()->unique('id')->values();
    }

    /** Pedidos da pessoa, por mestre: [mestre_id => status]. */
    public static function pedidos(User $user): array
    {
        return self::temTabela()
            ? DB::table('livro_mestre_pedidos')->where('user_id', $user->id)->pluck('status', 'mestre_id')->all()
            : [];
    }

    /** livre (nenhum mestre ou todos aprovaram) | pendente | recusado | sem_pedido (ainda não pediu a algum). */
    public static function estado(User $user): string
    {
        $mestres = self::mestres($user);
        if ($mestres->isEmpty()) {
            return self::LIVRE;
        }
        $pedidos = self::pedidos($user);
        $status = $mestres->map(fn ($m) => $pedidos[$m->id] ?? null);
        if ($status->every(fn ($s) => $s === 'aprovado')) {
            return self::LIVRE;
        }
        if ($status->contains('recusado')) {
            return self::RECUSADO;
        }

        return $status->contains(null) ? self::SEM_PEDIDO : self::PENDENTE;
    }

    /** Pede (ou pede de novo, se foi recusado) a cada mestre que ainda não aprovou. */
    public static function pedir(User $user): void
    {
        if (!self::temTabela()) {
            return;
        }
        $pedidos = self::pedidos($user);
        foreach (self::mestres($user) as $mestre) {
            $atual = $pedidos[$mestre->id] ?? null;
            if ($atual === null) {
                DB::table('livro_mestre_pedidos')->insert(['user_id' => $user->id, 'mestre_id' => $mestre->id, 'status' => 'pendente', 'created_at' => now(), 'updated_at' => now()]);
            } elseif ($atual === 'recusado') {
                DB::table('livro_mestre_pedidos')->where('user_id', $user->id)->where('mestre_id', $mestre->id)->update(['status' => 'pendente', 'updated_at' => now()]);
            }
        }
    }

    /** Pedidos pendentes que chegaram a este mestre, com o nome de quem pediu. */
    public static function pendentesPara(User $mestre): Collection
    {
        if (!self::temTabela()) {
            return collect();
        }

        return DB::table('livro_mestre_pedidos as p')->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.mestre_id', $mestre->id)->where('p.status', 'pendente')
            ->orderBy('p.created_at')->get(['p.id', 'p.created_at', 'u.name', 'u.email']);
    }
}
