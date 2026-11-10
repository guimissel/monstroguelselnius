<?php

require_once __DIR__ . "/AutenticacaoController.php";
require_once __DIR__ . "/../dao/EquipeDAO.php";

class EquipeController
{
    // GET api/equipe: a equipe salva, de onde escolher ("disponiveis") e a lista de basicos
    // "inicial" = true quando o jogador ainda nao tem pokemons (primeira escolha)
    public function listar(): void
    {
        // define que a resposta será JSON
        header("Content-Type: application/json");

        $meuId = $_SESSION["id"] ?? null;
        if (!isset($meuId))
        {
            http_response_code(401);
            echo json_encode([
                "status" => 401,
                "mensagem" => "usuario nao foi identificado"
            ]);
            return;
        }

        try
        {
            $dao = new EquipeDAO(Conexao::getConexao());
            $opcoes = $dao->disponiveis((int) $meuId);

            echo json_encode([
                "status" => 200,
                "equipe" => $dao->listar((int) $meuId),
                "inicial" => $opcoes["inicial"],
                "disponiveis" => $opcoes["lista"],
                "basicos" => $dao->basicos()
            ]);
            return;
        }

        catch (Exception $e)
        {
            // o motivo real fica no log do apache (xampp/apache/logs/error.log)
            error_log("EquipeController::listar: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel carregar a equipe"
            ]);
            return;
        }
    }

    // POST api/equipe (campo "pokemons" no form, ex: "4,7,25")
    public function salvar(): void
    {
        // define que a resposta será JSON
        header("Content-Type: application/json");

        $meuId = $_SESSION["id"] ?? null;
        if (!isset($meuId))
        {
            http_response_code(401);
            echo json_encode([
                "status" => 401,
                "mensagem" => "usuario nao foi identificado"
            ]);
            return;
        }

        // "4,7,25" -> [4, 7, 25] (qualquer coisa que nao seja numero inteiro vira erro)
        $ids = [];
        foreach (explode(",", (string) ($_POST["pokemons"] ?? "")) as $texto)
        {
            $id = filter_var(trim($texto), FILTER_VALIDATE_INT);
            if ($id === false || $id < 1)
            {
                http_response_code(400);
                echo json_encode([
                    "status" => 400,
                    "mensagem" => "Lista de pokemons invalida"
                ]);
                return;
            }
            $ids[] = $id;
        }

        try
        {
            $dao = new EquipeDAO(Conexao::getConexao());
            $dao->salvar((int) $meuId, $ids);

            echo json_encode([
                "status" => 200,
                "mensagem" => "equipe salva",
                "equipe" => $dao->listar((int) $meuId)
            ]);
            return;
        }

        catch (DomainException $e)
        {
            // erro esperado (menos de 3, repetidos, pokemon que o jogador nao tem...)
            http_response_code($e->getCode());
            echo json_encode([
                "status" => $e->getCode(),
                "mensagem" => $e->getMessage()
            ]);
            return;
        }

        catch (Exception $e)
        {
            error_log("EquipeController::salvar: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel salvar a equipe"
            ]);
            return;
        }
    }
}
