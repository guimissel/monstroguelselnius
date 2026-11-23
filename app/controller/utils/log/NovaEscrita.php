<?php

class NovaEscrita
{
    // permite que construa uma instancia pela função static
    use NovaInstancia;

    // uma variavel privada para os dados do arquivo
    private $dados = [];
    
    /**
     * Permite resgatar os dados
     * 
     * @author Miguel Granville
     * @return array retorna a lista de dados
     */
    public function getDados (): array
    {
        return $this->dados;
    }

    /**
     * Defina o conteudo do arquivo
     * 
     * @author Miguel Granville
     * @param string $conteudoDoArquivo recebe o conteudo a ser adicionado no arquivo
     * @return self retornou ele mesmo para o cascade
     */
    public function conteudo (string $conteudoDoArquivo): self
    {
        $this->dados["conteudo"] = $conteudoDoArquivo;
        return $this;
    }

    /**
     * Define o nome do arquivo a ser escrito
     * 
     * @author Miguel Granville
     * @param string $nomeDoArquivo nome do arquivo que será escrito
     * @return self retorna ele mesmo para o cascade
     */
    public function arquivo (string $nomeDoArquivo): self
    {
        $this->dados["arquivo"] = $nomeDoArquivo;
        return $this;
    }

    /**
     * Define os 'flags' da escrita
     * 
     * @author Miguel Granville
     * @param array $flagsDeclaradas flags que serão adicionadas
     * @return self retorna ele mesmo para o cascade
     */
    public function funcoes (array $flagsDeclaradas): self
    {
        $this->dados["flags"] = $flagsDeclaradas;
        return $this;
    }

    /**
     * Escreve finalizando o cascade
     * 
     * @author Miguel Granville
     * @return void
     */
    public function escrever (): void
    {
        // resgata a lista de dados
        $dados = $this->getDados();

        // pegando valores
        $flags = $dados["flags"];
        $arquivo = $dados["arquivo"];
        $conteudo = $dados["conteudo"];

        // lista de flags que vão ser inseridas
        $constantFlags = 0;
        
        // resgata as flags declaradas
        foreach($flags as $chave)
        {
            // verifica se é uma flag valida
            if (!in_array(strtoupper($chave), LISTA_FLAGS, true))
            {
                // trata a exception
                throw new RuntimeException("Foi declarado um constante que não existe");
            }

            // resgata o valor da constante e usa ou bitwise
            $constantFlags |= constant($chave);
        }

        // insere no arquivo o conteudo caso não seja falso isso
        // retorna os bytes do arquivo caso consiga inserir o conteudo
        // esse valor é inutil mas fazer o que ne :)
        if(file_put_contents($arquivo, $conteudo, $constantFlags) === false)
        {
            // trata a exception
            throw new RuntimeException("Não foi possivel escrever no arquivo $arquivo");
        }
    }
}