<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reclame aqui do painel: problemas e sugestões das contas, com a resposta dos administradores
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reclamacoes')) {
            return;
        }
        Schema::create('reclamacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('categoria', 20);
            $table->string('titulo', 120);
            $table->text('texto');
            $table->string('pagina', 255)->nullable();
            $table->string('status', 20)->default('aberta')->index(); // aberta | analise | resolvida | recusada
            $table->text('resposta')->nullable();
            $table->timestamp('respondida_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reclamacoes');
    }
};
