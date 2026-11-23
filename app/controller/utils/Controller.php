<?php

// Base comum dos controladores, com fábrica para criar cada recurso conforme suas dependências.
/**
 * Classe base de todo controller
 * 
 * @author Miguel Granville
 * @method void Header (string $body, int $codigo = 0)
 * @method RespostaJson RespostaJson()
 */
class Controller
{
    // usa o trait que permite criar uma estancia usando um metodo static
    use NovaInstancia;

    /**
     * Constroi um header
     * 
     * @author Miguel Granville
     * @param string $body texto para o header
     * @param int $codigo codigo do header
     * @return void não retorna nada, pois, a função header é :void
     */
    public static function Header (string $body, int $codigo = 0): void
    {
        // caso o body não tenha sido definido
        if (!isset($body))
        {
            // nova exception
            throw new RuntimeException("Nenhum header foi definido", 500);
        }

        // seta o header
        header($body, $codigo);
    }

    /**
     * Controi uma resposta em json
     * 
     * @author Miguel Granville
     * @return RespostaJson retorna uma instancia da classe de resposta
     */
    public static function RespostaJson (): RespostaJson
    {
        // cria e retorna uma nova instancia de resposta json
        return RespostaJson::NovaInstancia();
    }
}
