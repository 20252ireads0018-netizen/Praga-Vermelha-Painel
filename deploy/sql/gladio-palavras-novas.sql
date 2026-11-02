-- Gladio: palavras-chave novas aplicadas às cartas (campanha 1). Rodar uma vez no phpMyAdmin.
-- Cada UPDATE só acrescenta a palavra (não apaga as que a carta já tem) e não repete se rodar de novo.

-- Pressa (chave antiga 'impeto'): quem precisa entrar batendo
UPDATE gladio_cartas SET palavras = CONCAT_WS(',', NULLIF(palavras, ''), 'impeto') WHERE campanha_id = 1 AND id IN (59, 150, 119, 89, 101) AND FIND_IN_SET('impeto', IFNULL(palavras, '')) = 0;
-- Desafiador: lutadores que ganham algo matando em combate
UPDATE gladio_cartas SET palavras = CONCAT_WS(',', NULLIF(palavras, ''), 'desafiador') WHERE campanha_id = 1 AND id IN (141, 110, 4, 140, 189) AND FIND_IN_SET('desafiador', IFNULL(palavras, '')) = 0;
-- Imbloqueável: quem tem efeito ao atacar o jogador diretamente
UPDATE gladio_cartas SET palavras = CONCAT_WS(',', NULLIF(palavras, ''), 'imbloqueavel') WHERE campanha_id = 1 AND id IN (36, 80, 19, 97, 7) AND FIND_IN_SET('imbloqueavel', IFNULL(palavras, '')) = 0;
-- Sombra: o Bisão sombroso e os Kuros
UPDATE gladio_cartas SET palavras = CONCAT_WS(',', NULLIF(palavras, ''), 'sombra') WHERE campanha_id = 1 AND id IN (79, 76, 99, 153, 127) AND FIND_IN_SET('sombra', IFNULL(palavras, '')) = 0;
-- Furtividade: os pequenos espertos (olham, roubam, escapam)
UPDATE gladio_cartas SET palavras = CONCAT_WS(',', NULLIF(palavras, ''), 'furtividade') WHERE campanha_id = 1 AND id IN (122, 11, 138, 186, 2) AND FIND_IN_SET('furtividade', IFNULL(palavras, '')) = 0;
-- Flanquear: guardas e atiradores de primeira linha
UPDATE gladio_cartas SET palavras = CONCAT_WS(',', NULLIF(palavras, ''), 'flanquear') WHERE campanha_id = 1 AND id IN (120, 33, 90, 117, 142) AND FIND_IN_SET('flanquear', IFNULL(palavras, '')) = 0;
-- Exaltado: os diretores fortalecem o aluno que ataca sozinho
UPDATE gladio_cartas SET palavras = CONCAT_WS(',', NULLIF(palavras, ''), 'exaltado') WHERE campanha_id = 1 AND id IN (39, 77, 70, 72, 71) AND FIND_IN_SET('exaltado', IFNULL(palavras, '')) = 0;
-- Bravura: quem vive de feitiços
UPDATE gladio_cartas SET palavras = CONCAT_WS(',', NULLIF(palavras, ''), 'bravura') WHERE campanha_id = 1 AND id IN (137, 181, 205, 106, 46) AND FIND_IN_SET('bravura', IFNULL(palavras, '')) = 0;
-- Grito de guerra: líderes de bando
UPDATE gladio_cartas SET palavras = CONCAT_WS(',', NULLIF(palavras, ''), 'grito') WHERE campanha_id = 1 AND id IN (116, 113, 196, 86, 96) AND FIND_IN_SET('grito', IFNULL(palavras, '')) = 0;
-- Bushido: os duelistas (Friga, Lucas e Friga, Serrotinho, Aura)
UPDATE gladio_cartas SET palavras = CONCAT_WS(',', NULLIF(palavras, ''), 'bushido') WHERE campanha_id = 1 AND id IN (82, 194, 115, 63, 87) AND FIND_IN_SET('bushido', IFNULL(palavras, '')) = 0;
-- Persistir: os que insistem em voltar (Malditos, Cabrito, Pedra, Homem sapo)
UPDATE gladio_cartas SET palavras = CONCAT_WS(',', NULLIF(palavras, ''), 'persistir') WHERE campanha_id = 1 AND id IN (197, 85, 12, 35, 178) AND FIND_IN_SET('persistir', IFNULL(palavras, '')) = 0;
-- Imortal: os que voltam mais fortes
UPDATE gladio_cartas SET palavras = CONCAT_WS(',', NULLIF(palavras, ''), 'imortal') WHERE campanha_id = 1 AND id IN (64, 108, 204, 92, 109) AND FIND_IN_SET('imortal', IFNULL(palavras, '')) = 0;
