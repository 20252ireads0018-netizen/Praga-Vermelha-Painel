<?php

namespace App\Support;

use App\Models\Campanha;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class AcessoMaldicoes
{
    private static array $cacheChave = [];
    private static array $cacheMestre = [];

    public static function ehMestre(?User $u): bool
    {
        if (!$u) {
            return false;
        }
        return self::$cacheMestre[$u->id] ??= Campanha::where('mestre_id', $u->id)->exists();
    }

    // Critério da chave sozinho.
    public static function temChaveValida(?User $u): bool
    {
        if (!$u) {
            return false;
        }
        if (isset(self::$cacheChave[$u->id])) {
            return self::$cacheChave[$u->id];
        }
        $ok = false;
        if (Esquema::coluna('users', 'chave_maldicoes') && Esquema::coluna('campanhas', 'chave_maldicoes')) {
            $chave = mb_strtolower(trim((string) $u->chave_maldicoes));
            if ($chave !== '') {
                $ok = Campanha::whereNotNull('chave_maldicoes')->pluck('chave_maldicoes')
                    ->contains(fn ($c) => mb_strtolower(trim((string) $c)) === $chave);
            }
        }
        return self::$cacheChave[$u->id] = $ok;
    }

    public static function podeVerRestritas(?User $u): bool
    {
        return self::ehMestre($u) || self::temChaveValida($u);
    }

    public static function lista(?User $u): array
    {
        $todas = config('maldicoes_lista', []);
        if (self::podeVerRestritas($u)) {
            return $todas;
        }
        return array_values(array_filter($todas, fn ($m) => empty($m['restrita'])));
    }

    public static function slugPermitido(string $slug, ?User $u): bool
    {
        foreach (config('maldicoes_lista', []) as $m) {
            if (($m['slug'] ?? null) === $slug) {
                if (empty($m['restrita'])) {
                    return true;
                }
                break;
            }
        }
        return self::podeVerRestritas($u);
    }

    // Limpa o cache (após gravar a chave na mesma requisição).
    public static function limpar(): void
    {
        self::$cacheChave = [];
        self::$cacheMestre = [];
    }
}
