<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fichas') || Schema::hasColumn('fichas', 'armaduras')) {
            return;
        }

        $depois = Schema::hasColumn('fichas', 'vitais_extras');

        Schema::table('fichas', function (Blueprint $table) use ($depois) {
            $coluna = $table->json('armaduras')->nullable();
            if ($depois) {
                $coluna->after('vitais_extras');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('fichas') && Schema::hasColumn('fichas', 'armaduras')) {
            Schema::table('fichas', function (Blueprint $table) {
                $table->dropColumn('armaduras');
            });
        }
    }
};
