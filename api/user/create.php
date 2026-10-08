<?php

// requer a conexão do banco de dados
require_once __DIR__ . "/../database/connect.php";

// define que a resposta será JSON
header("Content-Type: application/json");

// se o metodo de requisição não for post
if ($_SERVER["REQUEST_METHOD"] != "POST")
{
    echo json_encode([
        "status" => 405,
        "mensagem" => "Método inválido para esta rota"
    ]);
    return;
}

foreach ($_POST as $chave => $valor) {
    if (empty($valor))
    {
        echo json_encode([
            "status" => 400,
            "mensagem" => "O campo " . $chave . " esta vazio"
        ]);
        return;
    }    
}

echo json_encode([
    "status" => 200,
    "mensagem" => $_POST["usuario"] . $_POST["senha"]
]);
return;