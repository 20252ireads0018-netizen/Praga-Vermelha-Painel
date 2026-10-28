<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mods de atributo ajustados à mão (modo "ficha já existente").
 * Guarda só os mods que deixaram de seguir o atributo, ex.: {"forca": 10}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas', function (Blueprint $t) {
            if (!Schema::hasColumn('fichas', 'mods_atributos')) {
                $t->json('mods_atributos')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('fichas', function (Blueprint $t) {
            if (Schema::hasColumn('fichas', 'mods_atributos')) {
                $t->dropColumn('mods_atributos');
            }
        });
    }
};