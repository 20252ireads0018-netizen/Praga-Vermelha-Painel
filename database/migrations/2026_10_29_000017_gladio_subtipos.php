<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Subtipos das cartas de Gladio ("hexomante, aluno", "kuro", "forma hexomante", "propriedade fisiomântica"). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gladio_cartas') && !Schema::hasColumn('gladio_cartas', 'subtipos')) {
            Schema::table('gladio_cartas', function (Blueprint $t) {
                $t->string('subtipos', 120)->nullable()->after('palavras'); // lista separada por vírgula
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gladio_cartas', 'subtipos')) {
            Schema::table('gladio_cartas', function (Blueprint $t) {
                $t->dropColumn('subtipos');
            });
        }
    }
};
