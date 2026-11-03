<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Time skips da campanha: o mestre monta as regras (escolhas, ramos, atividades, propriedades, salário) em "config";
// cada jogador preenche as escolhas de um personagem em time_skip_respostas (ficha_id 0: personagem sem ficha).
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('time_skips')) {
            Schema::create('time_skips', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campanha_id')->index();
                $table->string('nome', 80);
                $table->string('status', 12)->default('rascunho'); // rascunho (só o mestre), aberto, fechado
                $table->json('config');
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('time_skip_respostas')) {
            Schema::create('time_skip_respostas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('time_skip_id')->index();
                $table->foreignId('user_id');
                $table->unsignedBigInteger('ficha_id')->default(0);
                $table->json('dados');
                $table->timestamps();
                $table->unique(['time_skip_id', 'user_id', 'ficha_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('time_skip_respostas');
        Schema::dropIfExists('time_skips');
    }
};
