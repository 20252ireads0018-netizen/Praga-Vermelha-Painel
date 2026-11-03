<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Trilha da campanha: o que está tocando para todos (áudio da campanha ou YouTube), pausa, posição e os links salvos
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('campanhas') || Schema::hasColumn('campanhas', 'trilha')) {
            return;
        }
        Schema::table('campanhas', function (Blueprint $table) {
            $table->json('trilha')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('campanhas') && Schema::hasColumn('campanhas', 'trilha')) {
            Schema::table('campanhas', function (Blueprint $table) {
                $table->dropColumn('trilha');
            });
        }
    }
};
