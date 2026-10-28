<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campanhas') && !Schema::hasColumn('campanhas', 'chave_maldicoes')) {
            Schema::table('campanhas', function (Blueprint $table) {
                $table->string('chave_maldicoes', 60)->nullable();
            });
        }

        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'chave_maldicoes')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('chave_maldicoes', 60)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'chave_maldicoes')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('chave_maldicoes');
            });
        }

        if (Schema::hasTable('campanhas') && Schema::hasColumn('campanhas', 'chave_maldicoes')) {
            Schema::table('campanhas', function (Blueprint $table) {
                $table->dropColumn('chave_maldicoes');
            });
        }
    }
};
