-- INVENTARIO + ITENS + POKEDOLARES
--
-- Como rodar: phpMyAdmin > banco "monstroguelselnius" > aba SQL > colar tudo > Executar.
-- Rode UMA vez: ele recria item_loja e item_usuario (antes so tinham o id e estavam vazias),
-- entao rodar de novo apaga os itens dos jogadores.
-- Usa "ADD COLUMN IF NOT EXISTS", que o MariaDB do XAMPP aceita.

SET NAMES utf8mb4;  -- para os acentos dos nomes dos itens ficarem certos

-- dinheiro do jogador (ganho nas batalhas, gasto na loja)
ALTER TABLE usuario ADD COLUMN IF NOT EXISTS pokedolares INT NOT NULL DEFAULT 0;

DROP TABLE IF EXISTS item_usuario;
DROP TABLE IF EXISTS item_loja;

-- catalogo de itens: serve para o inventario, para a batalha e para a loja
CREATE TABLE item_loja (
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
CREATE TABLE item_usuario (
    id_usuario   INT NOT NULL,
    id_item_loja INT NOT NULL,
    quantidade   INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id_usuario, id_item_loja),
    FOREIGN KEY (id_usuario)   REFERENCES usuario(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_item_loja) REFERENCES item_loja(id_item_loja),
    CHECK (quantidade >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- itens do jogo (precos sao ponto de partida, podem ser ajustados aqui)
INSERT INTO item_loja (chave, nome, descricao, categoria, preco, imagem, cura_hp, cura_status, reviver, kit_inicial) VALUES
('potion',        'Poção',          'Recupera 20 de HP de um pokémon.',                       'pocao_hp',      50,  'potion.png',              20,   NULL,       0, 5),
('super-potion',  'Super Poção',    'Recupera 60 de HP de um pokémon.',                       'pocao_hp',      120, 'super potion.png',        60,   NULL,       0, 3),
('hyper-potion',  'Hiper Poção',    'Recupera 120 de HP de um pokémon.',                      'pocao_hp',      250, 'hiper potion.png',        120,  NULL,       0, 2),
('max-potion',    'Poção Máxima',   'Recupera todo o HP de um pokémon.',                      'pocao_hp',      400, 'max potion.png',          -1,   NULL,       0, 1),
('full-restore',  'Restaurar Tudo', 'Recupera todo o HP e cura qualquer condição.',           'pocao_hp',      600, 'full restore potion.png', -1,   'todos',    0, 1),
('revive',        'Reviver',        'Traz um pokémon desmaiado de volta com metade do HP.',   'pocao_hp',      350, 'reviver.webp',            0,    NULL,       1, 1),
('antidote',      'Antídoto',       'Cura um pokémon envenenado.',                            'pocao_condicao', 60, 'antidote.png',            0,    'poison',   0, 2),
('paralyze-heal', 'Anti-Paralisia', 'Cura um pokémon paralisado.',                            'pocao_condicao', 60, 'paralyse heal.png',       0,    'paralysis',0, 2),
('awakening',     'Despertar',      'Acorda um pokémon que está dormindo.',                   'pocao_condicao', 60, 'awakening.png',           0,    'sleep',    0, 2),
('ice-heal',      'Anti-Gelo',      'Descongela um pokémon congelado.',                       'pocao_condicao', 60, 'ice heal.png',            0,    'freeze',   0, 2),
('full-heal',     'Cura Total',     'Cura qualquer condição de um pokémon.',                  'pocao_condicao', 150,'full heal.webp',          0,    'todos',    0, 1);

-- jogadores que ja existem recebem o kit inicial (contas novas recebem pelo cadastro)
INSERT IGNORE INTO item_usuario (id_usuario, id_item_loja, quantidade)
SELECT u.id_usuario, i.id_item_loja, i.kit_inicial
FROM usuario u CROSS JOIN item_loja i
WHERE i.kit_inicial > 0;
