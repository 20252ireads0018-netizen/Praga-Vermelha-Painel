<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salas online do Tabletop.
 *  - codigo:     código da sala (null = mesa sem sala aberta)
 *  - sala_dono:  id do usuário que abriu a sala (só ele muda linhas, colunas, fundo e nome, e encerra a sala)
 *  - versao:     sobe a cada mudança na sala; os jogadores só baixam o estado quando ela muda
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $t) {
            if (!Schema::hasColumn('mesas', 'codigo')) {
                $t->string('codigo', 8)->nullable()->unique();
            }
            if (!Schema::hasColumn('mesas', 'sala_dono')) {
                $t->unsignedBigInteger('sala_dono')->nullable();
            }
            if (!Schema::hasColumn('mesas', 'versao')) {
                $t->unsignedInteger('versao')->default(1);
            }
        });
    }

    public function down(): void
    {
        Schema::table('mesas', function (Blueprint $t) {
            $t->dropColumn(['codigo', 'sala_dono', 'versao']);
        });
    }
};