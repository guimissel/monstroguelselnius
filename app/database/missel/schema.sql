DROP VIEW IF EXISTS vw_vendas_loja;
DROP VIEW IF EXISTS vw_pokedex;
DROP VIEW IF EXISTS vw_colecao_usuario;
DROP VIEW IF EXISTS vw_inventario_usuario;
DROP VIEW IF EXISTS vw_ranking_jogadores;
DROP VIEW IF EXISTS vw_historico_batalhas;
DROP VIEW IF EXISTS vw_eficacias_tipo;
DROP VIEW IF EXISTS vw_imunidades_monstro;
DROP VIEW IF EXISTS vw_fraquezas_monstro;
DROP VIEW IF EXISTS vw_evolucoes_monstro;
DROP VIEW IF EXISTS vw_batalhas_usuario;
DROP VIEW IF EXISTS vw_monstros_usuario;

DROP TRIGGER IF EXISTS trg_batalha_iniciada;
DROP TRIGGER IF EXISTS trg_batalha_finalizada;
DROP TRIGGER IF EXISTS trg_usuario_cadastro;
DROP TRIGGER IF EXISTS trg_usuario_antes_de_alterar;
DROP TRIGGER IF EXISTS trg_usuario_depois_de_alterar;
DROP TRIGGER IF EXISTS trg_usuario_exclusao;
DROP TRIGGER IF EXISTS trg_evolucao_monstro;

DROP TABLE IF EXISTS monstro_batalha_usuario;
DROP TABLE IF EXISTS batalha_robo;
DROP TABLE IF EXISTS equipe_usuario;
DROP TABLE IF EXISTS pokemon_usuario;
DROP TABLE IF EXISTS monstro_usuario;
DROP TABLE IF EXISTS log_loja;
DROP TABLE IF EXISTS log_usuario;
DROP TABLE IF EXISTS `log`;
DROP TABLE IF EXISTS batalha;
DROP TABLE IF EXISTS item_usuario;
DROP TABLE IF EXISTS item_loja;
DROP TABLE IF EXISTS pokemon_basico;
DROP TABLE IF EXISTS ataque_possivel_monstro;
DROP TABLE IF EXISTS ataque;
DROP TABLE IF EXISTS imagem_monstro;
DROP TABLE IF EXISTS tipo_imagem;
DROP TABLE IF EXISTS tipo_monstro;
DROP TABLE IF EXISTS evolucao_monstro;
DROP TABLE IF EXISTS evolucao;
DROP TABLE IF EXISTS imunidades_tipo;
DROP TABLE IF EXISTS fraquezas_tipo;
DROP TABLE IF EXISTS eficacias_tipo;
DROP TABLE IF EXISTS imune_tipo;
DROP TABLE IF EXISTS fraqueza_tipo;
DROP TABLE IF EXISTS eficaz_tipo;
DROP TABLE IF EXISTS categoria_dano;
DROP TABLE IF EXISTS tipo;
DROP TABLE IF EXISTS efeito;
DROP TABLE IF EXISTS monstro;
DROP TABLE IF EXISTS usuario;

CREATE DATABASE IF NOT EXISTS monstroguelselnius DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE monstroguelselnius;
SET NAMES utf8mb4;

CREATE TABLE usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    pokedolares INT DEFAULT 0 NOT NULL,
    is_bot BOOLEAN DEFAULT FALSE NOT NULL,
    is_ativo BOOLEAN DEFAULT TRUE NOT NULL,
    UNIQUE KEY uq_usuario_nome (nome),
    CHECK (pokedolares >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE efeito (
    id_efeito INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    is_ativo BOOLEAN DEFAULT TRUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tipo_imagem (
    id_tipo_imagem INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    UNIQUE KEY uq_tipo_imagem_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE item_loja (
    id_item_loja INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(30),
    nome VARCHAR(255) NOT NULL,
    descricao VARCHAR(255) NOT NULL DEFAULT '',
    categoria VARCHAR(20) NOT NULL DEFAULT 'item',
    preco INT DEFAULT 0 NOT NULL,
    imagem VARCHAR(100) NOT NULL DEFAULT '',
    cura_hp INT NOT NULL DEFAULT 0,
    cura_status VARCHAR(100),
    reviver BOOLEAN NOT NULL DEFAULT FALSE,
    kit_inicial INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_item_loja_chave (chave),
    CHECK (preco >= 0),
    CHECK (kit_inicial >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE monstro (
    id_monstro INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    vida_base INT NOT NULL,
    ataque_base INT NOT NULL,
    defesa_base INT NOT NULL,
    ataque_esp_base INT NOT NULL,
    defesa_esp_base INT NOT NULL,
    velocidade_base INT NOT NULL,
    is_ativo BOOLEAN DEFAULT TRUE NOT NULL,
    CHECK (vida_base >= 0), CHECK (ataque_base >= 0), CHECK (defesa_base >= 0),
    CHECK (ataque_esp_base >= 0), CHECK (defesa_esp_base >= 0), CHECK (velocidade_base >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE item_usuario (
    id_usuario INT NOT NULL,
    id_item_loja INT NOT NULL,
    quantidade INT DEFAULT 0 NOT NULL,
    PRIMARY KEY (id_usuario, id_item_loja),
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_item_loja) REFERENCES item_loja(id_item_loja) ON DELETE CASCADE ON UPDATE CASCADE,
    CHECK (quantidade >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE monstro_usuario (
    id_monstro INT NOT NULL,
    id_usuario INT NOT NULL,
    experiencia INT DEFAULT 0 NOT NULL,
    PRIMARY KEY (id_monstro, id_usuario),
    FOREIGN KEY (id_monstro) REFERENCES monstro(id_monstro) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
    CHECK (experiencia >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE evolucao (
    id_monstro_base INT NOT NULL,
    id_monstro_evoluido INT NOT NULL,
    pokedolares_necessarios INT DEFAULT 0 NOT NULL,
    PRIMARY KEY (id_monstro_base, id_monstro_evoluido),
    FOREIGN KEY (id_monstro_base) REFERENCES monstro(id_monstro) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_monstro_evoluido) REFERENCES monstro(id_monstro) ON DELETE RESTRICT ON UPDATE CASCADE,
    CHECK (pokedolares_necessarios >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE evolucao_monstro (
    id_monstro INT NOT NULL,
    id_evolucao INT NOT NULL,
    PRIMARY KEY (id_monstro),
    FOREIGN KEY (id_monstro) REFERENCES monstro(id_monstro) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_evolucao) REFERENCES monstro(id_monstro) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tipo (
    id_tipo INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    chave VARCHAR(20),
    UNIQUE KEY uq_tipo_nome (nome),
    UNIQUE KEY uq_tipo_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categoria_dano (
    id_categoria_dano INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    UNIQUE KEY uq_categoria_dano_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE eficacias_tipo (
    id_tipo INT NOT NULL,
    id_tipo_eficacia INT NOT NULL,
    PRIMARY KEY (id_tipo, id_tipo_eficacia),
    FOREIGN KEY (id_tipo) REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo_eficacia) REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fraquezas_tipo (
    id_tipo INT NOT NULL,
    id_tipo_eficacia INT NOT NULL,
    PRIMARY KEY (id_tipo, id_tipo_eficacia),
    FOREIGN KEY (id_tipo) REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo_eficacia) REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE imunidades_tipo (
    id_tipo INT NOT NULL,
    id_tipo_imunidade INT NOT NULL,
    PRIMARY KEY (id_tipo, id_tipo_imunidade),
    FOREIGN KEY (id_tipo) REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo_imunidade) REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tipo_monstro (
    id_monstro INT NOT NULL,
    id_tipo INT NOT NULL,
    ordem TINYINT NOT NULL DEFAULT 1,
    PRIMARY KEY (id_monstro, id_tipo),
    UNIQUE KEY uq_tipo_monstro_ordem (id_monstro, ordem),
    FOREIGN KEY (id_monstro) REFERENCES monstro(id_monstro) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo) REFERENCES tipo(id_tipo) ON DELETE RESTRICT ON UPDATE CASCADE,
    CHECK (ordem IN (1, 2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE imagem_monstro (
    id_imagem_monstro INT AUTO_INCREMENT PRIMARY KEY,
    id_monstro INT NOT NULL,
    caminho VARCHAR(255) NOT NULL,
    id_tipo_imagem INT NULL,
    FOREIGN KEY (id_monstro) REFERENCES monstro(id_monstro) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo_imagem) REFERENCES tipo_imagem(id_tipo_imagem) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ataque (
    id_ataque INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255),
    poder INT,
    precisao FLOAT,
    chance_efeito FLOAT,
    is_ativo BOOLEAN DEFAULT TRUE NOT NULL,
    id_categoria_dano INT NOT NULL,
    id_tipo INT NOT NULL,
    id_efeito INT,
    FOREIGN KEY (id_categoria_dano) REFERENCES categoria_dano(id_categoria_dano) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo) REFERENCES tipo(id_tipo) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_efeito) REFERENCES efeito(id_efeito) ON DELETE RESTRICT ON UPDATE CASCADE,
    CHECK (poder IS NULL OR poder >= 0),
    CHECK (precisao IS NULL OR (precisao BETWEEN 0 AND 100)),
    CHECK (chance_efeito IS NULL OR (chance_efeito BETWEEN 0 AND 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ataque_possivel_monstro (
    id_ataque INT NOT NULL,
    id_monstro INT NOT NULL,
    PRIMARY KEY (id_ataque, id_monstro),
    FOREIGN KEY (id_ataque) REFERENCES ataque(id_ataque) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_monstro) REFERENCES monstro(id_monstro) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE batalha (
    id_batalha INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_usuario_2 INT NULL,
    turno_atual INT DEFAULT 1 NOT NULL,
    cooldown_troca_1 INT DEFAULT 0 NOT NULL,
    cooldown_troca_2 INT DEFAULT 0 NOT NULL,
    is_boss BOOLEAN DEFAULT FALSE NOT NULL,
    status VARCHAR(15) NOT NULL DEFAULT 'em_andamento',
    recompensa INT NOT NULL DEFAULT 0,
    iniciada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizada_em DATETIME NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_usuario_2) REFERENCES usuario(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE,
    CHECK (status IN ('em_andamento','vitoria','derrota','fuga')),
    CHECK (recompensa >= 0),
    CHECK (turno_atual >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE batalha_robo (
    id_batalha INT NOT NULL,
    posicao TINYINT NOT NULL,
    id_pokemon INT NOT NULL,
    PRIMARY KEY (id_batalha, posicao),
    FOREIGN KEY (id_batalha) REFERENCES batalha(id_batalha) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_pokemon) REFERENCES monstro(id_monstro) ON DELETE RESTRICT ON UPDATE CASCADE,
    CHECK (posicao BETWEEN 1 AND 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `log` (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    id_batalha INT NOT NULL,
    id_usuario INT NULL,
    evento VARCHAR(15) NOT NULL DEFAULT 'iniciada',
    status VARCHAR(15),
    recompensa INT NOT NULL DEFAULT 0,
    descricao VARCHAR(255),
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_batalha) REFERENCES batalha(id_batalha) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE log_usuario (
    id_log_usuario INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    evento VARCHAR(20) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE log_loja (
    id_log_loja INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_item_loja INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario INT NOT NULL,
    total INT NOT NULL,
    estornada BOOLEAN NOT NULL DEFAULT FALSE,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (quantidade > 0), CHECK (preco_unitario >= 0), CHECK (total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE monstro_batalha_usuario (
    id_monstro INT NOT NULL,
    id_batalha INT NOT NULL,
    id_usuario INT NOT NULL,
    is_ativo BOOLEAN DEFAULT FALSE NOT NULL,
    vida_atual INT NOT NULL,
    id_efeito_atual INT,
    turnos_restantes_efeito INT,
    id_ataque_1 INT NOT NULL,
    id_ataque_2 INT,
    id_ataque_3 INT,
    id_ataque_4 INT,
    PRIMARY KEY (id_batalha, id_usuario, id_monstro),
    FOREIGN KEY (id_monstro) REFERENCES monstro(id_monstro) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_batalha) REFERENCES batalha(id_batalha) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_efeito_atual) REFERENCES efeito(id_efeito) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_ataque_1) REFERENCES ataque(id_ataque) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_ataque_2) REFERENCES ataque(id_ataque) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_ataque_3) REFERENCES ataque(id_ataque) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_ataque_4) REFERENCES ataque(id_ataque) ON DELETE RESTRICT ON UPDATE CASCADE,
    CHECK (vida_atual >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pokemon_basico (
    id_pokemon INT NOT NULL PRIMARY KEY,
    nome VARCHAR(30) NOT NULL,
    FOREIGN KEY (id_pokemon) REFERENCES monstro(id_monstro) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE equipe_usuario (
    id_usuario INT NOT NULL,
    posicao TINYINT NOT NULL,
    id_pokemon INT NOT NULL,
    PRIMARY KEY (id_usuario, posicao),
    UNIQUE KEY uq_equipe_pokemon (id_usuario, id_pokemon),
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_pokemon) REFERENCES monstro(id_monstro) ON DELETE RESTRICT ON UPDATE CASCADE,
    CHECK (posicao BETWEEN 1 AND 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pokemon_usuario (
    id_usuario INT NOT NULL,
    id_pokemon INT NOT NULL,
    conquistado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario, id_pokemon),
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_pokemon) REFERENCES monstro(id_monstro) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sincronizacao (
    id_sincronizacao INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    versao INT NOT NULL,
    sincronizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE auditoria (
    id_auditoria INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    acao VARCHAR(255) NOT NULL,
    tabela_afetada VARCHAR(255) NOT NULL,
    id_registro_afetado INT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DELIMITER $$

CREATE TRIGGER trg_batalha_iniciada AFTER INSERT ON batalha
FOR EACH ROW
BEGIN
    INSERT INTO `log` (id_batalha, id_usuario, evento, status, recompensa, descricao)
    VALUES (NEW.id_batalha, NEW.id_usuario, 'iniciada', 'em_andamento', 0, 'Batalha iniciada');
END$$

CREATE TRIGGER trg_batalha_finalizada AFTER UPDATE ON batalha
FOR EACH ROW
BEGIN
    IF OLD.status = 'em_andamento' AND NEW.status <> 'em_andamento' THEN
        INSERT INTO `log` (id_batalha, id_usuario, evento, status, recompensa, descricao)
        VALUES (NEW.id_batalha, NEW.id_usuario, 'finalizada', NEW.status, NEW.recompensa,
                CONCAT('Batalha terminou em ', NEW.status));
    END IF;
END$$

CREATE TRIGGER trg_usuario_cadastro AFTER INSERT ON usuario
FOR EACH ROW
BEGIN
    INSERT INTO log_usuario (id_usuario, nome, evento)
    VALUES (NEW.id_usuario, NEW.nome, 'cadastro');
END$$

CREATE TRIGGER trg_usuario_antes_de_alterar BEFORE UPDATE ON usuario
FOR EACH ROW
BEGIN
    IF NEW.pokedolares < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saldo de pokedolares nao pode ficar negativo';
    END IF;
END$$

CREATE TRIGGER trg_usuario_depois_de_alterar AFTER UPDATE ON usuario
FOR EACH ROW
BEGIN
    IF OLD.nome <> NEW.nome THEN
        INSERT INTO log_usuario (id_usuario, nome, evento)
        VALUES (NEW.id_usuario, NEW.nome, 'troca_nome');
    END IF;
    IF OLD.senha <> NEW.senha THEN
        INSERT INTO log_usuario (id_usuario, nome, evento)
        VALUES (NEW.id_usuario, NEW.nome, 'troca_senha');
    END IF;
END$$

CREATE TRIGGER trg_usuario_exclusao BEFORE DELETE ON usuario
FOR EACH ROW
BEGIN
    INSERT INTO log_usuario (id_usuario, nome, evento)
    VALUES (OLD.id_usuario, OLD.nome, 'exclusao');
END$$

CREATE TRIGGER trg_evolucao_monstro AFTER INSERT ON evolucao
FOR EACH ROW
BEGIN
    INSERT IGNORE INTO evolucao_monstro (id_monstro, id_evolucao)
    VALUES (NEW.id_monstro_base, NEW.id_monstro_evoluido);
END$$

DELIMITER ;

CREATE OR REPLACE VIEW vw_historico_batalhas AS
SELECT b.id_batalha, b.id_usuario, u.nome AS jogador,
       COALESCE(u2.nome, 'Robo') AS adversario, b.status, b.recompensa,
       b.iniciada_em, b.finalizada_em, b.is_boss,
       TIMESTAMPDIFF(SECOND, b.iniciada_em, b.finalizada_em) AS duracao_segundos
FROM batalha b
JOIN usuario u ON u.id_usuario = b.id_usuario
LEFT JOIN usuario u2 ON u2.id_usuario = b.id_usuario_2;

CREATE OR REPLACE VIEW vw_ranking_jogadores AS
SELECT u.id_usuario, u.nome, u.pokedolares,
       COUNT(b.id_batalha) AS batalhas,
       COALESCE(SUM(b.status = 'vitoria'), 0) AS vitorias,
       COALESCE(SUM(b.status = 'derrota'), 0) AS derrotas,
       COALESCE(SUM(b.status = 'fuga'), 0) AS fugas,
       COALESCE(SUM(b.recompensa), 0) AS total_ganho
FROM usuario u
LEFT JOIN batalha b ON b.id_usuario = u.id_usuario AND b.status <> 'em_andamento'
WHERE u.is_bot = FALSE
GROUP BY u.id_usuario, u.nome, u.pokedolares;

CREATE OR REPLACE VIEW vw_inventario_usuario AS
SELECT iu.id_usuario, u.nome AS jogador, i.chave, i.nome AS item,
       i.categoria, iu.quantidade, iu.quantidade * i.preco AS valor_total
FROM item_usuario iu
JOIN usuario u ON u.id_usuario = iu.id_usuario
JOIN item_loja i ON i.id_item_loja = iu.id_item_loja
WHERE iu.quantidade > 0;

CREATE OR REPLACE VIEW vw_colecao_usuario AS
SELECT pu.id_usuario, u.nome AS jogador, pu.id_pokemon,
       COALESCE(m.nome, CONCAT('#', pu.id_pokemon)) AS pokemon,
       pu.conquistado_em,
       EXISTS (
           SELECT 1 FROM equipe_usuario e
           WHERE e.id_usuario = pu.id_usuario AND e.id_pokemon = pu.id_pokemon
       ) AS na_equipe
FROM pokemon_usuario pu
JOIN usuario u ON u.id_usuario = pu.id_usuario
LEFT JOIN monstro m ON m.id_monstro = pu.id_pokemon;

CREATE OR REPLACE VIEW vw_pokedex AS
SELECT m.id_monstro AS id_pokemon, m.nome,
       m.vida_base, m.ataque_base, m.defesa_base,
       m.ataque_esp_base, m.defesa_esp_base, m.velocidade_base,
       (SELECT GROUP_CONCAT(t.chave ORDER BY tm.ordem)
          FROM tipo_monstro tm JOIN tipo t ON t.id_tipo = tm.id_tipo
         WHERE tm.id_monstro = m.id_monstro) AS tipos,
       e.id_evolucao, ev.nome AS evolucao_nome
FROM monstro m
LEFT JOIN evolucao_monstro e ON e.id_monstro = m.id_monstro
LEFT JOIN monstro ev ON ev.id_monstro = e.id_evolucao
WHERE m.is_ativo = TRUE;

CREATE OR REPLACE VIEW vw_vendas_loja AS
SELECT i.chave, i.nome AS item,
       COUNT(l.id_log_loja) AS compras,
       COALESCE(SUM(l.quantidade), 0) AS unidades,
       COALESCE(SUM(l.total), 0) AS arrecadado
FROM item_loja i
LEFT JOIN log_loja l ON l.id_item_loja = i.id_item_loja AND l.estornada = FALSE
GROUP BY i.id_item_loja, i.chave, i.nome;
