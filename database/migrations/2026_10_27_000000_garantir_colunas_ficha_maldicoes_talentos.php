<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas', function (Blueprint $table) {
            if (!Schema::hasColumn('fichas', 'nivel_hexomante')) {
                $table->unsignedSmallInteger('nivel_hexomante')->default(0);
            }
            if (!Schema::hasColumn('fichas', 'ameaca')) {
                $table->unsignedSmallInteger('ameaca')->default(0);
            }
            if (!Schema::hasColumn('fichas', 'talentos')) {
                $table->json('talentos')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Intencionalmente vazio: não apaga dados de fichas.
    }
};