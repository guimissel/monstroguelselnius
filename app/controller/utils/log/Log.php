<?php

/**
 * Uma constante da lista de flags permitidas
 */
const LISTA_FLAGS = ["FILE_USE_INCLUDE_PATH", "FILE_APPEND", "LOCK_EX"];

/**
 * Gerencia o log do sistema
 * 
 * @author Miguel Granville
 * @method static void EscreverArquivo (string $conteudo, string $arquivo, array $flags = [])
 * @method static bool CriarArquivo (string $nomeArquivo)
 */
class Log 
{
    /**
     * Escreve diretamente em um arquivo de log
     * 
     * @author Miguel Granville
     * @param string $conteudo Conteúdo a ser adicionado no arquivo
     * @param string $arquivo Caminho/nome do arquivo que será escrito
     * @param array $flags Flags permitidas para escrita (ex: ["FILE_APPEND", "LOCK_EX"])
     * @throws RuntimeException
     * @return void
     */
    public static function EscreverArquivo (string $conteudo, string $arquivo, array $flags = []): void
    {
        // lista de flags que vão ser inseridas
        $constantFlags = 0;
        
        // resgata as flags declaradas
        foreach ($flags as $chave)
        {
            $chaveFormatada = strtoupper($chave);

            // verifica se é uma flag valida
            if (!in_array($chaveFormatada, LISTA_FLAGS, true))
            {
                // trata a exception
                throw new RuntimeException("Foi declarada uma constante que não existe: {$chave}");
            }

            // resgata o valor da constante e usa ou bitwise
            $constantFlags |= constant($chaveFormatada);
        }

        // insere no arquivo o conteudo caso não seja falso
        if (file_put_contents($arquivo, $conteudo, $constantFlags) === false)
        {
            // trata a exception
            throw new RuntimeException("Não foi possível escrever no arquivo {$arquivo}");
        }
    }
}