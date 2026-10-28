<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completa a tabela "fichas" com tudo o que o formulário de ficha envia.
 * Cada coluna só é criada se ainda não existir, então a migration é segura
 * mesmo que parte do schema já tenha sido criada antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas', function (Blueprint $t) {
            // --- colunas novas ---
            if (!Schema::hasColumn('fichas', 'talentos')) {
                $t->json('talentos')->nullable();               // [{titulo, descricao}]
            }
            if (!Schema::hasColumn('fichas', 'niveis_profissao')) {
                $t->json('niveis_profissao')->nullable();       // ["Lutador","Criminoso",...]
            }
            if (!Schema::hasColumn('fichas', 'pontos_extras_atributos')) {
                $t->unsignedSmallInteger('pontos_extras_atributos')->default(0);
            }
            if (!Schema::hasColumn('fichas', 'pontos_extras_habilidades')) {
                $t->unsignedSmallInteger('pontos_extras_habilidades')->default(0);
            }
            if (!Schema::hasColumn('fichas', 'nivel_hexomante')) {
                $t->unsignedSmallInteger('nivel_hexomante')->default(0);
            }
            if (!Schema::hasColumn('fichas', 'ameaca')) {
                $t->unsignedSmallInteger('ameaca')->default(0);
            }

            // --- colunas que o model já usa; só cria se por acaso faltarem ---
            foreach (['habilidades', 'itens', 'vitais_extras', 'anotacoes'] as $coluna) {
                if (!Schema::hasColumn('fichas', $coluna)) {
                    $t->json($coluna)->nullable();
                }
            }
            if (!Schema::hasColumn('fichas', 'descricao')) {
                $t->text('descricao')->nullable();
            }
            if (!Schema::hasColumn('fichas', 'maldicoes')) {
                $t->text('maldicoes')->nullable();
            }
        });

        // "maldicoes" guarda "Nome (Ameaça N); Nome (Ameaça N)..." e pode passar de 255
        // caracteres. Em Laravel 9 sem doctrine/dbal o change() falha: nesse caso ignore.
        try {
            Schema::table('fichas', function (Blueprint $t) {
                $t->text('maldicoes')->nullable()->change();
            });
        } catch (\Throwable $e) {
            // já é text, ou o driver não suporta change(); nada a fazer
        }
    }

    public function down(): void
    {
        Schema::table('fichas', function (Blueprint $t) {
            foreach (['talentos', 'niveis_profissao', 'pontos_extras_atributos',
                      'pontos_extras_habilidades', 'nivel_hexomante', 'ameaca'] as $coluna) {
                if (Schema::hasColumn('fichas', $coluna)) {
                    $t->dropColumn($coluna);
                }
            }
        });
    }
};
