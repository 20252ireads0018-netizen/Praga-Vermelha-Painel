<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imagem de fundo do banner da campanha (arquivo no disco local, fora do public).
 * A coluna só é criada se ainda não existir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campanhas', function (Blueprint $t) {
            if (!Schema::hasColumn('campanhas', 'banner_path')) {
                $t->string('banner_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('campanhas', function (Blueprint $t) {
            if (Schema::hasColumn('campanhas', 'banner_path')) {
                $t->dropColumn('banner_path');
            }
        });
    }
};