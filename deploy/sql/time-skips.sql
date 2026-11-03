-- Time skips da campanha (migração 2026_11_02_000023_create_time_skips). Rodar uma vez no phpMyAdmin.
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

INSERT INTO migrations (migration, batch)
SELECT '2026_11_02_000023_create_time_skips', COALESCE(MAX(batch), 0) + 1 FROM migrations
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_02_000023_create_time_skips');
