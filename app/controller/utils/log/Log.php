<?php

/**
 * Uma constante da lista de flags permitidas
 */
const LISTA_FLAGS = ["FILE_USE_INCLUDE_PATH", "FILE_APPEND", "LOCK_EX"];

/**
 * Gerencia o log do sistema
 * 
 * @author Miguel Granville
 * @method void EscreverArquivo (string $conteudo, string $arquivo, array $flags = [])
 * @method bool CriarArquivo (string $nomeArquivo)
 */
class Log 
{
    /**
     * Escreve em um arquivo
     * 
     * @author Miguel Granville
     * @return NovaEscrita
     */
    public static function EscreverArquivo (): NovaEscrita
    {
        // retorna uma nova escrita
        return NovaEscrita::NovaInstancia();
    }

    /**
     * Cria um novo arquivo no sistema
     * 
     * @author Miguel Granville
     * @param string $nomeArquivo nome do arquivo que será criado
     * @throws RuntimeException
     * @return bool retorna se foi um sucesso ou não
     */
    public static function CriarArquivo (string $nomeArquivo): bool
    {
        // verifica se o arquivo existe já
        if (file_exists($nomeArquivo))
        {
            // trata o erro
            return false;
        }

        // cria o novo arquivo
        return touch($nomeArquivo);
    }
}
