-- Índices das consultas de polling (migração 2026_11_03_000029). Só aceleram as consultas; nenhum dado muda.
-- Rode no phpMyAdmin. Se algum já existir, o MySQL avisa "Duplicate key name" e os outros seguem normalmente.
CREATE INDEX campanha_rolagens_camp_pote_id_idx ON campanha_rolagens (campanha_id, pote_id, id);
CREATE INDEX jogo_partidas_jogo_status_upd_idx ON jogo_partidas (jogo, status, updated_at);
CREATE INDEX campanha_catalogos_camp_pont_idx ON campanha_catalogos (campanha_id, pontuacao, id);
INSERT INTO migrations (migration, batch) SELECT '2026_11_03_000029_indices_de_desempenho', COALESCE(MAX(batch), 0) + 1 FROM migrations
  WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_03_000029_indices_de_desempenho');
