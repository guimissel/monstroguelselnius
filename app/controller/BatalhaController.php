<?php

require_once __DIR__ . "/AutenticacaoController.php";
require_once __DIR__ . "/../database/Conexao.php";
require_once __DIR__ . "/../dao/BatalhaDAO.php";

class BatalhaController
{
    // POST api/batalha/iniciar: abre uma batalha e devolve o id e o time do robo (ids da PokeAPI)
    public function iniciar(): void
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
            $batalha = (new BatalhaDAO(Conexao::getConexao()))->iniciar((int) $meuId);

            echo json_encode([
                "status" => 200,
                "id" => $batalha["id"],
                "robo" => $batalha["robo"]
            ]);
            return;
        }

        catch (DomainException $e)
        {
            // erro esperado (jogador sem equipe)
            http_response_code($e->getCode());
            echo json_encode([
                "status" => $e->getCode(),
                "mensagem" => $e->getMessage()
            ]);
            return;
        }

        catch (Exception $e)
        {
            // o motivo real fica no log do apache (xampp/apache/logs/error.log)
            error_log("BatalhaController::iniciar: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel iniciar a batalha",
                // o jogo mostra isso no console do navegador (F12) para descobrir o que faltou no banco
                "detalhe" => $e->getMessage()
            ]);
            return;
        }
    }

    // POST api/batalha/{id}/finalizar (campos "resultado" e "vivos" no form)
    // o servidor calcula o dinheiro e entrega os pokemons do robo, o navegador so informa como terminou
    public function finalizar(string $id): void
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

        $resultado = $_POST["resultado"] ?? "";
        $vivos = filter_var($_POST["vivos"] ?? 0, FILTER_VALIDATE_INT);
        $idBatalha = filter_var($id, FILTER_VALIDATE_INT);

        if (!in_array($resultado, ["vitoria", "derrota", "fuga"], true) || $vivos === false || $idBatalha === false)
        {
            http_response_code(400);
            echo json_encode([
                "status" => 400,
                "mensagem" => "Dados da batalha invalidos"
            ]);
            return;
        }

        try
        {
            $dados = (new BatalhaDAO(Conexao::getConexao()))->finalizar($idBatalha, (int) $meuId, $resultado, $vivos);

            echo json_encode([
                "status" => 200,
                "recompensa" => $dados["recompensa"],
                "pokedolares" => $dados["pokedolares"],
                "pokemons" => $dados["pokemons"]
            ]);
            return;
        }

        catch (DomainException $e)
        {
            // erro esperado (batalha nao existe, ja finalizada...)
            http_response_code($e->getCode());
            echo json_encode([
                "status" => $e->getCode(),
                "mensagem" => $e->getMessage()
            ]);
            return;
        }

        catch (Exception $e)
        {
            error_log("BatalhaController::finalizar: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel finalizar a batalha",
                "detalhe" => $e->getMessage()
            ]);
            return;
        }
    }
}
