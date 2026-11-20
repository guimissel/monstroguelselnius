<?php

/**
 * Le os pokemons do banco dentro da view vw_pokedex
 * 
 * @author Vinicius Grub / Miguel Granville
 * @method array todos()
 * @method ?Pokemon buscarPorId(int $id)
 * @method array daColecao(int $idUsuario)
 * @method Equipe daEquipe(int $idUsuario)
 * @method array montar (array $linhas)
 */

class PokemonDAO extends DAO
{

    /**
     * Busca todos pokemons ordenando pelo seu numero
     * 
     * @author Miguel Granville
     * @return array retorna uma lista com todos pokemons
     */
    public function todos(): array
    {
        $resultadoBusca = $this->Buscar([
            "colunas" => "*",
            "tabela"=>"vw_pokedex",
            "pesquisa"=> "true ORDER BY id_pokemon",
            "valores" => []
        ], true);

        return $this->montar($resultadoBusca);
    }

    /**
     * Busca um pokemon usando o seu Id
     * 
     * @author Vinicius Grub
     * @param int $id chave usada para buscar no banco de dados
     * @return ?Pokemon retorna o pokemon buscado ou null caso não encontre
    */
    public function buscarPorId(int $id): ?Pokemon
    {
        $resultadoBusca = $this->Buscar([
            "colunas"=>"*",
            "tabela"=>"vw_pokedex",
            "pesquisa"=>"id_pokemon = :id",
            "valores"=>[
                "id"=>$id
            ]
        ], true);

        $lista = $this->montar($resultadoBusca);
        return $lista[0] ?? null;
    }

    // a colecao do jogador (pokemons que ele tem)
    public function daColecao(int $idUsuario): array
    {
        $resultadoBusca = $this->Buscar([
            "colunas"=>"p.*",
            "tabela"=>"vw_pokedex p JOIN pokemon_usuario pu ON pu.id_pokemon = p.id_pokemon",
            "pesquisa"=>"pu.id_usuario = :id ORDER BY p.id_pokemon",
            "valores"=>[
                "id"=>$idUsuario
            ]
        ], true);

        return $this->montar($resultadoBusca);
    }

    /**
     * Resgata o valor da equipe do jogador na ordem das posições
     * 
     * @author Vinicius Grub
     * @param int $idUsuario chave usada na busca pela equipe
     * @return Equipe retorna a equipe do usuario
     */
    public function daEquipe(int $idUsuario): Equipe
    {
        $resultadoBusca = $this->Buscar([
            "colunas"=>"*",
            "tabela" => "vw_pokedex p JOIN equipe_usuario e ON e.id_pokemon = p.id_pokemon",
            "pesquisa" => "e.id_usuario = :id ORDER BY e.posicao",
            "valores" => [
                "id" => $idUsuario
            ]
        ], true);

        $equipe = new Equipe();
        $arrayPokemonMontado = $this->montar($resultadoBusca);

        // adiciona cada pokemon a equipe do usuario
        foreach ($arrayPokemonMontado as $pokemon) {
            $equipe->adicionar($pokemon);
        }

        // retorna a equipe
        return $equipe;
    }

    /**
     * Monta a lista de pokemons PokemonFactory
     * 
     * @author Vinicius Grub
     * @param array $linhas lista de linhas para montar o pokemon
     * @return array retorna uma lista de pokemons gerados pela factory
    */
    private function montar(array $linhas): array
    {
        return array_map(fn (array $linha) => PokemonFactory::criar($linha), $linhas);
    }
}
