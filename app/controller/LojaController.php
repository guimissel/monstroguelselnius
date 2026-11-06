<?php

require_once __DIR__ . "/AutenticacaoController.php";
require_once __DIR__ . "/../database/Conexao.php";
require_once __DIR__ . "/../dao/LojaDAO.php";
require_once __DIR__ . "/../dao/InventarioDAO.php";

class LojaController
{
    // maximo de unidades de um item por compra
    private const MAXIMO_POR_COMPRA = 99;

    // GET api/loja: itens a venda (com quanto o jogador ja tem) + dinheiro
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
            $conexao = Conexao::getConexao();

            $itens = [];
            foreach ((new LojaDAO($conexao))->listar((int) $meuId) as $item) {
                $itens[] = $item->paraArray();
            }

            echo json_encode([
                "status" => 200,
                "pokedolares" => (new InventarioDAO($conexao))->pokedolares((int) $meuId),
                "itens" => $itens
            ]);
            return;
        }

        catch (Exception $e)
        {
            // o motivo real fica no log do apache (xampp/apache/logs/error.log)
            error_log("LojaController::listar: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel carregar a loja"
            ]);
            return;
        }
    }

    // POST api/loja/{chave}/comprar (campo "quantidade" no form)
    public function comprar(string $chave): void
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

        // quantidade: inteiro de 1 ate o maximo
        $quantidade = filter_var($_POST["quantidade"] ?? 1, FILTER_VALIDATE_INT);
        if ($quantidade === false || $quantidade < 1 || $quantidade > self::MAXIMO_POR_COMPRA)
        {
            http_response_code(400);
            echo json_encode([
                "status" => 400,
                "mensagem" => "Quantidade invalida (de 1 a " . self::MAXIMO_POR_COMPRA . ")"
            ]);
            return;
        }

        try
        {
            $resultado = (new LojaDAO(Conexao::getConexao()))->comprar((int) $meuId, $chave, $quantidade);

            echo json_encode([
                "status" => 200,
                "mensagem" => "compra realizada",
                "pokedolares" => $resultado["pokedolares"],
                "quantidade" => $resultado["quantidade"]
            ]);
            return;
        }

        catch (DomainException $e)
        {
            // erro esperado (item inexistente, sem dinheiro...)
            http_response_code($e->getCode());
            echo json_encode([
                "status" => $e->getCode(),
                "mensagem" => $e->getMessage()
            ]);
            return;
        }

        catch (Exception $e)
        {
            error_log("LojaController::comprar: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel concluir a compra"
            ]);
            return;
        }
    }
}
