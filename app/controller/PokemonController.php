<?php

/**
 * Controla as ações e exibições do catálogo de Pokémons
 * 
 * @author Miguel Granville / Vinicius Grub
 * @method void listar()
 */
class PokemonController extends Controller
{
    /**
     * Lista o catalogo completo de Pokemons.
     * 
     * @author Miguel Granville / Vinicius Grub
     * @return void
     */
    public function listar(): void
    {
        try
        {
            $pokemons = (PokemonDAO::NovaInstancia())->todos();

            Controller::RespostaJson()
                ->status(200)
                ->adicionar("pokemons", array_map(fn (Pokemon $p) => $p->paraArray(), $pokemons))
                ->enviar();
            return;
        }
        catch (Exception $e)
        {
            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [ERRO] PokemonController::listar: " . $e->getMessage() . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Nao foi possivel carregar os pokemons")
                ->enviar();
            return;
        }
    }
}