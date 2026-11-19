<?php

// não remova esta linha!
require_once __DIR__ . "/../app/controller/AutenticacaoController.php";                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 //manuela reis

class Roteador
{
    use NovaInstancia;

    // array das rotas
    private array $rotas = [];

    // array dos redirecionamento (são efetuados caso ocorra um 403 - forbidden)
    private array $redirecionamentos = [];
    private array $rolesPermitidos = [];

    // adiciona uma rota
    public function NovaRota(array $Argumentos): void
    {
        // resgata valores do array de argumentos
        $tipoDeRota = $Argumentos["tipo"];
        $rota = $Argumentos["rota"];
        $funcaoDaRota = $Argumentos["funcao"];
        $redirecionamentoDaRota = $Argumentos["redirecionamento"];
        $rolesDaRota = $Argumentos["roles"];

        // implementa a rota em um array associativo
        // definindo a função da rota
        $this->rotas[$tipoDeRota][$rota] = $funcaoDaRota;

        // cargos permitidos para acessar a rota
        $this->rolesPermitidos[$tipoDeRota][$rota] = $rolesDaRota;

        // definindo o redirecionamento caso a role não seja permitida
        $this->redirecionamentos[$tipoDeRota][$rota] = $redirecionamentoDaRota;
    }

    // despachar para uma rota
    public function DespacharRota(): void
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
            Roteador::View("404", 404);
            return;
        }

        $redirecionamento = $this->redirecionamentos[$metodo][$rotaEncontrada] ?? null;
        $rolesPermitidos = $this->rolesPermitidos[$metodo][$rotaEncontrada] ?? [];

        // instancia de autenticacao
        $autenticacao = AutenticacaoController::NovaInstancia([
            "restricaoAcesso" => $rolesPermitidos,
            "rota" => $caminho,
            "redirecionar" => $redirecionamento
        ]);
        
        $retornoAutenticacao = json_decode($autenticacao->acessar(), true);
        
        if ($retornoAutenticacao["status"] != 200) {
            http_response_code($retornoAutenticacao["status"]);
            header("location: ".$retornoAutenticacao["redirecione"]);
            return;
        };

        // executa a função
        call_user_func_array($funcaoDaRota, $parametros);
    }

    // função para arquivos html
    public static function View(string $nome, int $httpCode = 200): void
    {    
        // caminho do arquivo
        $caminho = __DIR__ . "/../public/pages/" . $nome . ".html";
        
        if(!file_exists($caminho)) {
            echo "View não foi encontrada: " . $caminho;
            return;
        }
            
        http_response_code($httpCode);
        readfile($caminho);
    }

}
