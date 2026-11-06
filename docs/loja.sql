-- LOJA + RECOMPENSA DE BATALHA
--
-- Como rodar: phpMyAdmin > banco "monstroguelselnius" > aba SQL > colar tudo > Executar.
-- Rode DEPOIS do inventario.sql. Pode rodar mais de uma vez sem perder dados.
-- Usa "ADD COLUMN IF NOT EXISTS", que o MariaDB do XAMPP aceita.

SET NAMES utf8mb4;

-- a tabela batalha passa a guardar o resultado e o dinheiro ganho
-- (o servidor abre a batalha ao entrar no jogo e paga a recompensa uma unica vez ao terminar)
ALTER TABLE batalha MODIFY id_batalha INT NOT NULL AUTO_INCREMENT;

ALTER TABLE batalha
    ADD COLUMN IF NOT EXISTS status        VARCHAR(15) NOT NULL DEFAULT 'em_andamento',  -- em_andamento, vitoria, derrota ou fuga
    ADD COLUMN IF NOT EXISTS recompensa    INT         NOT NULL DEFAULT 0,               -- pokedolares ganhos
    ADD COLUMN IF NOT EXISTS iniciada_em   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS finalizada_em DATETIME    NULL;

-- todo jogador novo comeca com 3 pocoes de vida e mais nada
-- (quem ja tem conta continua com os itens que tem)
UPDATE item_loja SET kit_inicial = CASE WHEN chave = 'potion' THEN 3 ELSE 0 END;
