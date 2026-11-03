-- SQL pendente de 02/11/2026 (Estudos AMA, Kuros, Trilha e Time skips). Rodar no phpMyAdmin.
-- Os ALTER TABLE dão erro "Duplicate column" se a coluna já existir: nesse caso aquela linha já foi rodada, siga para a próxima.

-- 1) Ficha: Estudos na AMA (migração 2026_11_02_000020)
ALTER TABLE fichas ADD COLUMN estudos_ama JSON NULL;

-- 2) Kuros da campanha (migração 2026_11_02_000021)
CREATE TABLE IF NOT EXISTS kuros (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    campanha_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    nome VARCHAR(80) NOT NULL,
    dados JSON NOT NULL,
    pv_atual INT NULL,
    notas TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX kuros_campanha_id_index (campanha_id),
    INDEX kuros_user_id_index (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Trilha da campanha (migração 2026_11_02_000022)
ALTER TABLE campanhas ADD COLUMN trilha JSON NULL;

-- 4) Time skips (migração 2026_11_02_000023)
CREATE TABLE IF NOT EXISTS time_skips (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    campanha_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(80) NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'rascunho',
    config JSON NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX time_skips_campanha_id_index (campanha_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS time_skip_respostas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    time_skip_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    ficha_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    dados JSON NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX time_skip_respostas_time_skip_id_index (time_skip_id),
    UNIQUE KEY time_skip_respostas_unica (time_skip_id, user_id, ficha_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
