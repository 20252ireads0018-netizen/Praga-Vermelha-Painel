<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jogos da campanha: Gladio (cartas do mestre e apostas de Dinheiro) e Pela Dama (xadrez com peças customizadas).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Dinheiro de cada membro na campanha (aposta do Gladio)
        if (!Schema::hasTable('jogo_carteiras')) {
            Schema::create('jogo_carteiras', function (Blueprint $t) {
                $t->id();
                $t->foreignId('campanha_id')->constrained('campanhas')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->bigInteger('dinheiro')->default(0);
                $t->timestamps();
                $t->unique(['campanha_id', 'user_id']);
            });
        }

        // Cartas de Gladio criadas pelo mestre
        if (!Schema::hasTable('gladio_cartas')) {
            Schema::create('gladio_cartas', function (Blueprint $t) {
                $t->id();
                $t->foreignId('campanha_id')->constrained('campanhas')->cascadeOnDelete();
                $t->string('nome', 60);
                $t->string('tipo', 10);               // lenda | feitico
                $t->unsignedSmallInteger('custo')->default(0);
                $t->unsignedSmallInteger('ataque')->default(0);
                $t->unsignedSmallInteger('vida')->default(0);
                $t->string('efeito', 20)->nullable(); // feitiço: dano, dano_todos, cura, comprar, fortalecer, energia
                $t->unsignedSmallInteger('valor')->default(0);
                $t->string('descricao', 500)->nullable();
                $t->mediumText('imagem')->nullable(); // data URL reduzida no navegador
                $t->timestamps();
            });
        }

        // Peças de Pela Dama criadas por cada jogador
        if (!Schema::hasTable('pela_dama_pecas')) {
            Schema::create('pela_dama_pecas', function (Blueprint $t) {
                $t->id();
                $t->foreignId('campanha_id')->constrained('campanhas')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->string('nome', 40);
                $t->string('simbolo', 8);
                $t->json('regras');
                $t->timestamps();
            });
        }

        // Partidas (desafio aberto, em andamento ou encerrada)
        if (!Schema::hasTable('jogo_partidas')) {
            Schema::create('jogo_partidas', function (Blueprint $t) {
                $t->id();
                $t->foreignId('campanha_id')->constrained('campanhas')->cascadeOnDelete();
                $t->string('jogo', 12);                // gladio | pela_dama
                $t->foreignId('jogador1_id')->constrained('users')->cascadeOnDelete();
                $t->foreignId('jogador2_id')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('convidado_id')->nullable()->constrained('users')->nullOnDelete(); // desafio só para essa pessoa
                $t->unsignedInteger('aposta')->default(0);
                $t->string('status', 12)->default('aberta'); // aberta | andamento | fim | cancelada
                $t->foreignId('vencedor_id')->nullable()->constrained('users')->nullOnDelete();
                $t->json('config')->nullable();        // exército do desafiante (Pela Dama)
                $t->longText('estado')->nullable();
                $t->unsignedInteger('versao')->default(1);
                $t->timestamps();
                $t->index(['campanha_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jogo_partidas');
        Schema::dropIfExists('pela_dama_pecas');
        Schema::dropIfExists('gladio_cartas');
        Schema::dropIfExists('jogo_carteiras');
    }
};
