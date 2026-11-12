DROP TABLE IF EXISTS monstro_batalha_usuario;
DROP TABLE IF EXISTS log;
DROP TABLE IF EXISTS batalha;
DROP TABLE IF EXISTS ataque_possivel_monstro;
DROP TABLE IF EXISTS ataque;
DROP TABLE IF EXISTS imagem_monstro;
DROP TABLE IF EXISTS tipo_monstro;
DROP TABLE IF EXISTS imune_tipo;
DROP TABLE IF EXISTS fraqueza_tipo;
DROP TABLE IF EXISTS eficaz_tipo;
DROP TABLE IF EXISTS categoria_dano;
DROP TABLE IF EXISTS tipo;
DROP TABLE IF EXISTS evolucao;
DROP TABLE IF EXISTS monstro;
DROP TABLE IF EXISTS item_usuario;
DROP TABLE IF EXISTS item_loja;
DROP TABLE IF EXISTS tipo_imagem;
DROP TABLE IF EXISTS efeito;
DROP TABLE IF EXISTS usuario;

CREATE TABLE usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    pokedolares INT DEFAULT 0 NOT NULL,
    is_bot BOOLEAN DEFAULT FALSE NOT NULL,
    is_ativo BOOLEAN DEFAULT TRUE NOT NULL
);

CREATE TABLE efeito (
    id_efeito INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    is_ativo BOOLEAN DEFAULT TRUE NOT NULL
);

CREATE TABLE tipo_imagem (
    id_tipo_imagem INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL
);

CREATE TABLE item_loja (
    id_item_loja INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    preco INT DEFAULT 0 NOT NULL
);

CREATE TABLE item_usuario (
    id_usuario INT NOT NULL,
    id_item_loja INT NOT NULL,
    quantidade INT DEFAULT 1 NOT NULL,

    PRIMARY KEY (id_usuario, id_item_loja),

    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (id_item_loja) REFERENCES item_loja(id_item_loja)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE monstro (
    id_monstro INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    vida_base INT NOT NULL,
    ataque_base INT NOT NULL,
    defesa_base INT NOT NULL,
    ataque_esp_base INT NOT NULL,
    defesa_esp_base INT NOT NULL,
    velocidade_base INT NOT NULL,
    is_ativo BOOLEAN DEFAULT TRUE NOT NULL
);

CREATE TABLE evolucao (
    id_monstro_base INT NOT NULL,
    id_monstro_evoluido INT NOT NULL,
    pokedolares_necessarios INT NOT NULL,

    PRIMARY KEY (id_monstro_base, id_monstro_evoluido),

    FOREIGN KEY (id_monstro_base) REFERENCES monstro(id_monstro)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    FOREIGN KEY (id_monstro_evoluido) REFERENCES monstro(id_monstro)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

CREATE TABLE tipo (
    id_tipo INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL
);

CREATE TABLE categoria_dano (
    id_categoria_dano INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL
);

CREATE TABLE eficacias_tipo (
    id_tipo INT NOT NULL,
    id_tipo_eficacia INT NOT NULL,

    PRIMARY KEY (id_tipo, id_tipo_eficacia),

    FOREIGN KEY (id_tipo) REFERENCES tipo(id_tipo)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo_eficacia) REFERENCES tipo(id_tipo)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE fraquezas_tipo (
    id_tipo INT NOT NULL,
    id_tipo_fraqueza INT NOT NULL,

    PRIMARY KEY (id_tipo, id_tipo_fraqueza),

    FOREIGN KEY (id_tipo) REFERENCES tipo(id_tipo)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo_fraqueza) REFERENCES tipo(id_tipo)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE imunidades_tipo (
    id_tipo INT NOT NULL,
    id_tipo_imunidade INT NOT NULL,

    PRIMARY KEY (id_tipo, id_tipo_imunidade),

    FOREIGN KEY (id_tipo) REFERENCES tipo(id_tipo)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo_imunidade) REFERENCES tipo(id_tipo)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE tipo_monstro (
    id_monstro INT NOT NULL,
    id_tipo INT NOT NULL,

    PRIMARY KEY (id_monstro, id_tipo),

    FOREIGN KEY (id_monstro) REFERENCES monstro(id_monstro)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo) REFERENCES tipo(id_tipo)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

CREATE TABLE imagem_monstro (
    id_imagem_monstro INT AUTO_INCREMENT PRIMARY KEY,
    id_monstro INT NOT NULL,
    caminho VARCHAR(255) NOT NULL,
    id_tipo_imagem INT NOT NULL,
    
    FOREIGN KEY (id_monstro) REFERENCES monstro(id_monstro)
    	  ON DELETE CASCADE
    	  ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo_imagem) REFERENCES tipo_imagem(id_tipo_imagem)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

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

    FOREIGN KEY (id_categoria_dano) REFERENCES categoria_dano(id_categoria_dano)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    FOREIGN KEY (id_tipo) REFERENCES tipo(id_tipo)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    FOREIGN KEY (id_efeito) REFERENCES efeito(id_efeito)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

CREATE TABLE ataque_possivel_monstro (
    id_ataque INT,
    id_monstro INT,

    PRIMARY KEY (id_ataque, id_monstro),

    FOREIGN KEY (id_ataque) REFERENCES ataque(id_ataque)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (id_monstro) REFERENCES monstro(id_monstro)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE batalha (
    id_batalha INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_usuario_2 INT NOT NULL,
    turno_atual INT DEFAULT 1 NOT NULL,
    cooldown_troca_1 INT DEFAULT 0 NOT NULL,
    cooldown_troca_2 INT DEFAULT 0 NOT NULL,

    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    FOREIGN KEY (id_usuario_2) REFERENCES usuario(id_usuario)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

CREATE TABLE log (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    id_batalha INT NOT NULL,

    FOREIGN KEY (id_batalha) REFERENCES batalha(id_batalha)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

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

    FOREIGN KEY (id_monstro) REFERENCES monstro(id_monstro)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    FOREIGN KEY (id_batalha) REFERENCES batalha(id_batalha)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    FOREIGN KEY (id_efeito_atual) REFERENCES efeito(id_efeito)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    FOREIGN KEY (id_ataque_1) REFERENCES ataque(id_ataque)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    FOREIGN KEY (id_ataque_2) REFERENCES ataque(id_ataque)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    FOREIGN KEY (id_ataque_3) REFERENCES ataque(id_ataque)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    FOREIGN KEY (id_ataque_4) REFERENCES ataque(id_ataque)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);