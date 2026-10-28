<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Propriedades fisiomantes salvas na ficha (lista JSON: nome, fonte, ameaça, descrição e anotação).
 * O catálogo completo fica em resources/data/propriedades_fisiomantes.json.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fichas', 'propriedades_fisiomantes')) {
            return;
        }
        Schema::table('fichas', function (Blueprint $t) {
            $t->json('propriedades_fisiomantes')->nullable()->after('tecnicas');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('fichas', 'propriedades_fisiomantes')) {
            return;
        }
        Schema::table('fichas', function (Blueprint $t) {
            $t->dropColumn('propriedades_fisiomantes');
        });
    }
};
