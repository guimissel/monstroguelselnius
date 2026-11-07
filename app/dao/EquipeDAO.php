<?php

class EquipeDAO
{
    private const TAMANHO_EQUIPE = 3;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // pokemons que o jogador pode escolher (nivel basico)
    public function basicos(): array
    {
        $prepare = $this->pdo->query("SELECT id_pokemon, nome FROM pokemon_basico ORDER BY id_pokemon");

        $lista = [];
        foreach ($prepare->fetchAll() as $linha) {
            $lista[] = ["id" => (int) $linha["id_pokemon"], "nome" => $linha["nome"]];
        }
        return $lista;
    }

    // pokemons que o jogador tem: os 3 primeiros + os que ganhou do robo
    public function colecao(int $idUsuario): array
    {
        $sql = "SELECT pu.id_pokemon, b.nome
                FROM pokemon_usuario pu
                LEFT JOIN pokemon_basico b ON b.id_pokemon = pu.id_pokemon
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
        $sql = "SELECT e.id_pokemon, b.nome
                FROM equipe_usuario e
                LEFT JOIN pokemon_basico b ON b.id_pokemon = e.id_pokemon
                WHERE e.id_usuario = :id
                ORDER BY e.posicao";
        $prepare = $this->pdo->prepare($sql);
        $prepare->execute(["id" => $idUsuario]);

        $lista = [];
        foreach ($prepare->fetchAll() as $linha) {
            $lista[] = ["id" => (int) $linha["id_pokemon"], "nome" => $linha["nome"]];
        }
        return $lista;
    }

    // troca a equipe inteira. $ids precisa ter 3 pokemons diferentes, todos da colecao do jogador
    // (na primeira vez, 3 basicos da lista, que viram a colecao).
    // erros esperados saem como DomainException (o codigo e o status http)
    public function salvar(int $idUsuario, array $ids): void
    {
        $ids = array_values(array_unique(array_map("intval", $ids)));

        if (count($ids) !== self::TAMANHO_EQUIPE) {
            throw new DomainException("Escolha " . self::TAMANHO_EQUIPE . " pokemons diferentes", 400);
        }

        $opcoes = $this->disponiveis($idUsuario);
        $permitidos = array_column($opcoes["lista"], "id");

        foreach ($ids as $id) {
            if (!in_array($id, $permitidos, true)) {
                throw new DomainException(
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
