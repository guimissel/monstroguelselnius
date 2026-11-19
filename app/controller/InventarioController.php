<?php
class InventarioController extends Controller
{
    // Lista os itens e o saldo do jogador.
    public function listar(): void
    {
        header("Content-Type: application/json");

        $meuId = $_SESSION["id"] ?? null;
        // Não consulta nem expõe o inventário de uma sessão sem jogador autenticado.
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
            // Serializa cada item mantendo o formato usado pelas telas do inventário.
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
            // Mantém o detalhe operacional no log e uma mensagem segura para o cliente.
            error_log("InventarioController::listar: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel carregar o inventario"
            ]);
            return;
        }
    }

    // Usa ou abre o item solicitado.
    public function usar(string $chave): void
    {
        header("Content-Type: application/json");

        $meuId = $_SESSION["id"] ?? null;
        // O consumo de itens altera recursos do jogador e exige uma sessão identificada.
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

            // Pokébolas concedem um Pokémon, portanto seguem o fluxo específico de abertura.
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

            // O DAO informa se o jogador não possui o item solicitado.
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
            // Retorna erros esperados de uso do item com o status indicado pela regra.
            http_response_code($e->getCode());
            echo json_encode([
                "status" => $e->getCode(),
                "mensagem" => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        catch (Exception $e)
        {
            // Evita expor detalhes internos em falhas inesperadas do inventário.
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
