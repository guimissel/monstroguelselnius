<?php

// Le os pokemons do banco (view vw_pokedex + imagem_monstro) e devolve objetos Pokemon.
class PokemonDAO
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // todos os pokemons, ordenados pelo numero
    public function todos(): array
    {
        $linhas = $this->pdo->query("SELECT * FROM vw_pokedex ORDER BY id_pokemon")->fetchAll();
        return $this->montar($linhas);
    }

    public function buscar(int $id): ?Pokemon
    {
        $prepare = $this->pdo->prepare("SELECT * FROM vw_pokedex WHERE id_pokemon = :id");
        $prepare->execute(["id" => $id]);
        $lista = $this->montar($prepare->fetchAll());

        return $lista[0] ?? null;
    }

    // a colecao do jogador (pokemons que ele tem)
    public function daColecao(int $idUsuario): array
    {
        $prepare = $this->pdo->prepare(
            "SELECT p.* FROM vw_pokedex p
             JOIN pokemon_usuario pu ON pu.id_pokemon = p.id_pokemon
             WHERE pu.id_usuario = :id
             ORDER BY p.id_pokemon"
        );
        $prepare->execute(["id" => $idUsuario]);

        return $this->montar($prepare->fetchAll());
    }

    // a equipe salva do jogador, na ordem das posicoes
    public function daEquipe(int $idUsuario): Equipe
    {
        $prepare = $this->pdo->prepare(
            "SELECT p.* FROM vw_pokedex p
             JOIN equipe_usuario e ON e.id_pokemon = p.id_pokemon
             WHERE e.id_usuario = :id
             ORDER BY e.posicao"
        );
        $prepare->execute(["id" => $idUsuario]);

        $equipe = new Equipe();
        foreach ($this->montar($prepare->fetchAll()) as $pokemon) {
            $equipe->adicionar($pokemon);
        }
        return $equipe;
    }

    // linhas da view -> objetos (busca as imagens de todos de uma vez so)
    private function montar(array $linhas): array
    {
        if (count($linhas) === 0) {
            return [];
        }

        $ids = array_map(fn (array $l) => (int) $l["id_pokemon"], $linhas);
        $marcas = implode(",", array_fill(0, count($ids), "?"));
        $prepare = $this->pdo->prepare("SELECT id_monstro, tipo, caminho FROM imagem_monstro WHERE id_monstro IN ($marcas)");
        $prepare->execute($ids);

        $imagens = [];
        foreach ($prepare->fetchAll() as $imagem) {
            $imagens[(int) $imagem["id_monstro"]][$imagem["tipo"]] = $imagem["caminho"];
        }

        $pokemons = [];
        foreach ($linhas as $linha) {
            $pokemons[] = PokemonFactory::criar($linha, $imagens[(int) $linha["id_pokemon"]] ?? []);
        }
        return $pokemons;
    }
}
