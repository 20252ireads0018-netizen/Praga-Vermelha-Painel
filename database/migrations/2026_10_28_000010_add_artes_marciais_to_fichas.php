<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Artes marciais da ficha: nível, experiência de arte e talentos customizados de cada arte (JSON)
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fichas') || Schema::hasColumn('fichas', 'artes_marciais')) {
            return;
        }

        $depois = Schema::hasColumn('fichas', 'armaduras');

        Schema::table('fichas', function (Blueprint $table) use ($depois) {
            $coluna = $table->json('artes_marciais')->nullable();
            if ($depois) {
                $coluna->after('armaduras');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('fichas') && Schema::hasColumn('fichas', 'artes_marciais')) {
            Schema::table('fichas', function (Blueprint $table) {
                $table->dropColumn('artes_marciais');
            });
        }
    }
};
