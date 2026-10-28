<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gladio: decks montados por cada jogador e palavras-chave das cartas (Sobrepujar, Ímpeto).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gladio_decks')) {
            Schema::create('gladio_decks', function (Blueprint $t) {
                $t->id();
                $t->foreignId('campanha_id')->constrained('campanhas')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->string('nome', 60);
                $t->json('cartas'); // { id da carta: quantidade }
                $t->timestamps();
                $t->index(['campanha_id', 'user_id']);
            });
        }

        if (Schema::hasTable('gladio_cartas') && !Schema::hasColumn('gladio_cartas', 'palavras')) {
            Schema::table('gladio_cartas', function (Blueprint $t) {
                $t->string('palavras', 100)->nullable()->after('valor'); // lista separada por vírgula
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gladio_decks');
        if (Schema::hasColumn('gladio_cartas', 'palavras')) {
            Schema::table('gladio_cartas', function (Blueprint $t) {
                $t->dropColumn('palavras');
            });
        }
    }
};
