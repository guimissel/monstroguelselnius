<?php
class LojaController extends Controller
{
    private const MAXIMO_POR_COMPRA = 99;

    // Lista os itens disponiveis e o saldo do jogador.
    public function listar(): void
    {
        header("Content-Type: application/json");

        $meuId = $_SESSION["id"] ?? null;
        // A loja apresenta o saldo e o catálogo específicos da conta autenticada.
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
            $lojaDAO = LojaDAO::NovaInstancia();
            $itens = [];

            // Converte os objetos do catálogo para estruturas que possam ser serializadas em JSON.
            foreach ($lojaDAO->listar((int) $meuId) as $item) {
                $itens[] = $item->paraArray();
            }

            $inventarioDAO = InventarioDAO::NovaInstancia();
            $pokedolares = $inventarioDAO->pokedolares((int) $meuId);

            echo json_encode([
                "status" => 200,
                "pokedolares" => $pokedolares,
                "itens" => $itens
            ]);
            return;
        }

        catch (Exception $e)
        {
            // O detalhe da falha fica no log para não vazar configuração ou dados internos.
            error_log("LojaController::listar: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel carregar a loja"
            ]);
            return;
        }
    }

    // Compra uma quantidade de um item da loja.
    public function comprar(string $chave): void
    {
        header("Content-Type: application/json");

        $meuId = $_SESSION["id"] ?? null;
        // Impede compras associadas a uma sessão sem jogador identificado.
        if (!isset($meuId))
        {
            http_response_code(401);
            echo json_encode([
                "status" => 401,
                "mensagem" => "usuario nao foi identificado"
            ]);
            return;
        }

        $quantidade = filter_var($_POST["quantidade"] ?? 1, FILTER_VALIDATE_INT);
        
        $quantidadeInvalida = $quantidade > self::MAXIMO_POR_COMPRA;
        $quantidadeNegativa = $quantidade < 1;
        $quantidadeNula = $quantidade === false;

        // Limita a quantidade solicitada para manter a operação dentro das regras da loja.
        if ($quantidadeInvalida || $quantidadeNegativa || $quantidadeNula)
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
            // O DAO verifica estoque e saldo e realiza a compra como uma única operação.
            $lojaDAO = LojaDAO::NovaInstancia();
            $resultado = $lojaDAO->comprar((int) $meuId, $chave, $quantidade);

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
            // Expõe recusas previstas, como falta de saldo, com o status correspondente.
            http_response_code($e->getCode());
            echo json_encode([
                "status" => $e->getCode(),
                "mensagem" => $e->getMessage()
            ]);
            return;
        }

        catch (Exception $e)
        {
            // Registra a causa técnica sem repassá-la como parte da resposta ao jogador.
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
