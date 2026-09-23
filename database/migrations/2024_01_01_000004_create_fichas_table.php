<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }

    public function up(): void
    {
        Schema::create('fichas', function (Blueprint $table){
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->onDelete('cascade');

            $table->string('nome');
            $table->string('raca');
            $table->string('maldicoes')->nullable();
            $table->integer('idade');

            $table->string('profissao')->nullable();
            $table->integer('nivel_mundano')->nullable();
            $table->integer('experiencia_mundana')->nullable();
            $table->string('porte_fisico')->nullable();
            $table->integer('be')->nullable();

            // Atributos
            $table->integer('forca')->nullable();
            $table->integer('constituicao')->nullable();
            $table->integer('destreza')->nullable();
            $table->integer('conhecimento')->nullable();
            $table->integer('carisma')->nullable();
            $table->integer('percepcao')->nullable();
            $table->integer('alma')->nullable();
            $table->integer('sorte')->nullable();

            // Habilidades — vamos guardar como JSON (explico abaixo)
            $table->json('habilidades')->nullable();

            // Itens e descrição
            $table->json('itens')->nullable();
            $table->text('descricao')->nullable();

            $table->timestamps();
            });
    }
};
