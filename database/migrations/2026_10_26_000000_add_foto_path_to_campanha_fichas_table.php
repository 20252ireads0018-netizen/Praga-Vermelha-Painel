<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Se a tabela do model CampanhaFicha tiver outro nome, troque aqui.
    private string $tabela = 'campanha_fichas';

    public function up(): void
    {
        if (!Schema::hasColumn($this->tabela, 'foto_path')) {
            Schema::table($this->tabela, function (Blueprint $table) {
                $table->string('foto_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn($this->tabela, 'foto_path')) {
            Schema::table($this->tabela, function (Blueprint $table) {
                $table->dropColumn('foto_path');
            });
        }
    }
};
