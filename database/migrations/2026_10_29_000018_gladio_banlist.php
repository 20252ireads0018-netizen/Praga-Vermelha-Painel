<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Banlist de Gladio: limite de cópias por deck que o mestre define em cada carta (null = livre, 0 = banida). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gladio_cartas') && !Schema::hasColumn('gladio_cartas', 'limite')) {
            Schema::table('gladio_cartas', function (Blueprint $t) {
                $t->unsignedTinyInteger('limite')->nullable()->after('custo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gladio_cartas', 'limite')) {
            Schema::table('gladio_cartas', function (Blueprint $t) {
                $t->dropColumn('limite');
            });
        }
    }
};
