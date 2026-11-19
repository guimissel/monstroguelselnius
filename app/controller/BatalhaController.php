<?php
class BatalhaController extends Controller
{
    // Inicia uma batalha e retorna o time adversario.
    public function iniciar(): void
    {
        header("Content-Type: application/json");

        $meuId = $_SESSION["id"] ?? null;
        // A batalha e seu estado pertencem ao jogador autenticado nesta sessão.
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
            // A camada de domínio prepara o adversário e determina o nível do encontro.
            $batalhaDAO = BatalhaDAO::NovaInstancia();
            $dados = $batalhaDAO->iniciar((int) $meuId);

            echo json_encode([
                "status" => 200,
                "id" => $dados["id"],
                "robo" => $dados["robo"],
                "chefe" => $dados["chefe"],
                "nivel" => $dados["nivel"]
            ]);
            return;
        }

        catch (DomainException $e)
        {
            // Regras de jogo indicam o status HTTP adequado para a recusa.
            http_response_code($e->getCode());
            echo json_encode([
                "status" => $e->getCode(),
                "mensagem" => $e->getMessage()
            ]);
            return;
        }

        catch (Exception $e)
        {
            // Registra o erro técnico sem alterar a resposta pública da API.
            error_log("BatalhaController::iniciar: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel iniciar a batalha",
                "detalhe" => $e->getMessage()
            ]);
            return;
        }
    }

    // Finaliza uma batalha e aplica recompensa e conquistas.
    public function finalizar(string $id): void
    {
        header("Content-Type: application/json");

        $meuId = $_SESSION["id"] ?? null;
        // Evita finalizar batalhas em nome de um jogador não identificado.
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

        $resultadoValido = in_array($resultado, ["vitoria", "derrota", "fuga"], true);
        // Confere o formato e os valores aceitos antes de registrar o resultado da partida.
        if (!$resultadoValido || $vivos === false || $idBatalha === false)
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
            // O DAO valida a titularidade e aplica recompensas e progresso em conjunto.
            $batalhaDAO = BatalhaDAO::NovaInstancia();
            $dadosBatalha = $batalhaDAO->finalizar($idBatalha, (int) $meuId, $resultado, $vivos);

            echo json_encode([
                "status" => 200,
                "recompensa" => $dadosBatalha["recompensa"],
                "pokedolares" => $dadosBatalha["pokedolares"],
                "pokemons" => $dadosBatalha["pokemons"]
            ]);
            return;
        }

        catch (DomainException $e)
        {
            // Devolve ao cliente a recusa de domínio sem mascarar seu status.
            http_response_code($e->getCode());
            echo json_encode([
                "status" => $e->getCode(),
                "mensagem" => $e->getMessage()
            ]);
            return;
        }

        catch (Exception $e)
        {
            // Mantém informações de diagnóstico fora da resposta normal da partida.
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
