-- BANCO DO JOGO (tudo em um arquivo so)
--
-- Junta num lugar so:
--   * a modelagem do banco (tipos, monstros, ataques, efeitos, batalha, log...), que veio do schema.sql do Guilherme
--   * o que o jogo usa: pokedolares, itens, inventario, equipe, colecao de pokemons e time do robo
--   (antes era schema.sql + inventario.sql + loja.sql + equipe.sql)
--
-- Como rodar: phpMyAdmin > aba SQL > colar tudo > Executar. Nao precisa criar o banco antes.
-- Serve para banco novo (vazio) e para banco que ja existe.
-- Pode rodar quantas vezes quiser: nao apaga jogador, item, pokedolar, equipe nem pokemon.
-- (so refaz as tabelas antigas da modelagem que eram "casca", so com os ids e sem nenhum dado)
-- Usa "ADD COLUMN IF NOT EXISTS", que o MariaDB do XAMPP aceita.
--
-- Mudancas em relacao ao schema.sql original:
--   * faltavam virgulas/sobravam virgulas em eficaz_tipo, fraqueza_tipo, imune_tipo e monstro_batalha_usuario (dava erro de sintaxe)
--   * ataque.id_categoria_dano era VARCHAR e a chave dele e INT
--   * imagem_monstro nao tinha chave estrangeira para monstro; monstro_batalha_usuario nao tinha chave primaria
--   * usuario: o dinheiro se chama "pokedolares" (e o nome que o jogo usa)
--   * item_loja e item_usuario: versao do jogo (com chave, descricao, categoria, cura, quantidade...)
--   * batalha: id_usuario_2 pode ser vazio (contra o robo nao existe segundo usuario) e ganhou
--     status, recompensa, iniciada_em e finalizada_em (o servidor abre a batalha e paga a recompensa uma vez)

CREATE DATABASE IF NOT EXISTS monstroguelselnius DEFAULT CHARACTER SET utf8mb4;
USE monstroguelselnius;

SET NAMES utf8mb4;   -- para os acentos ficarem certos


-- ======================================================================
-- 1. LIMPEZA: tabelas antigas que eram so "casca" (so os ids, nunca tiveram dado)
--    Sao refeitas na versao completa logo abaixo. Se ja estiverem na versao nova, nada e apagado.
-- ======================================================================

-- modelagem: a casca antiga de tipo/monstro/ataque nao tinha a coluna "nome"
SET @modelo_antigo = (
    EXISTS (SELECT 1 FROM information_schema.tables  WHERE table_schema = DATABASE() AND table_name = 'tipo')
    AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tipo' AND column_name = 'nome')
) OR (
    EXISTS (SELECT 1 FROM information_schema.tables  WHERE table_schema = DATABASE() AND table_name = 'monstro')
    AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'monstro' AND column_name = 'nome')
) OR (
    EXISTS (SELECT 1 FROM information_schema.tables  WHERE table_schema = DATABASE() AND table_name = 'ataque')
    AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'ataque' AND column_name = 'nome')
);

-- itens: a casca antiga de item_loja so tinha o id (sem a coluna "chave")
SET @itens_antigos = (
    EXISTS (SELECT 1 FROM information_schema.tables  WHERE table_schema = DATABASE() AND table_name = 'item_loja')
    AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'item_loja' AND column_name = 'chave')
);

SET FOREIGN_KEY_CHECKS = 0;
SET @comando = IF(@modelo_antigo,
    'DROP TABLE IF EXISTS `log`, monstro_batalha, monstro_batalha_usuario, ataque_monstro, ataque_possivel_monstro, imagem_monstro, tipo_monstro, eficaz_tipo, fraqueza_tipo, imune_tipo, ataque, monstro, tipo',
    'DO 0');
PREPARE passo FROM @comando; EXECUTE passo; DEALLOCATE PREPARE passo;
SET @comando = IF(@itens_antigos, 'DROP TABLE IF EXISTS item_usuario, item_loja', 'DO 0');
PREPARE passo FROM @comando; EXECUTE passo; DEALLOCATE PREPARE passo;
SET FOREIGN_KEY_CHECKS = 1;


-- ======================================================================
-- 2. JOGADORES E BATALHAS
-- ======================================================================

-- jogadores. O id numera sozinho (o cadastro nao manda id). Dinheiro em pokedolares: ganho nas batalhas, gasto na loja
CREATE TABLE IF NOT EXISTS usuario (
    id_usuario  INT          NOT NULL AUTO_INCREMENT,
    nome        VARCHAR(255) NOT NULL,
    senha       VARCHAR(255) NOT NULL,
    pokedolares INT          NOT NULL DEFAULT 0,
    is_bot      BOOLEAN      NOT NULL DEFAULT FALSE,
    is_ativo    BOOLEAN      NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- bancos que ja existiam: acrescenta o que falta (nao mexe nos dados)
ALTER TABLE usuario
    ADD COLUMN IF NOT EXISTS pokedolares INT     NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS is_bot      BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS is_ativo    BOOLEAN NOT NULL DEFAULT TRUE;

-- batalhas. id_usuario_2 e o adversario quando e outro jogador; contra o robo fica vazio.
-- status: em_andamento, vitoria, derrota ou fuga. recompensa: pokedolares ganhos.
CREATE TABLE IF NOT EXISTS batalha (
    id_batalha       INT         NOT NULL AUTO_INCREMENT,
    id_usuario       INT         NOT NULL,
    id_usuario_2     INT         NULL,
    turno_atual      INT         NOT NULL DEFAULT 1,
    cooldown_troca_1 INT         NOT NULL DEFAULT 0,
    cooldown_troca_2 INT         NOT NULL DEFAULT 0,
    status           VARCHAR(15) NOT NULL DEFAULT 'em_andamento',
    recompensa       INT         NOT NULL DEFAULT 0,
    iniciada_em      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizada_em    DATETIME    NULL,
    PRIMARY KEY (id_batalha),
    CONSTRAINT fk_batalha_usuario   FOREIGN KEY (id_usuario)   REFERENCES usuario(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_batalha_usuario_2 FOREIGN KEY (id_usuario_2) REFERENCES usuario(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE batalha
    ADD COLUMN IF NOT EXISTS id_usuario_2     INT         NULL,
    ADD COLUMN IF NOT EXISTS turno_atual      INT         NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS cooldown_troca_1 INT         NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS cooldown_troca_2 INT         NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS status           VARCHAR(15) NOT NULL DEFAULT 'em_andamento',
    ADD COLUMN IF NOT EXISTS recompensa       INT         NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS iniciada_em      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS finalizada_em    DATETIME    NULL;

-- bancos antigos tinham esses ids sem numerar sozinho e o adversario obrigatorio
-- (as chaves ficam desligadas so durante a troca, porque outras tabelas apontam para estas)
SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE usuario MODIFY id_usuario   INT NOT NULL AUTO_INCREMENT;
ALTER TABLE batalha MODIFY id_batalha   INT NOT NULL AUTO_INCREMENT;
ALTER TABLE batalha MODIFY id_usuario_2 INT NULL;
SET FOREIGN_KEY_CHECKS = 1;

-- chave estrangeira do adversario (so cria se ainda nao existir)
SET @tem_fk = EXISTS (
    SELECT 1 FROM information_schema.table_constraints
    WHERE table_schema = DATABASE() AND table_name = 'batalha' AND constraint_name = 'fk_batalha_usuario_2'
);
SET @comando = IF(@tem_fk, 'DO 0',
    'ALTER TABLE batalha ADD CONSTRAINT fk_batalha_usuario_2 FOREIGN KEY (id_usuario_2) REFERENCES usuario(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE');
PREPARE passo FROM @comando; EXECUTE passo; DEALLOCATE PREPARE passo;


-- ======================================================================
-- 3. MODELAGEM: tipos, efeitos, monstros e ataques
-- ======================================================================

CREATE TABLE IF NOT EXISTS efeito (
    id_efeito INT          NOT NULL AUTO_INCREMENT,
    nome      VARCHAR(255) NOT NULL,
    is_ativo  BOOLEAN      NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id_efeito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tipo (
    id_tipo INT          NOT NULL AUTO_INCREMENT,
    nome    VARCHAR(255) NOT NULL,
    PRIMARY KEY (id_tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categoria_dano (
    id_categoria_dano INT          NOT NULL AUTO_INCREMENT,
    nome              VARCHAR(255) NOT NULL,
    PRIMARY KEY (id_categoria_dano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- contra quem cada tipo e eficaz / fraco / imune
CREATE TABLE IF NOT EXISTS eficaz_tipo (
    id_tipo        INT NOT NULL,
    id_tipo_eficaz INT NOT NULL,
    PRIMARY KEY (id_tipo, id_tipo_eficaz),
    FOREIGN KEY (id_tipo)        REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo_eficaz) REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fraqueza_tipo (
    id_tipo          INT NOT NULL,
    id_tipo_fraqueza INT NOT NULL,
    PRIMARY KEY (id_tipo, id_tipo_fraqueza),
    FOREIGN KEY (id_tipo)          REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo_fraqueza) REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS imune_tipo (
    id_tipo       INT NOT NULL,
    id_tipo_imune INT NOT NULL,
    PRIMARY KEY (id_tipo, id_tipo_imune),
    FOREIGN KEY (id_tipo)       REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo_imune) REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS monstro (
    id_monstro      INT          NOT NULL AUTO_INCREMENT,
    nome            VARCHAR(255) NOT NULL,
    vida_base       INT          NOT NULL,
    ataque_base     INT          NOT NULL,
    defesa_base     INT          NOT NULL,
    ataque_esp_base INT          NOT NULL,
    defesa_esp_base INT          NOT NULL,
    velocidade_base INT          NOT NULL,
    is_ativo        BOOLEAN      NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id_monstro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tipo_monstro (
    id_monstro INT NOT NULL,
    id_tipo    INT NOT NULL,
    PRIMARY KEY (id_monstro, id_tipo),
    FOREIGN KEY (id_monstro) REFERENCES monstro(id_monstro) ON DELETE CASCADE  ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo)    REFERENCES tipo(id_tipo)       ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS imagem_monstro (
    id_imagem_monstro INT          NOT NULL AUTO_INCREMENT,
    id_monstro        INT          NOT NULL,
    caminho           VARCHAR(255) NOT NULL,
    PRIMARY KEY (id_imagem_monstro),
    FOREIGN KEY (id_monstro) REFERENCES monstro(id_monstro) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ataque (
    id_ataque         INT          NOT NULL AUTO_INCREMENT,
    nome              VARCHAR(255) NULL,
    poder             INT          NULL,
    precisao          FLOAT        NULL,
    chance_efeito     FLOAT        NULL,
    is_ativo          BOOLEAN      NOT NULL DEFAULT TRUE,
    id_categoria_dano INT          NOT NULL,
    id_tipo           INT          NOT NULL,
    id_efeito         INT          NULL,
    PRIMARY KEY (id_ataque),
    FOREIGN KEY (id_categoria_dano) REFERENCES categoria_dano(id_categoria_dano) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo)           REFERENCES tipo(id_tipo)                     ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_efeito)         REFERENCES efeito(id_efeito)                 ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- quais ataques cada monstro pode aprender
CREATE TABLE IF NOT EXISTS ataque_possivel_monstro (
    id_ataque  INT NOT NULL,
    id_monstro INT NOT NULL,
    PRIMARY KEY (id_ataque, id_monstro),
    FOREIGN KEY (id_ataque)  REFERENCES ataque(id_ataque)   ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_monstro) REFERENCES monstro(id_monstro) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ======================================================================
-- 4. DENTRO DA BATALHA: log e monstros em campo
-- ======================================================================

CREATE TABLE IF NOT EXISTS `log` (
    id_log     INT NOT NULL AUTO_INCREMENT,
    id_batalha INT NOT NULL,
    PRIMARY KEY (id_log),
    FOREIGN KEY (id_batalha) REFERENCES batalha(id_batalha) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- historico de todas as batalhas. Quem escreve aqui sao os triggers da secao 7 (ninguem insere na mao):
-- um registro quando a batalha comeca e outro quando ela termina (vitoria, derrota ou fuga)
ALTER TABLE `log`
    ADD COLUMN IF NOT EXISTS id_usuario INT          NULL,
    ADD COLUMN IF NOT EXISTS evento     VARCHAR(15)  NOT NULL DEFAULT 'iniciada',
    ADD COLUMN IF NOT EXISTS status     VARCHAR(15)  NULL,
    ADD COLUMN IF NOT EXISTS recompensa INT          NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS descricao  VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- historico dos jogadores (cadastro, troca de nome/senha, exclusao).
-- Sem chave estrangeira de proposito: o registro tem que sobreviver quando a conta for apagada.
CREATE TABLE IF NOT EXISTS log_usuario (
    id_log_usuario INT          NOT NULL AUTO_INCREMENT,
    id_usuario     INT          NOT NULL,
    nome           VARCHAR(255) NOT NULL,
    evento         VARCHAR(20)  NOT NULL,   -- cadastro, troca_nome, troca_senha, exclusao
    criado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_log_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- historico da loja: toda compra (quem, o que, quanto, por quanto). O LojaDAO grava junto com a compra.
-- Tambem sem chave estrangeira, pelo mesmo motivo.
CREATE TABLE IF NOT EXISTS log_loja (
    id_log_loja    INT        NOT NULL AUTO_INCREMENT,
    id_usuario     INT        NOT NULL,
    id_item_loja   INT        NOT NULL,
    quantidade     INT        NOT NULL,
    preco_unitario INT        NOT NULL,
    total          INT        NOT NULL,
    estornada      TINYINT(1) NOT NULL DEFAULT 0,
    criado_em      DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_log_loja)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS monstro_batalha_usuario (
    id_monstro              INT     NOT NULL,
    id_batalha              INT     NOT NULL,
    id_usuario              INT     NOT NULL,
    is_ativo                BOOLEAN NOT NULL DEFAULT FALSE,
    vida_atual              INT     NOT NULL,
    id_efeito_atual         INT     NULL,
    turnos_restantes_efeito INT     NULL,
    id_ataque_1             INT     NOT NULL,
    id_ataque_2             INT     NULL,
    id_ataque_3             INT     NULL,
    id_ataque_4             INT     NULL,
    PRIMARY KEY (id_batalha, id_usuario, id_monstro),
    FOREIGN KEY (id_monstro)       REFERENCES monstro(id_monstro)   ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_batalha)       REFERENCES batalha(id_batalha)   ON DELETE CASCADE  ON UPDATE CASCADE,
    FOREIGN KEY (id_usuario)       REFERENCES usuario(id_usuario)   ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_efeito_atual)  REFERENCES efeito(id_efeito)     ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_ataque_1)      REFERENCES ataque(id_ataque)     ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_ataque_2)      REFERENCES ataque(id_ataque)     ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_ataque_3)      REFERENCES ataque(id_ataque)     ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_ataque_4)      REFERENCES ataque(id_ataque)     ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ======================================================================
-- 5. ITENS, INVENTARIO E LOJA
-- ======================================================================

-- catalogo de itens: serve para o inventario, para a batalha e para a loja
CREATE TABLE IF NOT EXISTS item_loja (
    id_item_loja INT NOT NULL AUTO_INCREMENT,
    chave        VARCHAR(30)  NOT NULL,            -- nome interno usado pelo jogo (ex: potion)
    nome         VARCHAR(60)  NOT NULL,
    descricao    VARCHAR(255) NOT NULL,
    categoria    VARCHAR(20)  NOT NULL,            -- pocao_hp (inclui reviver) ou pocao_condicao
    preco        INT          NOT NULL,            -- em pokedolares
    imagem       VARCHAR(100) NOT NULL,            -- arquivo dentro de public/img
    cura_hp      INT          NOT NULL DEFAULT 0,  -- 0 = nao cura HP, -1 = cura todo o HP
    cura_status  VARCHAR(100) NULL,                -- 'todos' ou lista separada por virgula (poison,burn...)
    reviver      TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 = revive pokemon desmaiado com metade do HP
    kit_inicial  INT          NOT NULL DEFAULT 0,  -- quantos o jogador ganha ao criar a conta
    PRIMARY KEY (id_item_loja),
    UNIQUE KEY uq_item_chave (chave),
    CHECK (preco >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- o que cada jogador tem
CREATE TABLE IF NOT EXISTS item_usuario (
    id_usuario   INT NOT NULL,
    id_item_loja INT NOT NULL,
    quantidade   INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id_usuario, id_item_loja),
    FOREIGN KEY (id_usuario)   REFERENCES usuario(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_item_loja) REFERENCES item_loja(id_item_loja) ON DELETE CASCADE ON UPDATE CASCADE,
    CHECK (quantidade >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ======================================================================
-- 6. EQUIPE, COLECAO DE POKEMONS E TIME DO ROBO
--    pokemon_basico : os pokemons de nivel basico (de onde saem os 3 primeiros e os do robo)
--    equipe_usuario : os 3 pokemons que o jogador leva para a batalha
--    pokemon_usuario: a colecao (os pokemons que o jogador tem; cresce ao vencer batalhas)
--    batalha_robo   : o time do robo de cada batalha (o servidor sorteia; quem vence leva os 3)
-- ======================================================================

-- id_pokemon e o numero do pokemon na PokeAPI. O jogo busca imagem, tipos e golpes la.
CREATE TABLE IF NOT EXISTS pokemon_basico (
    id_pokemon INT         NOT NULL,
    nome       VARCHAR(30) NOT NULL,
    PRIMARY KEY (id_pokemon)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- a equipe de cada jogador: 3 posicoes, sem repetir pokemon.
-- id_pokemon nao tem chave estrangeira para pokemon_basico de proposito:
-- quando o pokemon evoluir, o id da evolucao vai ficar aqui.
CREATE TABLE IF NOT EXISTS equipe_usuario (
    id_usuario INT     NOT NULL,
    posicao    TINYINT NOT NULL,   -- 1, 2 ou 3
    id_pokemon INT     NOT NULL,
    PRIMARY KEY (id_usuario, posicao),
    UNIQUE KEY uq_equipe_pokemon (id_usuario, id_pokemon),
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
    CHECK (posicao BETWEEN 1 AND 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- colecao: os pokemons que o jogador tem. A equipe e escolhida entre eles.
CREATE TABLE IF NOT EXISTS pokemon_usuario (
    id_usuario     INT      NOT NULL,
    id_pokemon     INT      NOT NULL,
    conquistado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario, id_pokemon),
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- quem ja tinha equipe antes da colecao existir: os pokemons da equipe entram na colecao
INSERT IGNORE INTO pokemon_usuario (id_usuario, id_pokemon)
SELECT id_usuario, id_pokemon FROM equipe_usuario;

-- time do robo de cada batalha. O servidor sorteia ao abrir a batalha e entrega ao jogador se ele vencer.
-- (o navegador nao escolhe, senao daria para pedir qualquer pokemon)
CREATE TABLE IF NOT EXISTS batalha_robo (
    id_batalha INT     NOT NULL,
    posicao    TINYINT NOT NULL,   -- 1, 2 ou 3
    id_pokemon INT     NOT NULL,
    PRIMARY KEY (id_batalha, posicao),
    FOREIGN KEY (id_batalha) REFERENCES batalha(id_batalha) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- limpa times de robo de batalhas que nao existem mais
DELETE FROM batalha_robo WHERE id_batalha NOT IN (SELECT id_batalha FROM batalha);

-- ======================================================================
-- 7. TRIGGERS, VIEWS E PROCEDURE
--    (os triggers e a procedure usam DELIMITER; no phpMyAdmin pode colar do jeito que esta)
-- ======================================================================

-- registros das batalhas que ja existiam antes dos triggers (so entra o que ainda nao tem registro)
INSERT INTO `log` (id_batalha, id_usuario, evento, status, recompensa, descricao, criado_em)
SELECT b.id_batalha, b.id_usuario, 'iniciada', 'em_andamento', 0, 'Batalha iniciada', b.iniciada_em
FROM batalha b
WHERE NOT EXISTS (SELECT 1 FROM `log` l WHERE l.id_batalha = b.id_batalha AND l.evento = 'iniciada');

INSERT INTO `log` (id_batalha, id_usuario, evento, status, recompensa, descricao, criado_em)
SELECT b.id_batalha, b.id_usuario, 'finalizada', b.status, b.recompensa,
       CONCAT('Batalha terminou em ', b.status, ' e rendeu ', b.recompensa, ' pokedolares'),
       COALESCE(b.finalizada_em, b.iniciada_em)
FROM batalha b
WHERE b.status <> 'em_andamento'
  AND NOT EXISTS (SELECT 1 FROM `log` l WHERE l.id_batalha = b.id_batalha AND l.evento = 'finalizada');

INSERT INTO log_usuario (id_usuario, nome, evento)
SELECT u.id_usuario, u.nome, 'cadastro'
FROM usuario u
WHERE NOT EXISTS (SELECT 1 FROM log_usuario l WHERE l.id_usuario = u.id_usuario AND l.evento = 'cadastro');

-- ---------- TRIGGERS ----------
DROP TRIGGER IF EXISTS trg_batalha_iniciada;
DROP TRIGGER IF EXISTS trg_batalha_finalizada;
DROP TRIGGER IF EXISTS trg_usuario_cadastro;
DROP TRIGGER IF EXISTS trg_usuario_antes_de_alterar;
DROP TRIGGER IF EXISTS trg_usuario_depois_de_alterar;
DROP TRIGGER IF EXISTS trg_usuario_exclusao;

DELIMITER $$

-- toda batalha nova entra no log
CREATE TRIGGER trg_batalha_iniciada AFTER INSERT ON batalha
FOR EACH ROW
BEGIN
    INSERT INTO `log` (id_batalha, id_usuario, evento, status, recompensa, descricao)
    VALUES (NEW.id_batalha, NEW.id_usuario, 'iniciada', 'em_andamento', 0, 'Batalha iniciada');
END$$

-- quando a batalha sai de "em_andamento" (vitoria, derrota ou fuga), o resultado e a recompensa entram no log
CREATE TRIGGER trg_batalha_finalizada AFTER UPDATE ON batalha
FOR EACH ROW
BEGIN
    IF OLD.status = 'em_andamento' AND NEW.status <> 'em_andamento' THEN
        INSERT INTO `log` (id_batalha, id_usuario, evento, status, recompensa, descricao)
        VALUES (NEW.id_batalha, NEW.id_usuario, 'finalizada', NEW.status, NEW.recompensa,
                CONCAT('Batalha terminou em ', NEW.status, ' e rendeu ', NEW.recompensa, ' pokedolares'));
    END IF;
END$$

-- conta nova
CREATE TRIGGER trg_usuario_cadastro AFTER INSERT ON usuario
FOR EACH ROW
BEGIN
    INSERT INTO log_usuario (id_usuario, nome, evento) VALUES (NEW.id_usuario, NEW.nome, 'cadastro');
END$$

-- trava de seguranca: o saldo de pokedolares nunca pode ficar negativo
CREATE TRIGGER trg_usuario_antes_de_alterar BEFORE UPDATE ON usuario
FOR EACH ROW
BEGIN
    IF NEW.pokedolares < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saldo de pokedolares nao pode ficar negativo';
    END IF;
END$$

-- troca de nome e de senha ficam registradas (a senha em si nao vai para o log)
CREATE TRIGGER trg_usuario_depois_de_alterar AFTER UPDATE ON usuario
FOR EACH ROW
BEGIN
    IF OLD.nome <> NEW.nome THEN
        INSERT INTO log_usuario (id_usuario, nome, evento) VALUES (NEW.id_usuario, NEW.nome, 'troca_nome');
    END IF;
    IF OLD.senha <> NEW.senha THEN
        INSERT INTO log_usuario (id_usuario, nome, evento) VALUES (NEW.id_usuario, NEW.nome, 'troca_senha');
    END IF;
END$$

-- conta apagada
CREATE TRIGGER trg_usuario_exclusao BEFORE DELETE ON usuario
FOR EACH ROW
BEGIN
    INSERT INTO log_usuario (id_usuario, nome, evento) VALUES (OLD.id_usuario, OLD.nome, 'exclusao');
END$$

DELIMITER ;

-- ---------- VIEWS (consultas prontas; o jogo usa na tela de perfil) ----------

-- todas as batalhas, com o nome do jogador e do adversario ("Robo" quando nao tem segundo jogador)
CREATE OR REPLACE VIEW vw_historico_batalhas AS
SELECT b.id_batalha,
       b.id_usuario,
       u.nome                                   AS jogador,
       COALESCE(u2.nome, 'Robô')                AS adversario,
       b.status,
       b.recompensa,
       b.iniciada_em,
       b.finalizada_em,
       TIMESTAMPDIFF(SECOND, b.iniciada_em, b.finalizada_em) AS duracao_segundos
FROM batalha b
JOIN usuario u        ON u.id_usuario  = b.id_usuario
LEFT JOIN usuario u2  ON u2.id_usuario = b.id_usuario_2;

-- placar de cada jogador (so batalhas terminadas): agrupa e conta por resultado
CREATE OR REPLACE VIEW vw_ranking_jogadores AS
SELECT u.id_usuario,
       u.nome,
       u.pokedolares,
       COUNT(b.id_batalha)                     AS batalhas,
       COALESCE(SUM(b.status = 'vitoria'), 0)  AS vitorias,
       COALESCE(SUM(b.status = 'derrota'), 0)  AS derrotas,
       COALESCE(SUM(b.status = 'fuga'), 0)     AS fugas,
       COALESCE(SUM(b.recompensa), 0)          AS total_ganho
FROM usuario u
LEFT JOIN batalha b ON b.id_usuario = u.id_usuario AND b.status <> 'em_andamento'
WHERE u.is_bot = 0
GROUP BY u.id_usuario, u.nome, u.pokedolares;

-- o que cada jogador tem no inventario (so o que tem pelo menos 1)
CREATE OR REPLACE VIEW vw_inventario_usuario AS
SELECT iu.id_usuario,
       u.nome AS jogador,
       i.chave,
       i.nome AS item,
       i.categoria,
       iu.quantidade,
       iu.quantidade * i.preco AS valor_total
FROM item_usuario iu
JOIN usuario   u ON u.id_usuario   = iu.id_usuario
JOIN item_loja i ON i.id_item_loja = iu.id_item_loja
WHERE iu.quantidade > 0;

-- colecao de pokemons de cada jogador, dizendo quais estao na equipe
CREATE OR REPLACE VIEW vw_colecao_usuario AS
SELECT pu.id_usuario,
       u.nome AS jogador,
       pu.id_pokemon,
       COALESCE(pb.nome, CONCAT('#', pu.id_pokemon)) AS pokemon,
       pu.conquistado_em,
       EXISTS (SELECT 1 FROM equipe_usuario e
               WHERE e.id_usuario = pu.id_usuario AND e.id_pokemon = pu.id_pokemon) AS na_equipe
FROM pokemon_usuario pu
JOIN usuario u ON u.id_usuario = pu.id_usuario
LEFT JOIN pokemon_basico pb ON pb.id_pokemon = pu.id_pokemon;

-- quanto cada item vendeu na loja (compras estornadas nao contam)
CREATE OR REPLACE VIEW vw_vendas_loja AS
SELECT i.chave,
       i.nome AS item,
       COUNT(l.id_log_loja)             AS compras,
       COALESCE(SUM(l.quantidade), 0)   AS unidades,
       COALESCE(SUM(l.total), 0)        AS arrecadado
FROM item_loja i
LEFT JOIN log_loja l ON l.id_item_loja = i.id_item_loja AND l.estornada = 0
GROUP BY i.id_item_loja, i.chave, i.nome;

-- ---------- PROCEDURE (controle de transacao: DTL) ----------
-- Desfaz uma compra da loja: devolve os pokedolares e tira os itens do inventario.
-- Mostra START TRANSACTION, SAVEPOINT, ROLLBACK TO SAVEPOINT, COMMIT e ROLLBACK.
-- Uso: CALL sp_estornar_compra(<id_log_loja>);
DROP PROCEDURE IF EXISTS sp_estornar_compra;

DELIMITER $$

CREATE PROCEDURE sp_estornar_compra(IN p_id_log_loja INT)
BEGIN
    DECLARE v_usuario INT DEFAULT NULL;
    DECLARE v_item    INT DEFAULT NULL;
    DECLARE v_qtd     INT DEFAULT 0;
    DECLARE v_total   INT DEFAULT 0;
    DECLARE v_tem     INT DEFAULT NULL;

    -- qualquer erro desfaz tudo e repassa o erro
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT id_usuario, id_item_loja, quantidade, total
      INTO v_usuario, v_item, v_qtd, v_total
      FROM log_loja
     WHERE id_log_loja = p_id_log_loja AND estornada = 0
       FOR UPDATE;

    IF v_usuario IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Compra nao encontrada ou ja estornada';
    END IF;

    SAVEPOINT antes_do_estorno;

    SELECT quantidade INTO v_tem
      FROM item_usuario
     WHERE id_usuario = v_usuario AND id_item_loja = v_item
       FOR UPDATE;

    -- se o jogador ja gastou os itens, nao da para devolver o dinheiro
    IF v_tem IS NULL OR v_tem < v_qtd THEN
        ROLLBACK TO SAVEPOINT antes_do_estorno;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O jogador ja usou os itens dessa compra';
    END IF;

    UPDATE item_usuario SET quantidade = quantidade - v_qtd
     WHERE id_usuario = v_usuario AND id_item_loja = v_item;
    UPDATE usuario SET pokedolares = pokedolares + v_total WHERE id_usuario = v_usuario;
    UPDATE log_loja SET estornada = 1 WHERE id_log_loja = p_id_log_loja;

    COMMIT;
END$$

DELIMITER ;


-- ======================================================================
-- 8. DADOS: itens da loja, pokemons basicos e kit inicial
-- ======================================================================

-- itens do jogo (precos sao ponto de partida, podem ser ajustados aqui e o arquivo rodado de novo).
-- KIT INICIAL (ultima coluna): jogador novo comeca so com 3 pocoes basicas (Pocao) e mais nada.
INSERT INTO item_loja (chave, nome, descricao, categoria, preco, imagem, cura_hp, cura_status, reviver, kit_inicial) VALUES
('potion',        'Poção',          'Recupera 20 de HP de um pokémon.',                       'pocao_hp',      50,  'potion.png',              20,   NULL,       0, 3),
('super-potion',  'Super Poção',    'Recupera 50 de HP de um pokémon.',                       'pocao_hp',      120, 'super potion.png',        50,   NULL,       0, 0),
('hyper-potion',  'Hiper Poção',    'Recupera 200 de HP de um pokémon.',                      'pocao_hp',      250, 'hiper potion.png',        200,  NULL,       0, 0),
('max-potion',    'Poção Máxima',   'Recupera todo o HP de um pokémon.',                      'pocao_hp',      400, 'max potion.png',          -1,   NULL,       0, 0),
('full-restore',  'Restaurar Tudo', 'Recupera todo o HP e cura qualquer condição.',           'pocao_hp',      600, 'full restore potion.png', -1,   'todos',    0, 0),
('revive',        'Reviver',        'Traz um pokémon desmaiado de volta com metade do HP.',   'pocao_hp',      350, 'reviver.webp',            0,    NULL,       1, 0),
('antidote',      'Antídoto',       'Cura um pokémon envenenado.',                            'pocao_condicao', 60, 'antidote.png',            0,    'poison',   0, 0),
('burn-heal',     'Anti-Queimadura','Cura um pokémon queimado.',                              'pocao_condicao', 60, 'burn heal.png',           0,    'burn',     0, 0),
('paralyze-heal', 'Anti-Paralisia', 'Cura um pokémon paralisado.',                            'pocao_condicao', 60, 'paralyse heal.png',       0,    'paralysis',0, 0),
('awakening',     'Despertar',      'Acorda um pokémon que está dormindo.',                   'pocao_condicao', 60, 'awakening.png',           0,    'sleep',    0, 0),
('ice-heal',      'Anti-Gelo',      'Descongela um pokémon congelado.',                       'pocao_condicao', 60, 'ice heal.png',            0,    'freeze',   0, 0),
('full-heal',     'Cura Total',     'Cura qualquer condição de um pokémon.',                  'pocao_condicao', 150,'full heal.webp',          0,    'todos',    0, 0)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome), descricao = VALUES(descricao), categoria = VALUES(categoria), preco = VALUES(preco),
    imagem = VALUES(imagem), cura_hp = VALUES(cura_hp), cura_status = VALUES(cura_status),
    reviver = VALUES(reviver), kit_inicial = VALUES(kit_inicial);

INSERT INTO pokemon_basico (id_pokemon, nome) VALUES
    (1,   'Bulbasaur'),
    (4,   'Charmander'),
    (7,   'Squirtle'),
    (16,  'Pidgey'),
    (19,  'Rattata'),
    (23,  'Ekans'),
    (25,  'Pikachu'),
    (27,  'Sandshrew'),
    (35,  'Clefairy'),
    (37,  'Vulpix'),
    (39,  'Jigglypuff'),
    (43,  'Oddish'),
    (52,  'Meowth'),
    (54,  'Psyduck'),
    (56,  'Mankey'),
    (58,  'Growlithe'),
    (60,  'Poliwag'),
    (63,  'Abra'),
    (66,  'Machop'),
    (69,  'Bellsprout'),
    (72,  'Tentacool'),
    (74,  'Geodude'),
    (77,  'Ponyta'),
    (79,  'Slowpoke'),
    (81,  'Magnemite'),
    (92,  'Gastly'),
    (104, 'Cubone'),
    (116, 'Horsea'),
    (133, 'Eevee'),
    (147, 'Dratini')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- jogadores que ja existem recebem o kit inicial (contas novas recebem pelo cadastro).
-- So entra o que o jogador ainda nao tem, entao quem ja usou ou comprou itens nao e afetado.
INSERT IGNORE INTO item_usuario (id_usuario, id_item_loja, quantidade)
SELECT u.id_usuario, i.id_item_loja, i.kit_inicial
FROM usuario u CROSS JOIN item_loja i
WHERE i.kit_inicial > 0;

-- OPCIONAL: deixar TODAS as contas que ja existem so com 3 pocoes. Para usar, tire o "-- " do comeco das 3 linhas.
-- CUIDADO: apaga todos os itens que essas contas tem hoje, inclusive os comprados na loja.
-- DELETE FROM item_usuario;
-- INSERT INTO item_usuario (id_usuario, id_item_loja, quantidade)
-- SELECT u.id_usuario, i.id_item_loja, 3 FROM usuario u JOIN item_loja i ON i.chave = 'potion';


