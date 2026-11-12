<?php

class PokemonController
{
    // GET api/pokemon: todos os pokemons (stats, tipos, fraquezas e evolucao)
    public function listar(): void
    {
        header("Content-Type: application/json");

        try
        {
            $pokemons = (new PokemonDAO(Conexao::getConexao()))->todos();

            echo json_encode([
                "status" => 200,
                "pokemons" => array_map(fn (Pokemon $p) => $p->paraArray(), $pokemons)
            ]);
            return;
        }

        catch (Exception $e)
        {
            error_log("PokemonController::listar: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel carregar os pokemons"
            ]);
            return;
        }
    }
}
