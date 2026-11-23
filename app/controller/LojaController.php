<?php

/**
 * Controla as operações relacionadas à loja de itens
 * 
 * @author Miguel Granville / Vinicius Grub
 * @method void listar()
 * @method void comprar(string $chave)
 */
class LojaController extends Controller
{
    private const MAXIMO_POR_COMPRA = 99;

    /**
     * Lista os itens disponíveis e o saldo do jogador.
     * 
     * @author Miguel Granville / Vinicius Grub
     * @return void
     */
    public function listar(): void
    {
        $meuId = $_SESSION["id"] ?? null;
        
        // A loja apresenta o saldo e o catálogo específicos da conta autenticada.
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
            $conexao = Conexao::getConexao();
            $lojaDAO = LojaDAO::NovaInstancia();
            $itens = [];

            // Converte os objetos do catálogo para estruturas que possam ser serializadas em JSON.
            foreach ($lojaDAO->listar((int) $meuId) as $item) 
            {
                $itens[] = $item->paraArray();
            }

            $inventarioDAO = InventarioDAO::NovaInstancia();
            $pokedolares = $inventarioDAO->pokedolares((int) $meuId);

            Controller::RespostaJson()
                ->status(200)
                ->adicionar("pokedolares", $pokedolares)
                ->adicionar("itens", $itens)
                ->enviar();
            return;
        }
        catch (Exception $e)
        {
            // O detalhe da falha fica no log para não vazar configuração ou dados internos.
            error_log("LojaController::listar: " . $e->getMessage());

            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Nao foi possivel carregar a loja")
                ->enviar();
            return;
        }
    }

    /**
     * Compra uma quantidade de um item da loja.
     * 
     * @author Miguel Granville / Vinicius Grub
     * @param string $chave O identificador do item a ser comprado
     * @return void
     */
    public function comprar(string $chave): void
    {
        $meuId = $_SESSION["id"] ?? null;
        
        // Impede compras associadas a uma sessão sem jogador identificado.
        if (!isset($meuId))
        {
            Controller::RespostaJson()
                ->status(401)
                ->mensagem("usuario nao foi identificado")
                ->enviar();
            return;
        }

        $quantidade = filter_var($_POST["quantidade"] ?? 1, FILTER_VALIDATE_INT);
        
        $quantidadeInvalida = $quantidade > self::MAXIMO_POR_COMPRA;
        $quantidadeNegativa = $quantidade < 1;
        $quantidadeNula = $quantidade === false;

        // Limita a quantidade solicitada para manter a operação dentro das regras da loja.
        if ($quantidadeInvalida || $quantidadeNegativa || $quantidadeNula)
        {
            Controller::RespostaJson()
                ->status(400)
                ->mensagem("Quantidade invalida (de 1 a " . self::MAXIMO_POR_COMPRA . ")")
                ->enviar();
            return;
        }

        try
        {
            // O DAO verifica estoque e saldo e realiza a compra como uma única operação.
            $lojaDAO = LojaDAO::NovaInstancia();
            $resultado = $lojaDAO->comprar((int) $meuId, $chave, $quantidade);

            Controller::RespostaJson()
                ->status(200)
                ->mensagem("compra realizada")
                ->adicionar("pokedolares", $resultado["pokedolares"])
                ->adicionar("quantidade", $resultado["quantidade"])
                ->enviar();
            return;
        }
        catch (DomainException $e)
        {
            // Expõe recusas previstas, como falta de saldo, com o status correspondente.
            Controller::RespostaJson()
                ->status($e->getCode())
                ->mensagem($e->getMessage())
                ->enviar();
            return;
        }
        catch (Exception $e)
        {
            // Registra a causa técnica sem repassá-la como parte da resposta ao jogador.
            error_log("LojaController::comprar: " . $e->getMessage());

            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Nao foi possivel concluir a compra")
                ->enviar();
            return;
        }
    }
}