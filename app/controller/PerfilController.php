<?php

/**
 * Controla a exibição das informações e estatísticas do perfil do jogador
 * 
 * @author Miguel Granville / Vinicius Grub
 * @method void ver()
 */
class PerfilController extends Controller
{
    /**
     * Retorna os dados e o progresso do jogador autenticado.
     * 
     * @author Miguel Granville / Vinicius Grub
     * @return void
     */
    public function ver(): void
    {
        $meuId = $_SESSION["id"] ?? null;
        
        // Todos os dados reunidos abaixo são restritos ao jogador autenticado.
        if (!isset($meuId))
        {
            Controller::RespostaJson()
                ->status(401)
                ->mensagem("usuario nao foi identificado")
                ->enviar();
            return;
        }

        try
        {
            $dao = PerfilDAO::NovaInstancia();
            $treinador = $dao->treinador((int) $meuId);

            // Um treinador ausente não pode gerar placar ou histórico coerentes.
            if ($treinador === null)
            {
                Controller::RespostaJson()
                    ->status(404)
                    ->mensagem("Jogador nao encontrado")
                    ->enviar();
                return;
            }

            $placar = $dao->placar((int) $meuId);

            Controller::RespostaJson()
                ->status(200)
                ->adicionar("treinador", $treinador->paraArray())
                ->adicionar("placar", $placar)
                ->adicionar("posicao", $dao->posicao($placar["vitorias"]))
                ->adicionar("ranking", $dao->ranking())
                ->adicionar("historico", $dao->historico((int) $meuId))
                ->adicionar("colecao", $dao->colecao((int) $meuId))
                ->adicionar("itens", $dao->itens((int) $meuId))
                ->enviar();
            return;
        }
        catch (Exception $e)
        {
            // O log preserva o diagnóstico; a resposta orienta sobre a dependência do esquema.
            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [ERRO] PerfilController::ver: " . $e->getMessage() . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Nao foi possivel carregar o perfil. Confira se o app/database/schema.sql foi executado.")
                ->enviar();
            return;
        }
    }
}