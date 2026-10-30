<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Dinheiro do personagem (aba Dinheiro da ficha; dá para apostar no Gladio). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fichas') && !Schema::hasColumn('fichas', 'dinheiro')) {
            Schema::table('fichas', function (Blueprint $t) {
                $t->unsignedBigInteger('dinheiro')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fichas', 'dinheiro')) {
            Schema::table('fichas', function (Blueprint $t) {
                $t->dropColumn('dinheiro');
            });
        }
    }
};
