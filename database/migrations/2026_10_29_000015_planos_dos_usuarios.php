<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planos da conta: o que a pessoa veio fazer (boas-vindas) e quando comprou o livro do sistema (plano vitalício).
 * O teste de 45 dias não precisa de coluna: sai de created_at (ver App\Support\Plano).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'objetivo')) {
                $table->string('objetivo', 10)->nullable(); // livros | jogar
            }
            if (!Schema::hasColumn('users', 'livro_comprado_em')) {
                $table->timestamp('livro_comprado_em')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }
        foreach (['objetivo', 'livro_comprado_em'] as $coluna) {
            if (Schema::hasColumn('users', $coluna)) {
                Schema::table('users', fn (Blueprint $table) => $table->dropColumn($coluna));
            }
        }
    }
};
