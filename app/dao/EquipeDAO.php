<?php

class EquipeDAO
{
    private const TAMANHO_EQUIPE = 3;
    private const PEDRA = "pedra-evolutiva";   // chave do item na loja

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // pokemons que o jogador pode escolher (nivel basico)
    public function basicos(): array
    {
        $prepare = $this->pdo->query(
            "SELECT b.id_pokemon, COALESCE(m.nome, b.nome) AS nome
             FROM pokemon_basico b
             LEFT JOIN monstro m ON m.id_monstro = b.id_pokemon
             ORDER BY b.id_pokemon"
        );

        $lista = [];
        foreach ($prepare->fetchAll() as $linha) {
            $lista[] = ["id" => (int) $linha["id_pokemon"], "nome" => $linha["nome"]];
        }
        return $lista;
    }

    // pokemons que o jogador tem: os 3 primeiros + os que ganhou do robo
    public function colecao(int $idUsuario): array
    {
        $sql = "SELECT pu.id_pokemon, m.nome
                FROM pokemon_usuario pu
                LEFT JOIN monstro m ON m.id_monstro = pu.id_pokemon
                WHERE pu.id_usuario = :id
                ORDER BY pu.id_pokemon";
        $prepare = $this->pdo->prepare($sql);
        $prepare->execute(["id" => $idUsuario]);

        $lista = [];
        foreach ($prepare->fetchAll() as $linha) {
            $id = (int) $linha["id_pokemon"];
            $lista[] = ["id" => $id, "nome" => $linha["nome"] ?? "#" . $id];
        }
        return $lista;
    }

    // de onde o jogador escolhe a equipe: a colecao dele.
    // quem ainda nao tem nenhum pokemon (primeira vez) escolhe entre os basicos, e eles viram a colecao
    public function disponiveis(int $idUsuario): array
    {
        $colecao = $this->colecao($idUsuario);

        if (count($colecao) > 0) {
            return ["inicial" => false, "lista" => $colecao];
        }
        return ["inicial" => true, "lista" => $this->basicos()];
    }

    // equipe salva do jogador, na ordem das posicoes (vazia se ainda nao escolheu)
    public function listar(int $idUsuario): array
    {
        $sql = "SELECT e.id_pokemon, m.nome
                FROM equipe_usuario e
                LEFT JOIN monstro m ON m.id_monstro = e.id_pokemon
                WHERE e.id_usuario = :id
                ORDER BY e.posicao";
        $prepare = $this->pdo->prepare($sql);
        $prepare->execute(["id" => $idUsuario]);

        $lista = [];
        foreach ($prepare->fetchAll() as $linha) {
            $id = (int) $linha["id_pokemon"];
            $lista[] = ["id" => $id, "nome" => $linha["nome"] ?? "#" . $id];
        }
        return $lista;
    }

    // quantas Pedras Evolutivas o jogador tem
    public function pedras(int $idUsuario): int
    {
        $prepare = $this->pdo->prepare(
            "SELECT COALESCE(SUM(iu.quantidade), 0)
             FROM item_usuario iu
             JOIN item_loja i ON i.id_item_loja = iu.id_item_loja
             WHERE iu.id_usuario = :id AND i.chave = :chave"
        );
        $prepare->execute(["id" => $idUsuario, "chave" => self::PEDRA]);

        return (int) $prepare->fetchColumn();
    }

    // evolui um pokemon da colecao gastando 1 Pedra Evolutiva.
    // o pokemon antigo sai da colecao (e da equipe, se estiver) e a evolucao entra no lugar dele.
    // tudo numa transaction: ou evolui e gasta a pedra, ou nada muda.
    // erros esperados saem como RegraDeJogoException (o codigo e o status http)
    public function evoluir(int $idUsuario, int $idPokemon): array
    {
        $this->pdo->beginTransaction();

        try {
            // o jogador tem esse pokemon? (trava a linha)
            $prepare = $this->pdo->prepare(
                "SELECT 1 FROM pokemon_usuario WHERE id_usuario = :usuario AND id_pokemon = :pokemon FOR UPDATE"
            );
            $prepare->execute(["usuario" => $idUsuario, "pokemon" => $idPokemon]);
            if (!$prepare->fetchColumn()) {
                throw new RegraDeJogoException("Voce nao tem esse pokemon", 404);
            }

            // ele tem evolucao?
            $prepare = $this->pdo->prepare(
                "SELECT e.id_evolucao, a.nome AS nome_antigo, n.nome AS nome_novo
                 FROM evolucao_monstro e
                 JOIN monstro a ON a.id_monstro = e.id_monstro
                 JOIN monstro n ON n.id_monstro = e.id_evolucao
                 WHERE e.id_monstro = :pokemon"
            );
            $prepare->execute(["pokemon" => $idPokemon]);
            $evolucao = $prepare->fetch();
            if (!$evolucao) {
                throw new RegraDeJogoException("Esse pokemon nao tem evolucao", 409);
            }
            $idNovo = (int) $evolucao["id_evolucao"];

            // nao pode ter dois iguais na colecao
            $prepare = $this->pdo->prepare(
                "SELECT 1 FROM pokemon_usuario WHERE id_usuario = :usuario AND id_pokemon = :novo"
            );
            $prepare->execute(["usuario" => $idUsuario, "novo" => $idNovo]);
            if ($prepare->fetchColumn()) {
                throw new RegraDeJogoException("Voce ja tem um " . $evolucao["nome_novo"], 409);
            }

            // gasta a pedra (o "quantidade > 0" garante que nao fica negativo)
            $prepare = $this->pdo->prepare(
                "UPDATE item_usuario iu
                 JOIN item_loja i ON i.id_item_loja = iu.id_item_loja
                 SET iu.quantidade = iu.quantidade - 1
                 WHERE iu.id_usuario = :usuario AND i.chave = :chave AND iu.quantidade > 0"
            );
            $prepare->execute(["usuario" => $idUsuario, "chave" => self::PEDRA]);
            if ($prepare->rowCount() === 0) {
                throw new RegraDeJogoException("Voce precisa de uma Pedra Evolutiva (compre na loja)", 409);
            }

            $prepare = $this->pdo->prepare(
                "UPDATE pokemon_usuario SET id_pokemon = :novo WHERE id_usuario = :usuario AND id_pokemon = :antigo"
            );
            $prepare->execute(["novo" => $idNovo, "usuario" => $idUsuario, "antigo" => $idPokemon]);

            $prepare = $this->pdo->prepare(
                "UPDATE equipe_usuario SET id_pokemon = :novo WHERE id_usuario = :usuario AND id_pokemon = :antigo"
            );
            $prepare->execute(["novo" => $idNovo, "usuario" => $idUsuario, "antigo" => $idPokemon]);

            $this->pdo->commit();

            return [
                "antigo" => ["id" => $idPokemon, "nome" => $evolucao["nome_antigo"]],
                "novo" => ["id" => $idNovo, "nome" => $evolucao["nome_novo"]]
            ];
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $erro;
        }
    }

    // troca a equipe inteira. $ids precisa ter 3 pokemons diferentes, todos da colecao do jogador
    // (na primeira vez, 3 basicos da lista, que viram a colecao).
    // erros esperados saem como RegraDeJogoException (o codigo e o status http)
    public function salvar(int $idUsuario, array $ids): void
    {
        $ids = array_values(array_unique(array_map("intval", $ids)));

        if (count($ids) !== self::TAMANHO_EQUIPE) {
            throw new RegraDeJogoException("Escolha " . self::TAMANHO_EQUIPE . " pokemons diferentes", 400);
        }

        $opcoes = $this->disponiveis($idUsuario);
        $permitidos = array_column($opcoes["lista"], "id");

        foreach ($ids as $id) {
            if (!in_array($id, $permitidos, true)) {
                throw new RegraDeJogoException(
                    $opcoes["inicial"] ? "Escolha apenas pokemons basicos da lista" : "Escolha apenas pokemons que voce tem",
                    400
                );
            }
        }

        $this->pdo->beginTransaction();

        try {
            // primeira equipe: os 3 escolhidos sao os primeiros pokemons da colecao
            if ($opcoes["inicial"]) {
                $prepare = $this->pdo->prepare(
                    "INSERT IGNORE INTO pokemon_usuario (id_usuario, id_pokemon) VALUES (:usuario, :pokemon)"
                );
                foreach ($ids as $idPokemon) {
                    $prepare->execute(["usuario" => $idUsuario, "pokemon" => $idPokemon]);
                }
            }

            $prepare = $this->pdo->prepare("DELETE FROM equipe_usuario WHERE id_usuario = :id");
            $prepare->execute(["id" => $idUsuario]);

            $prepare = $this->pdo->prepare(
                "INSERT INTO equipe_usuario (id_usuario, posicao, id_pokemon) VALUES (:usuario, :posicao, :pokemon)"
            );
            foreach ($ids as $indice => $idPokemon) {
                $prepare->execute([
                    "usuario" => $idUsuario,
                    "posicao" => $indice + 1,
                    "pokemon" => $idPokemon
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $erro;
        }
    }
}
