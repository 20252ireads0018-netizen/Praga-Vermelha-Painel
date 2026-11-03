<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Sou mestre": quem joga em campanhas pede permissão aos mestres delas para abrir o livro do mestre
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('livro_mestre_pedidos')) {
            return;
        }
        Schema::create('livro_mestre_pedidos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('mestre_id')->index();
            $table->string('status', 20)->default('pendente'); // pendente | aprovado | recusado
            $table->timestamps();
            $table->unique(['user_id', 'mestre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livro_mestre_pedidos');
    }
};
