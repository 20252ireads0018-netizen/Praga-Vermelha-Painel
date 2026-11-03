<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices das consultas feitas em polling (só aceleram; nenhum dado muda):
 * - rolagens do pote: campanha_id + pote_id, a partir de um id;
 * - miniatura do Gladio em todas as páginas: partidas em andamento por jogo, das mais recentes;
 * - catálogo da campanha ordenado por pontuação.
 * SQL equivalente para o phpMyAdmin em deploy/sql/indices-desempenho.sql.
 */
return new class extends Migration
{
    private const INDICES = [
        ['campanha_rolagens', ['campanha_id', 'pote_id', 'id'], 'campanha_rolagens_camp_pote_id_idx', 'pote_id'],
        ['jogo_partidas', ['jogo', 'status', 'updated_at'], 'jogo_partidas_jogo_status_upd_idx', 'jogo'],
        ['campanha_catalogos', ['campanha_id', 'pontuacao', 'id'], 'campanha_catalogos_camp_pont_idx', 'pontuacao'],
    ];

    public function up(): void
    {
        foreach (self::INDICES as [$tabela, $colunas, $nome, $exige]) {
            if (Schema::hasTable($tabela) && Schema::hasColumn($tabela, $exige) && !Schema::hasIndex($tabela, $nome)) {
                Schema::table($tabela, fn (Blueprint $t) => $t->index($colunas, $nome));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDICES as [$tabela, , $nome]) {
            if (Schema::hasTable($tabela) && Schema::hasIndex($tabela, $nome)) {
                Schema::table($tabela, fn (Blueprint $t) => $t->dropIndex($nome));
            }
        }
    }
};
