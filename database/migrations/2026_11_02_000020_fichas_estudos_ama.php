<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Estudos na AMA da ficha: períodos, matérias, aulas, provas e Talentos de Grade escolhidos (JSON)
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fichas') || Schema::hasColumn('fichas', 'estudos_ama')) {
            return;
        }
        Schema::table('fichas', function (Blueprint $table) {
            $table->json('estudos_ama')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('fichas') && Schema::hasColumn('fichas', 'estudos_ama')) {
            Schema::table('fichas', function (Blueprint $table) {
                $table->dropColumn('estudos_ama');
            });
        }
    }
};
