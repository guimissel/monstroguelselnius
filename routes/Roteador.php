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

    // adiciona uma rota do tipo PATCH
    public function patch(
        string $caminho, 
        callable $funcao, 
        array $rolesPermitidos,
        string $redirecionamento = "/monstroguelselnius/login"): void
    {
        // adiciona ao array associativo
        $this->rotas["PATCH"][$caminho] = $funcao;
        $this->redirecionamentos["PATCH"][$caminho] = $redirecionamento;
        $this->rolesPermitidos["PATCH"][$caminho] = $rolesPermitidos;
    }

    // despachar para uma rota
    public function despacharRota(): void
    {
        // metodo da requisição
        $metodo = $_SERVER["REQUEST_METHOD"];

        // caminho da requisição
        $caminho = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

        $funcaoDaRota = null;
        $rotaEncontrada = null;
        $parametros = [];

        // Percorre as rotas do método HTTP solicitado
        foreach ($this->rotas[$metodo] ?? [] as $rota => $funcao) {

            $regex = preg_replace('/\{\w+\}/', '([^/]+)', $rota);

            // Garante correspondência com o caminho completo
            $regex = '#^' . $regex . '$#';

            if (preg_match($regex, $caminho, $matches)) {
                $funcaoDaRota = $funcao;
                $rotaEncontrada = $rota;

                // Remove a correspondência completa
                array_shift($matches);

                $parametros = array_values($matches);

                break;
            }
        }
        
        // rota não encontrada 404
        if ($rotaEncontrada === null) {
            http_response_code(404);
            echo '404 - Rota não encontrada';
            return;
        }

        $redirecionamento = $this->redirecionamentos[$metodo][$rotaEncontrada] ?? null;
        $rolesPermitidos = $this->rolesPermitidos[$metodo][$rotaEncontrada] ?? [];

        // instancia de autenticacao
        $autenticacao = new AutenticacaoController($rolesPermitidos, $caminho, $redirecionamento);
        $retornoAutenticacao = json_decode($autenticacao->acessar(), true);
        
        if ($retornoAutenticacao["status"] != 200) {
            http_response_code($retornoAutenticacao["status"]);
            header("location: ".$retornoAutenticacao["redirecione"]);
            return;
        };

        // executa a função
        call_user_func_array($funcaoDaRota, $parametros);
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