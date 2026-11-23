<?php

// constante com os modos validos para 
const LISTA_MODOS = ["w", "w+", "r", "r+", "a", "a+", "x", "x+"];
const LISTA_FLAGS = ["FILE_USE_INCLUDE_PATH", "FILE_APPEND", "LOCK_EX"];
class Log 
{
    public static function EscreverEmArquivo (string $conteudo, string $arquivo, array $flags = []): void
    {
        // resgata as flags declaradas
        foreach($flags as $chave => $valor)
        {
            // verifica se é uma flag valida
            if (in_array($chave, LISTA_FLAGS))
            {
                
            }
        }

        file_put_contents()
    }

    public static function CriarArquivo (string $nomeArquivo, string $modo, bool $caminhoDoArquivo = false): void
    {
        // verifica se o arquivo existe já
        if (file_exists($nomeArquivo))
        {
            // trata o erro
            throw new RuntimeException("Já existe um arquivo com o nome declarado!");
            return;
        }

        // verifica se é um modo valido
        if (!in_array(strtolower($modo), LISTA_MODOS))
        {
            // trata o erro como generico
            throw new Exception("O modo delcarado não é válido");
            return;
        } 

        // cria o novo arquivo
        fopen($nomeArquivo, $modo, $caminhoDoArquivo);
    }
}
