<?php 
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// enumeração para os caminhos de log
enum CaminhoLogs: string 
{
    case USUARIO = "./logs/usuario.log";
    case ERROS = "./logs/erros.log";
    case AUTH = "./logs/auth.log";
}

// carrega o autoload
require_once __DIR__ . "/autoload.php";

// chamar rotas da api
require_once __DIR__ . '/routes/ApiRoutes.php';

// caminho base para todas rotas
$caminhoBase = "/monstroguelselnius";
$caminhoApi = "/monstroguelselnius/";

// cria os arquivos de logs
Log::CriarArquivo(CaminhoLogs::USUARIO->value);
Log::CriarArquivo(CaminhoLogs::ERROS->value);
Log::CriarArquivo(CaminhoLogs::AUTH->value);

// instancia um roteador
$roteador = Roteador::NovaInstancia();

// adicionar rota /login
$roteador->NovaRota([
    "tipo" => "GET",
    "rota" => "$caminhoBase/login",
    "funcao" => function () { Roteador::View("login"); },
    "roles" =>  [Acesso::VISITANTE],
    "redirecionamento" => "$caminhoBase/home"
]);

$roteador->NovaRota([
    "tipo" => "GET",
    "rota" => "$caminhoBase/signup",
    "funcao" => function () { Roteador::View("signup"); },
    "roles" => [Acesso::VISITANTE],
    "redirecionamento" => "$caminhoBase/home"
]);

$roteador->NovaRota([
    "tipo" => "GET",
    "rota" => "$caminhoBase/jogo",
    "funcao" => function () use ($caminhoBase) {
        // sem equipe nao tem batalha: manda escolher a equipe primeiro
        try {
            $equipe = (EquipeDAO::NovaInstancia())->listar((int) ($_SESSION["id"] ?? 0));

            if (count($equipe) !== 3) {
                header("Location: $caminhoBase/equipe");
                exit;
            }
        } catch (Exception $e) {
            // sem as tabelas da equipe (app/database/schema.sql) o proprio jogo mostra o aviso
            error_log("rota jogo: " . $e->getMessage());
        }

        Roteador::View("jogo");
    },
    "roles" => [Acesso::USUARIO, Acesso::ADMIN],
    "redirecionamento" => "$caminhoBase/login"
]);

$roteador->NovaRota([
    "tipo" => "GET",
    "rota" => "$caminhoBase/inventario",
    "funcao" => function () { Roteador::View("inventario"); },
    "roles" => [Acesso::USUARIO, Acesso::ADMIN],
    "redirecionamento" => "$caminhoBase/login"
]);

$roteador->NovaRota([
    "tipo" => "GET",
    "rota" => "$caminhoBase/equipe",
    "funcao" => function () { Roteador::View("equipe"); },
    "roles" => [Acesso::USUARIO, Acesso::ADMIN],
    "redirecionamento" => "$caminhoBase/login"
]);

$roteador->NovaRota([
    "tipo" => "GET",
    "rota" => "$caminhoBase/perfil",
    "funcao" => function () { Roteador::View("perfil"); },
    "roles" => [Acesso::USUARIO, Acesso::ADMIN],
    "redirecionamento" => "$caminhoBase/login"
]);

$roteador->NovaRota([
    "tipo" => "GET",
    "rota" => "$caminhoBase/loja",
    "funcao" => function () { Roteador::View("loja"); },
    "roles" => [Acesso::USUARIO, Acesso::ADMIN],
    "redirecionamento" => "$caminhoBase/login"
]);

$roteador->NovaRota(
    [
        "tipo" => "GET",
        "rota" => "$caminhoBase/home",
        "funcao" => function () { Roteador::View("home"); },
        "roles" =>  [Acesso::USUARIO, Acesso::ADMIN],
        "redirecionamento" => "$caminhoBase/login"
    ]
);

$roteador->NovaRota([
    "tipo" => "GET",
    "rota" => "$caminhoBase/",
    "funcao" => function () use ($caminhoBase) { header("location: " . $caminhoBase . "/home"); },
    "roles" => [Acesso::USUARIO, Acesso::ADMIN],
    "redirecionamento" => "$caminhoBase/login"
]);

// criar rotas api que são livres
$contagemTiposRequisicao = count(ROTAS_API_NOAUTH);

for ($i = 0; $i < $contagemTiposRequisicao; $i++) 
{

    $tipoRequisicao = array_keys(ROTAS_API_NOAUTH)[$i];

    // numero de rotas da requisição
    $contagemRotas = count(ROTAS_API_NOAUTH[$tipoRequisicao]);

    for ($j = 0; $j < $contagemRotas; $j++)
    {
        $rota = array_keys(ROTAS_API_NOAUTH[$tipoRequisicao])[$j];

        $roteador->NovaRota([
            "tipo" => $tipoRequisicao,
            "rota" => $caminhoApi . $rota,
            "funcao" => ROTAS_API_NOAUTH[$tipoRequisicao][$rota],
            "roles" => [Acesso::VISITANTE, Acesso::USUARIO, Acesso::ADMIN],
            "redirecionamento" => "$caminhoBase/login"
        ]);
    }
}

// criar rotas api que são privadas [Acesso::USUARIO, Acesso::ADMIN]
$contagemTiposRequisicao = count(ROTAS_API_NEEDAUTH);

for ($i = 0; $i < $contagemTiposRequisicao; $i++) 
{
    $tipoRequisicao = array_keys(ROTAS_API_NEEDAUTH)[$i];

    // numero de rotas da requisição
    $contagemRotas = count(ROTAS_API_NEEDAUTH[$tipoRequisicao]);

    for ($j = 0; $j < $contagemRotas; $j++)
    {
        $rota = array_keys(ROTAS_API_NEEDAUTH[$tipoRequisicao])[$j];

        $roteador->NovaRota([
            "tipo" => $tipoRequisicao,
            "rota" => $caminhoApi . $rota,
            "funcao" => ROTAS_API_NEEDAUTH[$tipoRequisicao][$rota],
            "roles" => [Acesso::USUARIO, Acesso::ADMIN],
            "redirecionamento" => "$caminhoBase/login"
        ]);
    }
}

// despachar usuario para a rota
$roteador->DespacharRota();