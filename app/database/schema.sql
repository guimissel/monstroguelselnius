-- Schema completo do jogo para banco novo.

-- Cria o banco com suporte a caracteres acentuados e seleciona-o para os comandos seguintes.
CREATE DATABASE IF NOT EXISTS monstroguelselnius DEFAULT CHARACTER SET utf8mb4;
USE monstroguelselnius;

-- Mantem a conexao SQL em UTF-8 para nomes e textos acentuados.
SET NAMES utf8mb4;
-- 1. JOGADORES E BATALHAS

-- Guarda contas de jogadores e bots, saldo e estado de ativacao.
CREATE TABLE IF NOT EXISTS usuario (
    id_usuario  INT          NOT NULL AUTO_INCREMENT,
    nome        VARCHAR(255) NOT NULL,
    senha       VARCHAR(255) NOT NULL,
    pokedolares INT          NOT NULL DEFAULT 0,
    is_bot      BOOLEAN      NOT NULL DEFAULT FALSE,
    is_ativo    BOOLEAN      NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Registra partidas, participantes, turno, cooldowns, resultado e recompensa.
-- id_usuario_2 fica NULL quando a partida e contra o robo.
CREATE TABLE IF NOT EXISTS batalha (
    id_batalha       INT         NOT NULL AUTO_INCREMENT,
    id_usuario       INT         NOT NULL,
    id_usuario_2     INT         NULL,
    turno_atual      INT         NOT NULL DEFAULT 1,
    cooldown_troca_1 INT         NOT NULL DEFAULT 0,
    cooldown_troca_2 INT         NOT NULL DEFAULT 0,
    is_boss          BOOLEAN     NOT NULL DEFAULT FALSE,
    status           VARCHAR(15) NOT NULL DEFAULT 'em_andamento',
    recompensa       INT         NOT NULL DEFAULT 0,
    iniciada_em      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizada_em    DATETIME    NULL,
    PRIMARY KEY (id_batalha),
    CONSTRAINT fk_batalha_usuario   FOREIGN KEY (id_usuario)   REFERENCES usuario(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_batalha_usuario_2 FOREIGN KEY (id_usuario_2) REFERENCES usuario(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. CATALOGO DE TIPOS, EFEITOS, MONSTROS E ATAQUES

-- Efeitos que podem ser aplicados durante uma batalha.
CREATE TABLE IF NOT EXISTS efeito (
    id_efeito INT          NOT NULL AUTO_INCREMENT,
    nome      VARCHAR(255) NOT NULL,
    is_ativo  BOOLEAN      NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id_efeito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tipos elementais; chave guarda o identificador em ingles usado pelo jogo.
CREATE TABLE IF NOT EXISTS tipo (
    id_tipo INT          NOT NULL AUTO_INCREMENT,
    nome    VARCHAR(255) NOT NULL,
    chave   VARCHAR(20)  NULL,   -- nome em ingles que a PokeAPI e o jogo usam (fire, water...)
    PRIMARY KEY (id_tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categorias de dano dos ataques (por exemplo, fisico, especial ou status).
CREATE TABLE IF NOT EXISTS categoria_dano (
    id_categoria_dano INT          NOT NULL AUTO_INCREMENT,
    nome              VARCHAR(255) NOT NULL,
    PRIMARY KEY (id_categoria_dano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Relaciona tipos contra os quais cada tipo causa dano dobrado.
CREATE TABLE IF NOT EXISTS eficaz_tipo (
    id_tipo        INT NOT NULL,
    id_tipo_eficaz INT NOT NULL,
    PRIMARY KEY (id_tipo, id_tipo_eficaz),
    FOREIGN KEY (id_tipo)        REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo_eficaz) REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Relaciona tipos que causam dano dobrado a cada tipo defensor.
CREATE TABLE IF NOT EXISTS fraqueza_tipo (
    id_tipo          INT NOT NULL,
    id_tipo_fraqueza INT NOT NULL,
    PRIMARY KEY (id_tipo, id_tipo_fraqueza),
    FOREIGN KEY (id_tipo)          REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo_fraqueza) REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Relaciona tipos que causam metade do dano a cada tipo defensor.
CREATE TABLE IF NOT EXISTS resiste_tipo (
    id_tipo         INT NOT NULL,
    id_tipo_resiste INT NOT NULL,
    PRIMARY KEY (id_tipo, id_tipo_resiste),
    FOREIGN KEY (id_tipo)         REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo_resiste) REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Relaciona tipos que nao causam dano a cada tipo defensor.
CREATE TABLE IF NOT EXISTS imune_tipo (
    id_tipo       INT NOT NULL,
    id_tipo_imune INT NOT NULL,
    PRIMARY KEY (id_tipo, id_tipo_imune),
    FOREIGN KEY (id_tipo)       REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo_imune) REFERENCES tipo(id_tipo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Guarda os atributos base dos monstros usados no jogo.
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

-- Associa um ou dois tipos a cada monstro; ordem 1 indica o tipo principal.
CREATE TABLE IF NOT EXISTS tipo_monstro (
    id_monstro INT     NOT NULL,
    id_tipo    INT     NOT NULL,
    ordem      TINYINT NOT NULL DEFAULT 1,   -- 1 = tipo principal, 2 = secundario
    PRIMARY KEY (id_monstro, id_tipo),
    FOREIGN KEY (id_monstro) REFERENCES monstro(id_monstro) ON DELETE CASCADE  ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo)    REFERENCES tipo(id_tipo)       ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mantem caminhos de imagem opcionais; a interface atualmente busca imagens na PokeAPI.
CREATE TABLE IF NOT EXISTS imagem_monstro (
    id_imagem_monstro INT          NOT NULL AUTO_INCREMENT,
    id_monstro        INT          NOT NULL,
    caminho           VARCHAR(255) NOT NULL,
    PRIMARY KEY (id_imagem_monstro),
    FOREIGN KEY (id_monstro) REFERENCES monstro(id_monstro) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Define a evolucao disponivel para cada monstro.
CREATE TABLE IF NOT EXISTS evolucao_monstro (
    id_monstro  INT NOT NULL,
    id_evolucao INT NOT NULL,
    PRIMARY KEY (id_monstro),
    FOREIGN KEY (id_monstro)  REFERENCES monstro(id_monstro) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_evolucao) REFERENCES monstro(id_monstro) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Catalogo de ataques, seus atributos e referencias a tipo, categoria e efeito.
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

-- Relaciona os ataques que cada monstro pode usar.
CREATE TABLE IF NOT EXISTS ataque_possivel_monstro (
    id_ataque  INT NOT NULL,
    id_monstro INT NOT NULL,
    PRIMARY KEY (id_ataque, id_monstro),
    FOREIGN KEY (id_ataque)  REFERENCES ataque(id_ataque)   ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_monstro) REFERENCES monstro(id_monstro) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 4. ESTADO E HISTORICO DAS BATALHAS

-- Cria a tabela de eventos de batalha; as colunas de historico sao adicionadas abaixo.
CREATE TABLE IF NOT EXISTS `log` (
    id_log     INT         NOT NULL AUTO_INCREMENT,
    id_batalha INT         NOT NULL,
    id_usuario INT         NULL,
    evento     VARCHAR(15) NOT NULL DEFAULT 'iniciada',
    status     VARCHAR(15) NULL,
    recompensa INT         NOT NULL DEFAULT 0,
    descricao  VARCHAR(255) NULL,
    criado_em  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_log),
    FOREIGN KEY (id_batalha) REFERENCES batalha(id_batalha) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Registra eventos da conta sem FK para preservar o historico apos a exclusao do usuario.
CREATE TABLE IF NOT EXISTS log_usuario (
    id_log_usuario INT          NOT NULL AUTO_INCREMENT,
    id_usuario     INT          NOT NULL,
    nome           VARCHAR(255) NOT NULL,
    evento         VARCHAR(20)  NOT NULL,
    criado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_log_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Registra os dados da compra para auditoria, sem FK para preservar o historico.
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

-- Guarda os monstros de cada participante, com vida, efeito e ataques selecionados.
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


-- 5. ITENS, INVENTARIO E LOJA

-- Define itens compraveis e seus efeitos no inventario e na batalha.
CREATE TABLE IF NOT EXISTS item_loja (
    id_item_loja INT NOT NULL AUTO_INCREMENT,
    chave        VARCHAR(30)  NOT NULL,
    nome         VARCHAR(60)  NOT NULL,
    descricao    VARCHAR(255) NOT NULL,
    categoria    VARCHAR(20)  NOT NULL,
    preco        INT          NOT NULL,
    imagem       VARCHAR(100) NOT NULL,
    cura_hp      INT          NOT NULL DEFAULT 0,
    cura_status  VARCHAR(100) NULL,
    reviver      TINYINT(1)   NOT NULL DEFAULT 0,
    kit_inicial  INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (id_item_loja),
    UNIQUE KEY uq_item_chave (chave),
    CHECK (preco >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Relaciona cada jogador aos itens e quantidades que possui.
CREATE TABLE IF NOT EXISTS item_usuario (
    id_usuario   INT NOT NULL,
    id_item_loja INT NOT NULL,
    quantidade   INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id_usuario, id_item_loja),
    FOREIGN KEY (id_usuario)   REFERENCES usuario(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_item_loja) REFERENCES item_loja(id_item_loja) ON DELETE CASCADE ON UPDATE CASCADE,
    CHECK (quantidade >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. EQUIPE, COLECAO E TIME DO ROBO

-- Define os monstros basicos disponiveis para sorteios e equipe inicial.
CREATE TABLE IF NOT EXISTS pokemon_basico (
    id_pokemon INT         NOT NULL,
    nome       VARCHAR(30) NOT NULL,
    PRIMARY KEY (id_pokemon)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Guarda os tres monstros escolhidos por jogador; permite atualizar o ID ao evoluir.
CREATE TABLE IF NOT EXISTS equipe_usuario (
    id_usuario INT     NOT NULL,
    posicao    TINYINT NOT NULL,
    id_pokemon INT     NOT NULL,
    PRIMARY KEY (id_usuario, posicao),
    UNIQUE KEY uq_equipe_pokemon (id_usuario, id_pokemon),
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
    CHECK (posicao BETWEEN 1 AND 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mantem a colecao de monstros adquiridos por cada jogador.
CREATE TABLE IF NOT EXISTS pokemon_usuario (
    id_usuario     INT      NOT NULL,
    id_pokemon     INT      NOT NULL,
    conquistado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario, id_pokemon),
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Persiste o time sorteado no servidor para cada batalha contra o robo.
CREATE TABLE IF NOT EXISTS batalha_robo (
    id_batalha INT     NOT NULL,
    posicao    TINYINT NOT NULL,
    id_pokemon INT     NOT NULL,
    PRIMARY KEY (id_batalha, posicao),
    FOREIGN KEY (id_batalha) REFERENCES batalha(id_batalha) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ======================================================================
-- 7. TRIGGERS E VIEWS
-- Os triggers abaixo usam DELIMITER para agrupar seus comandos.
-- ======================================================================

-- Remove as definicoes anteriores para recriar triggers sem duplicidade.
DROP TRIGGER IF EXISTS trg_batalha_iniciada;
DROP TRIGGER IF EXISTS trg_batalha_finalizada;
DROP TRIGGER IF EXISTS trg_usuario_cadastro;
DROP TRIGGER IF EXISTS trg_usuario_antes_de_alterar;
DROP TRIGGER IF EXISTS trg_usuario_depois_de_alterar;
DROP TRIGGER IF EXISTS trg_usuario_exclusao;

DELIMITER $$

-- Registra o evento de inicio sempre que uma batalha e criada.
CREATE TRIGGER trg_batalha_iniciada AFTER INSERT ON batalha
FOR EACH ROW
BEGIN
    INSERT INTO `log` (id_batalha, id_usuario, evento, status, recompensa, descricao)
    VALUES (NEW.id_batalha, NEW.id_usuario, 'iniciada', 'em_andamento', 0, 'Batalha iniciada');
END$$

-- Registra o resultado uma unica vez quando a batalha deixa o estado em andamento.
CREATE TRIGGER trg_batalha_finalizada AFTER UPDATE ON batalha
FOR EACH ROW
BEGIN
    IF OLD.status = 'em_andamento' AND NEW.status <> 'em_andamento' THEN
        INSERT INTO `log` (id_batalha, id_usuario, evento, status, recompensa, descricao)
        VALUES (NEW.id_batalha, NEW.id_usuario, 'finalizada', NEW.status, NEW.recompensa,
                CONCAT('Batalha terminou em ', NEW.status, ' e rendeu ', NEW.recompensa, ' pokedolares'));
    END IF;
END$$

-- Registra a criacao de uma conta sem armazenar a senha no historico.
CREATE TRIGGER trg_usuario_cadastro AFTER INSERT ON usuario
FOR EACH ROW
BEGIN
    INSERT INTO log_usuario (id_usuario, nome, evento) VALUES (NEW.id_usuario, NEW.nome, 'cadastro');
END$$

-- Impede atualizacoes que deixariam o saldo de pokedolares negativo.
CREATE TRIGGER trg_usuario_antes_de_alterar BEFORE UPDATE ON usuario
FOR EACH ROW
BEGIN
    IF NEW.pokedolares < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saldo de pokedolares nao pode ficar negativo';
    END IF;
END$$

-- Registra alteracoes de nome e senha sem gravar o valor da senha.
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

-- Registra a exclusao antes que os dados da conta desaparecam.
CREATE TRIGGER trg_usuario_exclusao BEFORE DELETE ON usuario
FOR EACH ROW
BEGIN
    INSERT INTO log_usuario (id_usuario, nome, evento) VALUES (OLD.id_usuario, OLD.nome, 'exclusao');
END$$

DELIMITER ;

-- Views disponibilizam consultas agregadas para historico, perfil e telas do jogo.

-- Exibe cada batalha com os nomes dos participantes e sua duracao.
CREATE OR REPLACE VIEW vw_historico_batalhas AS
SELECT b.id_batalha,
       b.id_usuario,
       u.nome                                   AS jogador,
       COALESCE(u2.nome, 'Robô')                AS adversario,
       b.status,
       b.recompensa,
       b.iniciada_em,
       b.finalizada_em,
       b.is_boss,
       TIMESTAMPDIFF(SECOND, b.iniciada_em, b.finalizada_em) AS duracao_segundos
FROM batalha b
JOIN usuario u        ON u.id_usuario  = b.id_usuario
LEFT JOIN usuario u2  ON u2.id_usuario = b.id_usuario_2;

-- Resume partidas concluidas, resultados e ganhos de cada jogador humano.
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

-- Lista itens e valor acumulado apenas para inventarios com quantidade positiva.
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

-- Lista a colecao de cada jogador e indica quais monstros estao em sua equipe.
CREATE OR REPLACE VIEW vw_colecao_usuario AS
SELECT pu.id_usuario,
       u.nome AS jogador,
       pu.id_pokemon,
       COALESCE(m.nome, CONCAT('#', pu.id_pokemon)) AS pokemon,
       pu.conquistado_em,
       EXISTS (SELECT 1 FROM equipe_usuario e
               WHERE e.id_usuario = pu.id_usuario AND e.id_pokemon = pu.id_pokemon) AS na_equipe
FROM pokemon_usuario pu
JOIN usuario u ON u.id_usuario = pu.id_usuario
LEFT JOIN monstro m ON m.id_monstro = pu.id_pokemon;

-- Consolida atributos, tipos ordenados e proxima evolucao de cada monstro ativo.
CREATE OR REPLACE VIEW vw_pokedex AS
SELECT m.id_monstro AS id_pokemon,
       m.nome,
       m.vida_base, m.ataque_base, m.defesa_base, m.ataque_esp_base, m.defesa_esp_base, m.velocidade_base,
       (SELECT GROUP_CONCAT(t.chave ORDER BY tm.ordem)
          FROM tipo_monstro tm JOIN tipo t ON t.id_tipo = tm.id_tipo
         WHERE tm.id_monstro = m.id_monstro) AS tipos,
       e.id_evolucao,
       ev.nome AS evolucao_nome
FROM monstro m
LEFT JOIN evolucao_monstro e ON e.id_monstro = m.id_monstro
LEFT JOIN monstro ev         ON ev.id_monstro = e.id_evolucao
WHERE m.is_ativo = TRUE;

-- Resume compras por item e ignora registros marcados como estornados.
CREATE OR REPLACE VIEW vw_vendas_loja AS
SELECT i.chave,
       i.nome AS item,
       COUNT(l.id_log_loja)             AS compras,
       COALESCE(SUM(l.quantidade), 0)   AS unidades,
       COALESCE(SUM(l.total), 0)        AS arrecadado
FROM item_loja i
LEFT JOIN log_loja l ON l.id_item_loja = i.id_item_loja AND l.estornada = 0
GROUP BY i.id_item_loja, i.chave, i.nome;
