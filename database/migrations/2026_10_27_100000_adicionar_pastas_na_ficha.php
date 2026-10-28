<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas', function (Blueprint $table) {
            if (!Schema::hasColumn('fichas', 'pastas')) {
                // {"talentos": [...], "tecnicas": [...], "anotacoes": [...]}
                $table->json('pastas')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Intencionalmente vazio: não apaga dados de fichas.
    }
};