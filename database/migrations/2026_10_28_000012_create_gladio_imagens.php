<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Galeria de imagens da campanha para as cartas de Gladio.
 * O arquivo fica no disco "local" (fora do public): campanhas/{id}/galeria/...
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gladio_imagens')) {
            Schema::create('gladio_imagens', function (Blueprint $t) {
                $t->id();
                $t->foreignId('campanha_id')->constrained('campanhas')->cascadeOnDelete();
                $t->string('pasta', 60)->nullable(); // categoria livre (ex.: Vilões, Kuros)
                $t->string('nome', 120);
                $t->string('caminho', 255);
                $t->timestamps();
                $t->index(['campanha_id', 'pasta']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gladio_imagens');
    }
};
