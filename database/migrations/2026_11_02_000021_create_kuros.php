<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kuros da campanha: o mestre cria e organiza; pode entregar um Kuro a um jogador (user_id), que passa a vê-lo
// e controlar a vida atual e as anotações. A ficha (tipo, aprimoramentos, habilidades...) fica em "dados" (JSON).
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kuros')) {
            return;
        }
        Schema::create('kuros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campanha_id')->index();
            $table->foreignId('user_id')->nullable()->index(); // jogador que recebeu o Kuro (null: só o mestre)
            $table->string('nome', 80);
            $table->json('dados');
            $table->integer('pv_atual')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kuros');
    }
};
