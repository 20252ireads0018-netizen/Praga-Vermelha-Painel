<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Duas colunas JSON na ficha:
//  aprimoramentos: aprimoramentos guardados pelo jogador (os secretos que o mestre manda), usáveis em qualquer técnica:
//                  [{"id": "a1", "nome": "...", "tipo": "fixo|original", "custo": 9, "acumulativo": true}]
//  carreira_ama:   patente, grau e salário na AMA e os exércitos:
//                  {"patente": 3, "grau": 2, "salario": 4850.33, "exercitos": [{"nome", "descricao", "nivel", "aprimoramentos"}]}
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fichas')) {
            return;
        }
        foreach (['aprimoramentos', 'carreira_ama'] as $coluna) {
            if (!Schema::hasColumn('fichas', $coluna)) {
                Schema::table('fichas', fn (Blueprint $t) => $t->json($coluna)->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (['aprimoramentos', 'carreira_ama'] as $coluna) {
            if (Schema::hasTable('fichas') && Schema::hasColumn('fichas', $coluna)) {
                Schema::table('fichas', fn (Blueprint $t) => $t->dropColumn($coluna));
            }
        }
    }
};
