<?php
class EquipeController extends Controller
{
    // Lista a equipe, a colecao disponivel e as opcoes iniciais.
    public function listar(): void
    {
        header("Content-Type: application/json");

        $meuId = $_SESSION["id"] ?? null;
        // Sem a identidade da sessão, não há como limitar as consultas à coleção correta.
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
            // Reúne em uma única resposta o time atual e as opções disponíveis para edição.
            $dao = EquipeDAO::NovaInstancia();
            $opcoes = $dao->disponiveis((int) $meuId);

            echo json_encode([
                "status" => 200,
                "equipe" => $dao->listar((int) $meuId),
                "inicial" => $opcoes["inicial"],
                "disponiveis" => $opcoes["lista"],
                "basicos" => $dao->basicos(),
                "pedras" => $dao->pedras((int) $meuId)
            ]);
            return;
        }

        catch (Exception $e)
        {
            // Registra detalhes apenas no servidor e mantém a mensagem pública estável.
            error_log("EquipeController::listar: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel carregar a equipe"
            ]);
            return;
        }
    }

    // Evolui um Pokemon da colecao.
    public function evoluir(): void
    {
        header("Content-Type: application/json");

        $meuId = $_SESSION["id"] ?? null;
        // A evolução altera dados persistidos e só pode ocorrer para um jogador autenticado.
        if (!isset($meuId))
        {
            http_response_code(401);
            echo json_encode([
                "status" => 401,
                "mensagem" => "usuario nao foi identificado"
            ]);
            return;
        }

        $idPokemon = filter_var($_POST["pokemon"] ?? null, FILTER_VALIDATE_INT);
        // Rejeita IDs ausentes, não inteiros ou fora do domínio antes de chamar o DAO.
        if ($idPokemon === false || $idPokemon < 1)
        {
            http_response_code(400);
            echo json_encode([
                "status" => 400,
                "mensagem" => "Pokemon invalido"
            ]);
            return;
        }

        try
        {
            // O DAO aplica os requisitos de evolução e devolve as duas formas do Pokémon.
            $dao = EquipeDAO::NovaInstancia();
            $resultado = $dao->evoluir((int) $meuId, $idPokemon);

            echo json_encode([
                "status" => 200,
                "mensagem" => $resultado["antigo"]["nome"] . " evoluiu para " . $resultado["novo"]["nome"] . "!",
                "antigo" => $resultado["antigo"],
                "novo" => $resultado["novo"],
                "pedras" => $dao->pedras((int) $meuId)
            ]);
            return;
        }

        catch (DomainException $e)
        {
            // Regras de jogo já possuem status e mensagem apropriados para a resposta HTTP.
            http_response_code($e->getCode());
            echo json_encode([
                "status" => $e->getCode(),
                "mensagem" => $e->getMessage()
            ]);
            return;
        }

        catch (Exception $e)
        {
            // Falhas inesperadas não devem expor detalhes internos ao cliente.
            error_log("EquipeController::evoluir: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel evoluir o pokemon"
            ]);
            return;
        }
    }

    // Salva a equipe selecionada pelo jogador.
    public function salvar(): void
    {
        header("Content-Type: application/json");

        $meuId = $_SESSION["id"] ?? null;
        // A equipe sempre pertence ao jogador da sessão, nunca a um ID recebido pelo cliente.
        if (!isset($meuId))
        {
            http_response_code(401);
            echo json_encode([
                "status" => 401,
                "mensagem" => "usuario nao foi identificado"
            ]);
            return;
        }

        $ids = [];
        // Converte a lista textual e interrompe o salvamento se algum identificador for inválido.
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
            // A validação de limites, propriedade e duplicidade permanece centralizada no DAO.
            $dao = EquipeDAO::NovaInstancia();
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
            // Preserva o status HTTP definido pela regra de negócio.
            http_response_code($e->getCode());
            echo json_encode([
                "status" => $e->getCode(),
                "mensagem" => $e->getMessage()
            ]);
            return;
        }

        catch (Exception $e)
        {
            // Detalhes técnicos ficam no log; a API retorna uma mensagem genérica.
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
