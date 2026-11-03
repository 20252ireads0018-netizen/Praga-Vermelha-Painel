<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pedidos de compra do livro do sistema por Pix direto: a pessoa informa que pagou e um administrador aprova
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pix_pedidos')) {
            return;
        }
        Schema::create('pix_pedidos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('codigo', 25)->unique();
            $table->decimal('valor', 10, 2);
            $table->string('status', 20)->default('aguardando'); // aguardando | informado | aprovado | recusado
            $table->string('pagador', 80)->nullable();
            $table->timestamp('informado_em')->nullable();
            $table->timestamp('decidido_em')->nullable();
            $table->unsignedBigInteger('decidido_por')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pix_pedidos');
    }
};
