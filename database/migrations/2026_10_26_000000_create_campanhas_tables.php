<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campanhas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->foreignId('mestre_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('sessao_ativa')->default(false);
            $table->dateTime('sessao_inicio')->nullable();
            $table->dateTime('som_em')->nullable();
            $table->timestamps();

            $table->index('mestre_id');
        });

        Schema::create('campanha_jogadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campanha_id')->constrained('campanhas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pendente'); // pendente | ativo | recusado
            $table->timestamps();

            $table->unique(['campanha_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('campanha_fichas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campanha_id')->constrained('campanhas')->cascadeOnDelete();
            // Sem FK: a tabela de fichas pertence a outro módulo.
            $table->unsignedBigInteger('ficha_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['campanha_id', 'ficha_id']);
            $table->index('ficha_id');
        });

        Schema::create('campanha_sons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campanha_id')->constrained('campanhas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nome');
            $table->string('arquivo');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('tamanho')->default(0); // bytes
            $table->timestamps();

            $table->index('campanha_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campanha_sons');
        Schema::dropIfExists('campanha_fichas');
        Schema::dropIfExists('campanha_jogadores');
        Schema::dropIfExists('campanhas');
    }
};