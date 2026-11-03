<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Schema::hasTable / hasColumn com memória: no MySQL cada chamada é uma consulta ao information_schema (lenta na
 * hospedagem compartilhada) e várias rodam em todo polling. Só o "existe" é lembrado (na requisição e por 1 hora no
 * cache); o "não existe" é consultado de novo toda vez, então rodar um SQL pendente passa a valer na hora.
 */
class Esquema
{
    public static function tabela(string $tabela): bool
    {
        return self::lembrar("t:{$tabela}", fn () => Schema::hasTable($tabela));
    }

    public static function coluna(string $tabela, string $coluna): bool
    {
        return self::lembrar("c:{$tabela}.{$coluna}", fn () => Schema::hasColumn($tabela, $coluna));
    }

    private static function lembrar(string $chave, \Closure $consulta): bool
    {
        $app = app();
        if (!$app->bound('esquema.memo')) {
            $app->instance('esquema.memo', new \ArrayObject());
        }
        $memo = $app->make('esquema.memo');
        if (isset($memo[$chave])) {
            return true;
        }

        try {
            if (Cache::get('esquema:' . $chave)) {
                return $memo[$chave] = true;
            }
        } catch (\Throwable $e) {
            // sem cache (ex.: tabela do cache ainda não criada): consulta direto
        }

        if (!$consulta()) {
            return false;
        }
        $memo[$chave] = true;
        try {
            Cache::put('esquema:' . $chave, true, 3600);
        } catch (\Throwable $e) {
        }

        return true;
    }
}
