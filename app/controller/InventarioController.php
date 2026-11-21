<?php

/**
 * Controla as ações relacionadas ao inventário do jogador
 * 
 * @author Miguel Granville / Vinicius Grub
 * @method void listar()
 * @method void usar(string $chave)
 */
class InventarioController extends Controller
{
    /**
     * Lista os itens e o saldo do jogador.
     * 
     * @author Miguel Granville / Vinicius Grub
     * @return void
     */
    public function listar(): void
    {
        // id do usuario
        $meuId = $_SESSION["id"] ?? null;
        
        // Não consulta nem expõe o inventário de uma sessão sem jogador autenticado.
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
            $inventarioDAO = InventarioDAO::NovaInstancia();
            $itens = [];
            
            // Serializa cada item mantendo o formato usado pelas telas do inventário.
            foreach ($inventarioDAO->listar((int) $meuId) as $item) 
            {
                $itens[] = $item->paraArray();
            }

            Controller::RespostaJson()
                ->status(200)
                ->adicionar("pokedolares", $inventarioDAO->pokedolares((int) $meuId))
                ->adicionar("itens", $itens)
                ->enviar();
            return;
        }
        catch (Exception $e)
        {
            // Mantém o detalhe operacional no log e uma mensagem segura para o cliente.
            error_log("InventarioController::listar: " . $e->getMessage());

            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Nao foi possivel carregar o inventario")
                ->enviar();
            return;
        }
    }

    /**
     * Usa ou abre o item solicitado.
     * 
     * @author Miguel Granville / Vinicius Grub
     * @param string $chave Identificador do item a ser usado
     * @return void
     */
    public function usar(string $chave): void
    {
        $meuId = $_SESSION["id"] ?? null;
        
        // O consumo de itens altera recursos do jogador e exige uma sessão identificada.
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
            $inventarioDAO = InventarioDAO::NovaInstancia();

            // Pokébolas concedem um Pokémon, portanto seguem o fluxo específico de abertura.
            if ($chave === "pokebola") 
            {
                $resultado = $inventarioDAO->abrirPokebola((int) $meuId);
                
                Controller::RespostaJson()
                    ->status(200)
                    ->mensagem("Pokebola aberta")
                    ->adicionar("pokemon", $resultado["pokemon"])
                    ->adicionar("quantidade", $resultado["quantidade"])
                    ->enviar();
                return;
            }

            // O DAO informa se o jogador não possui o item solicitado.
            if (!$inventarioDAO->usar((int) $meuId, $chave))
            {
                Controller::RespostaJson()
                    ->status(409)
                    ->mensagem("Voce nao tem esse item")
                    ->enviar();
                return;
            }

            Controller::RespostaJson()
                ->status(200)
                ->mensagem("item usado")
                ->enviar();
            return;
        }
        catch (DomainException $e)
        {
            // Retorna erros esperados de uso do item com o status indicado pela regra.
            Controller::RespostaJson()
                ->status($e->getCode())
                ->mensagem($e->getMessage())
                ->enviar();
            return;
        }
        catch (Exception $e)
        {
            // Evita expor detalhes internos em falhas inesperadas do inventário.
            error_log("InventarioController::usar: " . $e->getMessage());

            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Nao foi possivel usar o item")
                ->enviar();
            return;
        }
    }
}