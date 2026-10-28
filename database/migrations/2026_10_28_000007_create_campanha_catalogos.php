<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * campanha_catalogos: registros de catalogação de maldições feitos por jogadores
 * numa campanha. O índice (campanha_id, user_id, created_at) serve ao cooldown.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('campanha_catalogos')) {
            Schema::create('campanha_catalogos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('campanha_id')->constrained('campanhas')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->string('maldicao_slug', 120);
                $t->text('texto');
                $t->unsignedTinyInteger('pontuacao')->default(0);
                $t->json('detalhes')->nullable();
                $t->timestamps();

                $t->index(['campanha_id', 'user_id', 'created_at'], 'campanha_catalogos_cooldown_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campanha_catalogos');
    }
};
