<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Potes secretos de rolagem dentro de uma campanha.
 * - campanha_potes: um pote criado por um jogador (ou pelo mestre).
 * - campanha_pote_membros: quem mais pode ver o pote (o criador e o mestre sempre veem).
 * - campanha_rolagens.pote_id: null = pote público; senão, a rolagem pertence ao pote.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('campanha_potes')) {
            Schema::create('campanha_potes', function (Blueprint $t) {
                $t->id();
                $t->foreignId('campanha_id')->constrained('campanhas')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->string('nome', 60);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('campanha_pote_membros')) {
            Schema::create('campanha_pote_membros', function (Blueprint $t) {
                $t->id();
                $t->foreignId('pote_id')->constrained('campanha_potes')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->timestamps();
                $t->unique(['pote_id', 'user_id']);
            });
        }

        if (Schema::hasTable('campanha_rolagens') && !Schema::hasColumn('campanha_rolagens', 'pote_id')) {
            Schema::table('campanha_rolagens', function (Blueprint $t) {
                $t->unsignedBigInteger('pote_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('campanha_rolagens', 'pote_id')) {
            Schema::table('campanha_rolagens', fn (Blueprint $t) => $t->dropColumn('pote_id'));
        }
        Schema::dropIfExists('campanha_pote_membros');
        Schema::dropIfExists('campanha_potes');
    }
};