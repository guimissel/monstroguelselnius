<?php

class Roteador
{
    // array das rotas
    private array $rotas = [];

    // adiciona uma rota do tipo GET
    public function get(string $caminho, callable $funcao): void
    {
        // adiciona ao array associativo
        $this->rotas["GET"][$caminho] = $funcao;
    }

    // adiciona uma rota do tipo POST
    public function post(string $caminho, callable $funcao): void
    {
        // adiciona ao array associativo
        $this->rotas["POST"][$caminho] = $funcao;
    }

    // despachar para uma rota
    public function despacharRota(): void
    {
        // metodo da requisição
        $metodo = $_SERVER["REQUEST_METHOD"];

        // caminho da requisição
        $caminho = $_SERVER["REQUEST_URI"];

        // se a rota não for definida
        // retorne 404
        if (!isset($this->rotas[$metodo][$caminho])) {
            http_response_code(404);
            echo '404 - Rota não encontrada';
            return;
        }

        // função que deve executada pela rota
        $funcaoDaRota = $this->rotas[$metodo][$caminho];

        // executa a função
        call_user_func($funcaoDaRota);
    }
}

// função para arquivos html
function view(string $nome): void
{    
    // caminho do arquivo
    $caminho = __DIR__ . "/../public/pages/" . $nome . ".html";

    if(!file_exists($caminho)) {
        http_response_code(404);
        echo "View não foi encontrada: " . $caminho;
        return;
    }

    readfile($caminho);
}