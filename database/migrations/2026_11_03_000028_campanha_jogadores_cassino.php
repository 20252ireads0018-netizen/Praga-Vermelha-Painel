<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// O mestre libera ou fecha o cassino da campanha (minijogos) para cada jogador: 1 = liberado (padrão), 0 = fechado
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('campanha_jogadores') || Schema::hasColumn('campanha_jogadores', 'cassino')) {
            return;
        }
        Schema::table('campanha_jogadores', function (Blueprint $table) {
            $table->boolean('cassino')->default(true);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('campanha_jogadores') && Schema::hasColumn('campanha_jogadores', 'cassino')) {
            Schema::table('campanha_jogadores', function (Blueprint $table) {
                $table->dropColumn('cassino');
            });
        }
    }
};
