-- EQUIPE E COLECAO DO JOGADOR
--
--  pokemon_basico : os pokemons de nivel basico (de onde saem os 3 primeiros e os do robo)
--  equipe_usuario : os 3 pokemons que o jogador leva para a batalha
--  pokemon_usuario: a colecao (os pokemons que o jogador tem; cresce ao vencer batalhas)
--  batalha_robo   : o time do robo de cada batalha (o servidor sorteia; quem vence leva os 3)
--
-- Como rodar: phpMyAdmin > banco "monstroguelselnius" > aba SQL > colar tudo > Executar.
-- Pode rodar mais de uma vez sem perder dados (so cria o que falta).
-- Rode depois do inventario.sql e do loja.sql (a tabela batalha precisa estar pronta).

SET NAMES utf8mb4;

-- pokemons de nivel basico (sem evolucao anterior) que o jogador pode escolher.
-- id_pokemon e o numero do pokemon na PokeAPI. O jogo busca imagem, tipos e golpes la.
CREATE TABLE IF NOT EXISTS pokemon_basico (
    id_pokemon INT         NOT NULL,
    nome       VARCHAR(30) NOT NULL,
    PRIMARY KEY (id_pokemon)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
