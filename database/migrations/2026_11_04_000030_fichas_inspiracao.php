<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Inspiração: contador gastável da ficha, que o jogador aumenta ou diminui (nunca abaixo de zero)
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fichas') || Schema::hasColumn('fichas', 'inspiracao')) {
            return;
        }
        Schema::table('fichas', function (Blueprint $table) {
            $table->unsignedSmallInteger('inspiracao')->default(0);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('fichas') && Schema::hasColumn('fichas', 'inspiracao')) {
            Schema::table('fichas', function (Blueprint $table) {
                $table->dropColumn('inspiracao');
            });
        }
    }
};
