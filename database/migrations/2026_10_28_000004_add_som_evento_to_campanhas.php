<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Último evento de som da campanha (os jogadores consultam o som_seq no polling).
 * A tabela "campanhas" foi criada em produção sem estas colunas; cada uma só é
 * adicionada se ainda não existir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campanhas', function (Blueprint $t) {
            if (!Schema::hasColumn('campanhas', 'som_seq')) {
                $t->unsignedInteger('som_seq')->default(0);
            }
            if (!Schema::hasColumn('campanhas', 'som_id')) {
                $t->unsignedBigInteger('som_id')->nullable();
            }
            if (!Schema::hasColumn('campanhas', 'som_acao')) {
                $t->string('som_acao', 10)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('campanhas', function (Blueprint $t) {
            foreach (['som_seq', 'som_id', 'som_acao'] as $coluna) {
                if (Schema::hasColumn('campanhas', $coluna)) {
                    $t->dropColumn($coluna);
                }
            }
        });
    }
};