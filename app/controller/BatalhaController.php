<?php

/**
 * O controlador de batalha
 * 
 * @author Miguel Granville / Vinicius grub
 * @method void iniciar ()
 * @method void finalizar (string $id)
 */
class BatalhaController extends Controller
{
    /**
     * Inicia uma nova batalha
     * 
     * @author Miguel Granville / Vinicius Grub
     * @return void não tem retorno garantindo que foi criada, exceto o status
     */
    public function iniciar(): void
    {
        // verifica se a sessão é null
        $meuId = $_SESSION["id"] ?? null;

        // A batalha e seu estado pertencem ao jogador autenticado nesta sessão.
        if (!isset($meuId))
        {
            // resposta de erro 401
            Controller::RespostaJson()
                ->status(401)
                ->mensagem("Usuário não foi identificado")
                ->enviar();
            return;
        }

        // tenta fazer o inicio da batalha
        try
        {
            // A camada de domínio prepara o adversário e determina o nível do encontro.
            $batalhaDAO = BatalhaDAO::NovaInstancia();
            $dados = $batalhaDAO->iniciar((int) $meuId);

            // retorno do json como resposta
            Controller::RespostaJson()
                ->status(200)
                ->adicionar("id", $dados["id"])
                ->adicionar("robo", $dados["robo"])
                ->adicionar("chefe", $dados["chefe"])
                ->adicionar("nivel", $dados["nivel"])
                ->enviar();
            return;
        }

        // retorna a exception de domain
        catch (DomainException $e)
        {
            // Regras de jogo indicam o status HTTP adequado para a recusa.
            Controller::RespostaJson()
                ->status($e->getCode())
                ->mensagem($e->getMessage())
                ->enviar();

            // trata a exception
            throw $e;
            return;
        }

        // exception generica $e
        catch (Exception $e)
        {
            // resposta montado em json
            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Não foi possivel iniciar a batalha")
                ->adicionar("detalhe", $e->getMessage())
                ->enviar();
            
            // trata a exception
            throw $e;
            return;
        }
    }

    /**
     * Finaliza uma batalha e concede a recompensa
     * 
     * @author Miguel Granville / Vinicius Grub
     * @param string $id identifica a batalha que sera finalizada
     * @return void não retorna nenhuma confirmação que foi finalizado
     */
    public function finalizar(string $id): void
    {
        // procura o id dentro da sessão
        $meuId = $_SESSION["id"] ?? null;

        // Evita finalizar batalhas em nome de um jogador não identificado.
        if (!isset($meuId))
        {
            // envia uma resposta de não autorizado
            Controller::RespostaJson()
                ->status(401)
                ->mensagem("Usuário não foi identificado")
                ->enviar();
            return;
        }

        // resultado da batalha (vitoria, derrota, fuga)
        $resultado = $_POST["resultado"] ?? "";

        // vivos passa por uma validação para verificar se é um numero INTEIRO válido
        $vivos = filter_var($_POST["vivos"] ?? 0, FILTER_VALIDATE_INT);

        // id da batalha passa por uma validação para verificar se é um número INTEIRO válido
        $idBatalha = filter_var($id, FILTER_VALIDATE_INT);

        // verifica se esta contido numa lista de resultados válidos
        $resultadoValido = in_array($resultado, ["vitoria", "derrota", "fuga"], true);

        // Confere o formato e os valores aceitos antes de registrar o resultado da partida.
        if (!$resultadoValido || $vivos === false || $idBatalha === false)
        {
            // responde com json e usa o método para enviar
            Controller::RespostaJson()
                ->status(400)
                ->mensagem("Os dados passados da batalha são inválidos")
                ->enviar();
            return;
        }

        // tenta finalizar a batalha no banco de dados
        try
        {
            // O DAO valida a titularidade e aplica recompensas e progresso em conjunto.
            $batalhaDAO = BatalhaDAO::NovaInstancia();

            // envia para o DAO finalizar
            $dadosBatalha = $batalhaDAO->finalizar($idBatalha, (int) $meuId, $resultado, $vivos);

            // envia a resposta
            Controller::RespostaJson()
                ->status(200)
                ->adicionar("recompensa", $dadosBatalha["recompensa"])
                ->adicionar("pokedolares", $dadosBatalha["pokedolares"])
                ->adicionar("pokemons", $dadosBatalha["pokemons"])
                ->enviar();
            return;
        }

        // problema de dominio
        catch (DomainException $e)
        {
            // Devolve ao cliente a recusa de domínio sem mascarar seu status.
            Controller::RespostaJson()
                ->status($e->getCode())
                ->mensagem($e->getMessage())
                ->enviar();

            // trata a exception
            throw $e;
            return;
        }

        // trata uma exception <T> 
        catch (Exception $e)
        {
            // Mantém informações de diagnóstico fora da resposta normal da partida.
            error_log("BatalhaController::finalizar: " . $e->getMessage());
            
            // envia resposta na rest
            Controller::RespostaJson()
                ->status($e->getCode())
                ->mensagem("Não foi possivel finalizar a batalha")
                ->adicionar("detalhe", $e->getMessage())
                ->enviar();

            // trata a exception
            throw $e;
            return;
        }
    }
}
