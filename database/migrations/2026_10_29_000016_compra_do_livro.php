<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compra do livro do sistema pelo Asaas: CPF/CNPJ (o Asaas exige para cobrar) e a última cobrança aberta,
 * para reaproveitar a fatura e conferir o pagamento quando a pessoa volta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'cpf_cnpj')) {
                $table->string('cpf_cnpj', 14)->nullable();
            }
            if (!Schema::hasColumn('users', 'asaas_cobranca_id')) {
                $table->string('asaas_cobranca_id', 40)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }
        foreach (['cpf_cnpj', 'asaas_cobranca_id'] as $coluna) {
            if (Schema::hasColumn('users', $coluna)) {
                Schema::table('users', fn (Blueprint $table) => $table->dropColumn($coluna));
            }
        }
    }
};
