<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga cada ficha (Personagem) e cada mesa (Mesa) a uma conta.
 * O nome da tabela vem do próprio model, então funciona mesmo que a sua
 * tabela se chame "personagens", "personagems", etc.
 */
return new class extends Migration
{
    /** @return array<int, class-string> */
    private function models(): array
    {
        return array_values(array_filter([
            \App\Models\Personagem::class,
            \App\Models\Mesa::class,
        ], 'class_exists'));
    }

    public function up(): void
    {
        foreach ($this->models() as $classe) {
            $tabela = (new $classe)->getTable();

            if (! Schema::hasTable($tabela) || Schema::hasColumn($tabela, 'user_id')) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $table) {
                // nullable: fichas antigas continuam existindo até serem atribuídas a alguém
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->models() as $classe) {
            $tabela = (new $classe)->getTable();

            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'user_id')) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};