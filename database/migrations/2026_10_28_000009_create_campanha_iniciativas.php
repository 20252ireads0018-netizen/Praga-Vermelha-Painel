<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Iniciativas rolatas dentro de uma campanha.
 * ficha_id nao tem FK: as fichas tem escopo proprio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('campanha_iniciativas')) {
            Schema::create('campanha_iniciativas', function (Blueprint $t) {
                $t->id();
                $t->foreignId('campanha_id')->constrained('campanhas')->cascadeOnDelete();
                $t->unsignedBigInteger('ficha_id')->index();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->string('nome', 120);
                $t->unsignedTinyInteger('dado');
                $t->integer('bonus');
                $t->integer('resultado');
                $t->timestamps();
                $t->index(['campanha_id', 'ficha_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campanha_iniciativas');
    }
};
