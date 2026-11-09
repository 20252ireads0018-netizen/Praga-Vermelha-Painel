-- Aprimoramentos guardados e carreira na AMA (migração 2026_11_09_000031_fichas_aprimoramentos_carreira). Rodar uma vez no phpMyAdmin.
ALTER TABLE fichas ADD COLUMN aprimoramentos JSON NULL, ADD COLUMN carreira_ama JSON NULL;
INSERT INTO migrations (migration, batch)
SELECT '2026_11_09_000031_fichas_aprimoramentos_carreira', COALESCE(MAX(batch), 0) + 1 FROM migrations;
