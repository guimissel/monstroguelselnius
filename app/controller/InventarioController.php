<?php

require_once __DIR__ . "/AutenticacaoController.php";
<<<<<<< HEAD
=======
require_once __DIR__ . "/../config/Conexao.php";
>>>>>>> e93ff98364b97c91199b78a163153c7321b646f6
require_once __DIR__ . "/../dao/InventarioDAO.php";

class InventarioController
{
    // GET api/inventario: itens do jogador logado + dinheiro
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
            $inventarioDAO = new InventarioDAO(Conexao::getConexao());

            $itens = [];
            foreach ($inventarioDAO->listar((int) $meuId) as $item) {
                $itens[] = $item->paraArray();
            }

            echo json_encode([
                "status" => 200,
                "pokedolares" => $inventarioDAO->pokedolares((int) $meuId),
                "itens" => $itens
            ]);
            return;
        }

        catch (Exception $e)
        {
            // o motivo real fica no log do apache (xampp/apache/logs/error.log)
            error_log("InventarioController::listar: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel carregar o inventario"
            ]);
            return;
        }
    }

    // POST api/inventario/{chave}/usar: gasta 1 unidade do item
    public function usar(string $chave): void
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
            $inventarioDAO = new InventarioDAO(Conexao::getConexao());

            if (!$inventarioDAO->usar((int) $meuId, $chave))
            {
                http_response_code(409);
                echo json_encode([
                    "status" => 409,
                    "mensagem" => "Voce nao tem esse item"
                ]);
                return;
            }

            echo json_encode([
                "status" => 200,
                "mensagem" => "item usado"
            ]);
            return;
        }

        catch (Exception $e)
        {
            // o motivo real fica no log do apache (xampp/apache/logs/error.log)
            error_log("InventarioController::usar: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel usar o item"
            ]);
            return;
        }
    }
}
