<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajuste manual do total de pontos de habilidade (modo "ficha já existente").
 * Pode ser negativo: é somado ao total calculado (nível × BE + bônus + pontos extras).
 * A coluna só é criada se ainda não existir, então a migration é segura de repetir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas', function (Blueprint $t) {
            if (!Schema::hasColumn('fichas', 'pontos_habilidade_ajuste')) {
                $t->integer('pontos_habilidade_ajuste')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('fichas', function (Blueprint $t) {
            if (Schema::hasColumn('fichas', 'pontos_habilidade_ajuste')) {
                $t->dropColumn('pontos_habilidade_ajuste');
            }
        });
    }
};