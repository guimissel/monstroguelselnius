<?php
class InventarioController extends Controller
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
            $inventarioDAO = InventarioDAO::NovaInstancia();

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
            $inventarioDAO = InventarioDAO::NovaInstancia();

            if ($chave === "pokebola") {
                $resultado = $inventarioDAO->abrirPokebola((int) $meuId);
                echo json_encode([
                    "status" => 200,
                    "mensagem" => "Pokebola aberta",
                    "pokemon" => $resultado["pokemon"],
                    "quantidade" => $resultado["quantidade"]
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

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

        catch (DomainException $e)
        {
            http_response_code($e->getCode());
            echo json_encode([
                "status" => $e->getCode(),
                "mensagem" => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
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
