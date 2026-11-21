<?php

/**
 * Controla as ações do usuário em relação a equipe
 * 
 * @author Miguel Granville / Vinicius Grub
 */
class EquipeController extends Controller
{
    /**
     * Listar a equipe do usuário
     * 
     * @author Miguel Granville / Vinicius Grub
     * @return void não retorna a lista, mas a resposta o json chega no usuario
     */
    public function listar(): void
    {
        // id do usuario na sessao
        $meuId = $_SESSION["id"] ?? null;

        // Sem a identidade da sessão, não há como limitar as consultas à coleção correta.
        if (!isset($meuId))
        {
            // envia a resposta de erro do usuario não autenticado
            Controller::RespostaJson()
                ->status(401)
                ->mensagem("Usuário não foi identificado")
                ->enviar();
            return;
        }

        // tenta listar a equipe do usuario
        try
        {
            // Reúne em uma única resposta o time atual e as opções disponíveis para edição.
            $dao = EquipeDAO::NovaInstancia();

            // solicita ao DAO para ver os disponiveis
            $opcoes = $dao->disponiveis((int) $meuId);

            // envia a resposta
            Controller::RespostaJson()
                ->status(200)
                ->adicionar("equipe", $dao->listar((int) $meuId))
                ->adicionar("inicial", $opcoes["inicial"])
                ->adicionar("disponiveis", $opcoes["lista"])
                ->adicionar("basicos", $dao->basicos())
                ->adicionar("pedras", $dao->pedras((int) $meuId))
                ->enviar();
            return;
        }

        // exceção generica <T>
        catch (Exception $e)
        {
            // Registra detalhes apenas no servidor e mantém a mensagem pública estável.
            error_log("EquipeController::listar: " . $e->getMessage());

            // envia a resposta com a exception generics
            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Não foi possivel carregar a equipe")
                ->adicionar("detalhe", $e->getMessage())
                ->enviar();
            return;
        }
    }

    /**
     * Evolui um pokemon da coleção do usuario
     * 
     * @author Miguel Granville / Vinicius Grub
     */
    public function evoluir(): void
    {
        // id do usuario na sessão
        $meuId = $_SESSION["id"] ?? null;

        // A evolução altera dados persistidos e só pode ocorrer para um jogador autenticado.
        if (!isset($meuId))
        {
            // envia a mensagem de não identificado
            Controller::RespostaJson()
                ->status(401)
                ->mensagem("Usuário não foi identificado")
                ->enviar();
            return;
        }

        // valida se o id do pokemon é um número INTEIRO valido
        $idPokemon = filter_var($_POST["pokemon"] ?? null, FILTER_VALIDATE_INT);

        // Rejeita IDs ausentes, não inteiros ou fora do domínio antes de chamar o DAO.
        if ($idPokemon === false || $idPokemon < 1)
        {
            // envia a resposta
            Controller::RespostaJson()
                ->status(400)
                ->mensagem("O ID de pokemon digitado é inválido")
                ->enviar();
            return;
        }

        // tenta evoluir o pokemon pelo id
        try
        {
            // O DAO aplica os requisitos de evolução e devolve as duas formas do Pokémon.
            $dao = EquipeDAO::NovaInstancia();

            // pede ao DAO para evoluir o pokemon do usuario
            $resultado = $dao->evoluir((int) $meuId, $idPokemon);

            // envia a resposta em json
            Controller::RespostaJson()
                ->status(200)
                ->mensagem($resultado["antigo"]["nome"] . " evoluiu para " . $resultado["novo"]["nome"] . "!")
                ->adicionar("antigo", $resultado["antigo"])
                ->adicionar("novo", $resultado["novo"])
                ->adicionar("pedras", $dao->pedras((int) $meuId))
                ->enviar();
            return;
        }

        // exceção de dominio
        catch (DomainException $e)
        {
            // Regras de jogo já possuem status e mensagem apropriados para a resposta HTTP.
            Controller::RespostaJson()
                ->status($e->getCode())
                ->mensagem($e->getMessage())
                ->enviar();
            return;
        }

        // exceção generica
        catch (Exception $e)
        {
            // Falhas inesperadas não devem expor detalhes internos ao cliente.
            error_log("EquipeController::evoluir: " . $e->getMessage());

            // envia a resposta
            Controller::RespostaJson()
                ->status($e->getCode())
                ->mensagem($e->getMessage())
                ->enviar();
            return;
        }
    }

    /**
     * Salva a equipe selecionada pelo jogador no banco de dados
     * 
     * @author Miguel Granville / Vinicius Grub
     */
    public function salvar(): void
    {
        // id do usuario na sessão
        $meuId = $_SESSION["id"] ?? null;

        // A equipe sempre pertence ao jogador da sessão, nunca a um ID recebido pelo cliente.
        if (!isset($meuId))
        {
            // envia a resposta
            Controller::RespostaJson()
                ->status(401)
                ->mensagem("Usuário não foi identificado")
                ->enviar();
            return;
        }

        // lista dos ids da nova equipe
        $ids = [];

        // Converte a lista textual e interrompe o salvamento se algum identificador for inválido.
        foreach (explode(",", (string) ($_POST["pokemons"] ?? "")) as $texto)
        {
            // verifica se é um int valido
            $id = filter_var(trim($texto), FILTER_VALIDATE_INT);

            // senão for cai aqui
            if ($id === false || $id < 1)
            {
                // responde com um erro
                Controller::RespostaJson()
                    ->status(400)
                    ->mensagem("Existe um ID de pokemon inválido no meio da lista da equipe do usuário")
                    ->enviar();
                return;
            }

            // caso passe na validação: adicione na lista
            $ids[] = $id;
        }

        // tenta salvar a equipe do usuário
        try
        {
            // A validação de limites, propriedade e duplicidade permanece centralizada no DAO.
            $dao = EquipeDAO::NovaInstancia();

            // pede para o DAO salvar a equipe do usuario
            $dao->salvar((int) $meuId, $ids);

            // envia a resposta de sucesso
            Controller::RespostaJson()
                ->status(200)
                ->mensagem("A equipe nova do usuário foi salva no banco de dados")
                ->adicionar("equipe", $dao->listar((int) $meuId))
                ->enviar();
            return;
        }

        // trata a exceção de dominio
        catch (DomainException $e)
        {
            // Preserva o status HTTP definido pela regra de negócio.
            Controller::RespostaJson()
                ->status($e->getCode())
                ->mensagem($e->getMessage())
                ->enviar();
            return;
        }

        // trata aa exceção generica
        catch (Exception $e)
        {
            // Detalhes técnicos ficam no log; a API retorna uma mensagem genérica.
            error_log("EquipeController::salvar: " . $e->getMessage());

            // envia a resposta de erro
            Controller::RespostaJson()
                ->status($e->getCode())
                ->mensagem("Não foi possivel salva a equipe do usuário")
                ->adicionar("detalhe", $e->getMessage())
                ->enviar();
            return;
        }
    }
}
