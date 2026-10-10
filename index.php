<?php 
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/app/controller/AutenticacaoController.php";

// chamar roteador
require_once __DIR__ . '/routes/Router.php';

// chamar rotas da api
require_once __DIR__ . '/api/routes.php';

// caminho base para todas rotas
$caminhoBase = "/monstroguelselnius";
$caminhoApi = "/monstroguelselnius/";

// instancia um roteador
$roteador = new Roteador();

// adicionar rota /login
$roteador->get(
    "$caminhoBase/login", 
    function () {
        view("login");
    },
    [Acesso::VISITANTE], 
    "$caminhoBase/home"
);

$roteador->get(
    "$caminhoBase/signup", 
    function () {
        view("signup");
    },
    [Acesso::VISITANTE],
    "$caminhoBase/home"
);

$roteador->get(
    "$caminhoBase/home",
    function () {
        view("home");
    },
    [Acesso::USUARIO, Acesso::ADMIN],
    "$caminhoBase/login"
);

$roteador->get(
    "$caminhoBase/",
    function () {
        header("location: ".$caminhoBase."home");
    },
    [Acesso::USUARIO, Acesso::ADMIN],
    "$caminhoBase/login"
);

// criar rotas api
// numero de tipos de requisição (index)
$contagemTiposRequisicao = count(ROTAS_API);

// percorre todos tipos de requisição
for ($i = 0; $i < $contagemTiposRequisicao; $i++) 
{

    // resgata a chave da requisição
    $tipoRequisicao = array_keys(ROTAS_API)[$i];

    // verifica se o metodo existe antes de continuar
    if (!method_exists($roteador, $tipoRequisicao)) 
    {
        // pula para o próximo tipo de requisição
        continue;
    }

    // numero de rotas da requisição
    $contagemRotas = count(ROTAS_API[$tipoRequisicao]);

    // percorre todas rotas
    for ($j = 0; $j < $contagemRotas; $j++)
    {
        // caminho que sera usado como rota na rquisição
        $rota = array_keys(ROTAS_API[$tipoRequisicao])[$j];

        // cria uma nova rota
        $roteador->$tipoRequisicao(
            $caminhoApi . $rota, 
            ROTAS_API[$tipoRequisicao][$rota], 
            [Acesso::VISITANTE, Acesso::USUARIO, Acesso::ADMIN]
        );
    }
}

// despachar usuario para a rota
$roteador->despacharRota();