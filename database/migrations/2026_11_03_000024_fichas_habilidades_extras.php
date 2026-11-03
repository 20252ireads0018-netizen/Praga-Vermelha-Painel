<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Valores extras das habilidades da ficha, livres e com a origem de cada um: {"luta": [{"origem": "Talento X", "valor": 5}], ...}
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fichas') || Schema::hasColumn('fichas', 'habilidades_extras')) {
            return;
        }
        Schema::table('fichas', function (Blueprint $table) {
            $table->json('habilidades_extras')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('fichas') && Schema::hasColumn('fichas', 'habilidades_extras')) {
            Schema::table('fichas', function (Blueprint $table) {
                $table->dropColumn('habilidades_extras');
            });
        }
    }
};
