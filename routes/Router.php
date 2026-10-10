<?php
require_once __DIR__ . "/../app/controller/AutenticacaoController.php";

class Roteador
{
    // array das rotas
    private array $rotas = [];

    // array dos redirecionamento (são efetuados caso ocorra um 403 - forbidden)
    private array $redirecionamentos = [];
    private array $rolesPermitidos = [];

    // adiciona uma rota do tipo GET
    public function get(
        string $caminho, 
        callable $funcao, 
        array $rolesPermitidos, 
        string $redirecionamento = "/monstroguelselnius/login"): void
    {
        // adiciona ao array associativo
        $this->rotas["GET"][$caminho] = $funcao;
        $this->redirecionamentos["GET"][$caminho] = $redirecionamento;
        $this->rolesPermitidos["GET"][$caminho] = $rolesPermitidos;
    }

    // adiciona uma rota do tipo POST
    public function post(
        string $caminho, 
        callable $funcao, 
        array $rolesPermitidos,
        string $redirecionamento = "/monstroguelselnius/login"): void
    {
        // adiciona ao array associativo
        $this->rotas["POST"][$caminho] = $funcao;
        $this->redirecionamentos["POST"][$caminho] = $redirecionamento;
        $this->rolesPermitidos["POST"][$caminho] = $rolesPermitidos;
    }

    // adiciona uma rota do tipo DELETE
    public function delete(
        string $caminho, 
        callable $funcao, 
        array $rolesPermitidos,
        string $redirecionamento = "/monstroguelselnius/login"): void
    {
        // adiciona ao array associativo
        $this->rotas["DELETE"][$caminho] = $funcao;
        $this->redirecionamentos["DELETE"][$caminho] = $redirecionamento;
        $this->rolesPermitidos["DELETE"][$caminho] = $rolesPermitidos;
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
        $redirecionamento = $this->redirecionamentos[$metodo][$caminho];
        $rolesPermitidos = $this->rolesPermitidos[$metodo][$caminho];

        // instancia de autenticacao
        $autenticacao = new AutenticacaoController($rolesPermitidos, $caminho, $redirecionamento);
        $retornoAutenticacao = json_decode($autenticacao->acessar(), true);
        
        if ($retornoAutenticacao["status"] != 200) {
            http_response_code($retornoAutenticacao["status"]);
            header("location: ".$retornoAutenticacao["redirecione"]);
            return;
        };
        
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