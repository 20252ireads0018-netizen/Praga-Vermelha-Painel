<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gladio: comandante escolhido em cada deck (uma Lenda do próprio deck; sem escolha, ela é sorteada na partida).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gladio_decks') && !Schema::hasColumn('gladio_decks', 'comandante_id')) {
            Schema::table('gladio_decks', function (Blueprint $t) {
                $t->unsignedBigInteger('comandante_id')->nullable()->after('cartas');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gladio_decks', 'comandante_id')) {
            Schema::table('gladio_decks', function (Blueprint $t) {
                $t->dropColumn('comandante_id');
            });
        }
    }
};
