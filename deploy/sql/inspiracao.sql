-- Inspiração da ficha (migração 2026_11_04_000030_fichas_inspiracao). Rodar uma vez no phpMyAdmin.
ALTER TABLE fichas ADD COLUMN inspiracao SMALLINT UNSIGNED NOT NULL DEFAULT 0;
INSERT INTO migrations (migration, batch)
SELECT '2026_11_04_000030_fichas_inspiracao', COALESCE(MAX(batch), 0) + 1 FROM migrations;
