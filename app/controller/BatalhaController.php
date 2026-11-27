<?php

/**
 * Controlador de Batalha — expõe os endpoints HTTP de início, turno e finalização.
 *
 * Toda a validação de sessão, parâmetros e regras de domínio acontece aqui e no
 * MotorDeBatalha/BatalhaDAO, impedindo que o navegador manipule o resultado da luta.
 *
 * @author Miguel Granville / Vinicius Grub
 * @method void iniciar()
 * @method void turno(string $id)
 * @method void finalizar(string $id)
 */
class BatalhaController extends Controller
{
    /**
     * Inicia uma nova batalha, sorteando o time adversário no servidor e
     * retornando as configurações e o estado inicial completo para o frontend.
     *
     * @author Miguel Granville / Vinicius Grub
     * @return void
     */
    public function iniciar(): void
    {
        $meuId = $_SESSION["id"] ?? null;

        // A batalha e seu estado pertencem exclusivamente ao jogador autenticado na sessão
        if (!isset($meuId)) {
            Controller::RespostaJson()
                ->status(401)
                ->mensagem("Usuário não foi identificado")
                ->enviar();
            return;
        }

        try {
            $batalhaDAO = BatalhaDAO::NovaInstancia();
            $dados      = $batalhaDAO->iniciar((int) $meuId);

            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [INFO] BatalhaController::iniciar: Batalha ID {$dados["id"]} iniciada pelo usuario ID {$meuId}" . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

            // Devolve ID, dados do oponente, configurações da batalha e estado inicial sincronizado
            Controller::RespostaJson()
                ->status(200)
                ->adicionar("id", $dados["id"])
                ->adicionar("robo", $dados["robo"])
                ->adicionar("chefe", $dados["chefe"])
                ->adicionar("nivel", $dados["nivel"])
                ->adicionar("configuracoes", $dados["configuracoes"])
                ->adicionar("batalha", $dados["batalha"])
                ->enviar();
            return;
        } catch (DomainException $e) {
            Controller::RespostaJson()
                ->status($e->getCode() ?: 400)
                ->mensagem($e->getMessage())
                ->enviar();
            return;
        } catch (Exception $e) {
            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [ERRO] BatalhaController::iniciar: " . $e->getMessage() . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Não foi possível iniciar a batalha")
                ->adicionar("detalhe", $e->getMessage())
                ->enviar();
            return;
        }
    }

    /**
     * Processa uma jogada/turno enviada pelo jogador durante a batalha.
     *
     * Aceita corpo JSON ou parâmetros POST tradicionais com:
     *   - tipo: 'golpe' | 'trocar' | 'trocar_forcado' | 'item' | 'fugir'
     *   - indice: inteiro (para golpe, troca voluntária ou troca forçada)
     *   - item / alvo: chave do item e índice do pokémon alvo (para item)
     *
     * @author Miguel Granville / Vinicius Grub
     * @param string $id ID da batalha recebido pela rota
     * @return void
     */
    public function turno(string $id): void
    {
        $meuId = $_SESSION["id"] ?? null;

        if (!isset($meuId)) {
            Controller::RespostaJson()
                ->status(401)
                ->mensagem("Usuário não foi identificado")
                ->enviar();
            return;
        }

        $idBatalha = filter_var($id, FILTER_VALIDATE_INT);
        if ($idBatalha === false || $idBatalha < 1) {
            Controller::RespostaJson()
                ->status(400)
                ->mensagem("ID da batalha inválido")
                ->enviar();
            return;
        }

        // Lê a ação enviada via JSON (fetch application/json) ou via formulário $_POST
        $entrada = $this->lerCorpoAcao();
        $tipo    = trim((string) ($entrada["tipo"] ?? ""));

        if (!in_array($tipo, ["golpe", "trocar", "trocar_forcado", "item", "fugir"], true)) {
            Controller::RespostaJson()
                ->status(400)
                ->mensagem("Ação de batalha inválida")
                ->enviar();
            return;
        }

        $acao = [
            "tipo"   => $tipo,
            "indice" => $entrada["indice"] ?? null,
            "item"   => $entrada["item"] ?? null,
            "alvo"   => $entrada["alvo"] ?? null
        ];

        try {
            $batalhaDAO = BatalhaDAO::NovaInstancia();
            $resultado  = $batalhaDAO->processarTurno($idBatalha, (int) $meuId, $acao);

            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [INFO] BatalhaController::turno: Batalha ID {$idBatalha}, usuario ID {$meuId}, acao '{$tipo}'" . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

            Controller::RespostaJson()
                ->status(200)
                ->adicionar("eventos", $resultado["eventos"])
                ->adicionar("estado", $resultado["estado"])
                ->enviar();
            return;
        } catch (DomainException $e) {
            Controller::RespostaJson()
                ->status($e->getCode() ?: 400)
                ->mensagem($e->getMessage())
                ->enviar();
            return;
        } catch (Exception $e) {
            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [ERRO] BatalhaController::turno: " . $e->getMessage() . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Não foi possível processar o turno da batalha")
                ->adicionar("detalhe", $e->getMessage())
                ->enviar();
            return;
        }
    }

    /**
     * Finaliza uma batalha (usado principalmente para registrar fuga via sendBeacon ao sair da página).
     * Vitórias e derrotas também são validadas contra os pontos de vida salvos no banco.
     *
     * @author Miguel Granville / Vinicius Grub
     * @param string $id Identifica a batalha que será finalizada
     * @return void
     */
    public function finalizar(string $id): void
    {
        $meuId = $_SESSION["id"] ?? null;

        if (!isset($meuId)) {
            Controller::RespostaJson()
                ->status(401)
                ->mensagem("Usuário não foi identificado")
                ->enviar();
            return;
        }

        $resultado = $_POST["resultado"] ?? "";
        $vivos     = filter_var($_POST["vivos"] ?? 0, FILTER_VALIDATE_INT);
        $idBatalha = filter_var($id, FILTER_VALIDATE_INT);

        $resultadoValido = in_array($resultado, ["vitoria", "derrota", "fuga"], true);

        if (!$resultadoValido || $vivos === false || $idBatalha === false) {
            Controller::RespostaJson()
                ->status(400)
                ->mensagem("Os dados passados da batalha são inválidos")
                ->enviar();
            return;
        }

        try {
            $batalhaDAO   = BatalhaDAO::NovaInstancia();
            $dadosBatalha = $batalhaDAO->finalizar($idBatalha, (int) $meuId, $resultado, $vivos);

            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [INFO] BatalhaController::finalizar: Batalha ID {$idBatalha} finalizada pelo usuario ID {$meuId} ({$resultado})" . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

            Controller::RespostaJson()
                ->status(200)
                ->adicionar("recompensa", $dadosBatalha["recompensa"])
                ->adicionar("pokedolares", $dadosBatalha["pokedolares"])
                ->adicionar("pokemons", $dadosBatalha["pokemons"])
                ->enviar();
            return;
        } catch (DomainException $e) {
            Controller::RespostaJson()
                ->status($e->getCode() ?: 400)
                ->mensagem($e->getMessage())
                ->enviar();
            return;
        } catch (Exception $e) {
            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [ERRO] BatalhaController::finalizar: " . $e->getMessage() . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Não foi possível finalizar a batalha")
                ->adicionar("detalhe", $e->getMessage())
                ->enviar();
            return;
        }
    }

    /**
     * Lê o payload da ação enviado via JSON ou formulário POST.
     */
    private function lerCorpoAcao(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }

        $bruto = file_get_contents("php://input");
        if ($bruto === false || trim($bruto) === "") {
            return [];
        }

        $json = json_decode($bruto, true);
        return is_array($json) ? $json : [];
    }
}
