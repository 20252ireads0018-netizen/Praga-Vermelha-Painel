<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fichas', function (Blueprint $table) {
            $table->integer('vida_atual')->nullable();
            $table->integer('vida_maxima')->nullable();
            $table->integer('sanidade_atual')->nullable();
            $table->integer('sanidade_maxima')->nullable();
            $table->integer('aura_atual')->nullable();
            $table->integer('aura_maxima')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fichas', function (Blueprint $table) {
            $table->dropColumn([
                'vida_atual', 'vida_maxima',
                'sanidade_atual', 'sanidade_maxima',
                'aura_atual', 'aura_maxima',
            ]);
        });
    }
};
