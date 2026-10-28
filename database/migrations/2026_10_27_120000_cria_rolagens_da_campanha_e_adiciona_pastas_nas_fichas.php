<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1) Corrige o "server error" ao salvar ficha:
 *    SQLSTATE[42S22] Unknown column 'pastas' in 'field list' (tabela fichas).
 * 2) Cria a tabela do pote de dados (rolagens públicas da campanha).
 *
 * Todas as etapas têm guarda (hasColumn/hasTable): pode rodar em qualquer banco
 * sem dar erro de "já existe", inclusive várias vezes pelo cron.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- 1) fichas.pastas ----
        if (Schema::hasTable('fichas') && !Schema::hasColumn('fichas', 'pastas')) {
            Schema::table('fichas', function (Blueprint $table) {
                $table->json('pastas')->nullable();
            });
        }

        // ---- 2) campanha_rolagens ----
        if (!Schema::hasTable('campanha_rolagens')) {
            Schema::create('campanha_rolagens', function (Blueprint $t) {
                $t->id();
                $t->foreignId('campanha_id')->constrained('campanhas')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->string('rotulo', 60)->nullable();   // ex.: "Ataque com a espada"
                $t->unsignedSmallInteger('qtd');        // quantidade de dados
                $t->unsignedSmallInteger('lados');      // faces de cada dado
                $t->string('extras', 60)->nullable();   // texto digitado, ex.: "+2+3-1"
                $t->integer('bonus')->default(0);       // soma dos extras
                $t->text('resultados');                 // JSON: [4, 6, 1]
                $t->integer('total');
                $t->timestamps();

                $t->index(['campanha_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campanha_rolagens');

        if (Schema::hasTable('fichas') && Schema::hasColumn('fichas', 'pastas')) {
            Schema::table('fichas', function (Blueprint $table) {
                $table->dropColumn('pastas');
            });
        }
    }
};