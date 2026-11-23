<?php

/**
 * Uma classe para contruir responses json
 * 
 * @author Miguel Granville
 * @method self status (int $status)
 * @method self sucesso (bool $sucesso = true)
 * @method self mensagem(string $mensagem)
 * @method self adicionar(string $chave, mixed $valor)
 * @method string construir()
 * @method void enviar()
 */
class RespostaJson
{
    // usa o trait de criar instancias com ::NovaInstancia()
    use NovaInstancia;
    
    /**
     * @var array $dados guarda os dados da resposta
     */
    private array $dados = [];
    /**
     * @var int $status guarda o status code da resposta (default = 200)
     */
    private int $status = 200;

    /**
     * @author Miguel Granville
     * @param int $status status do http code da resposta json
     * @return self serve para criar um metodo de encadeamento
     */
    public function status(int $status): self
    {
        // definindo na propriedade privada
        $this->status = $status;

        // definindo a chave status
        $this->dados["status"] = $status;

        // return self
        return $this;
    }

    /**
     * @author Miguel Granville
     * @param string $mensagem uma mensagem de resposta para facilitar entendimento
     * @return self serve para criar um metodo de encadeamento
     */
    public function mensagem(string $mensagem): self
    {
        // definindo em dados uma chave mensagem
        $this->dados["mensagem"] = $mensagem;

        // return self
        return $this;
    }

    /**
     * Adiciona um chave e valor personalizada na resposta json
     * 
     * @author Miguel Granville
     * @param string $chave chave da resposta do json
     * @param mixed $valor valor da chave no json
     * @return self serve para criar um metodo de encadeamento
     */
    public function adicionar(string $chave, mixed $valor): self
    {
        // cria a chave com o valor personalizado na lista de dados
        $this->dados[$chave] = $valor;

        // return self
        return $this;
    }

    /**
     * Construir a resposta em uma string json
     * 
     * @author Miguel Granville
     * @return string retorna a string montando o json
     */
    public function construir(): string
    {
        // tenta construir a string json
        try {
            // monta a string do json
            $jsonDeResposta = json_encode(
                $this->dados,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            );

            // retorna a string
            return $jsonDeResposta;
        } 
        
        // trata um exception para caso não consiga construir
        catch (JsonException $jE) {

            // retorna a mensagem de erro
            error_log(
                "Ocorreu um erro ao tentar montar a string de resposta json: " . $jE->getMessage(),
                $jE->getCode()
            );

            // trata a exceção
            throw $jE;
        }
    }

    /**
     * Envia a resposta json
     * @author Miguel Granville
     * @return void
     */
    public function enviar(): void
    {
        // definindo o http code (status)
        http_response_code($this->status);

        // definindo o conteudo como json
        Controller::Header("Content-Type: application/json; charset=utf-8");

        // constroi o json e printa
        echo $this->Construir();

        // fim
        exit;
    }
}