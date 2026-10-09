<?php

$usuario = "root";
$senha = "";
$conexao = "mysql:host=localhost; dbname=monstroguelselnius; charset=utf8;";

try 
{
    // instancia um novo objeto pdo
    $pdo = new PDO($conexao, $usuario, $senha);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return;
} 

// erro no banco de dados
catch (PDOException $e) 
{
    // retorna um erro ao usuario
    http_response_code(500);
    echo json_encode([
        "status" => 500,
        "mensagem" => "Erro na conexão com o banco"
    ]);
    exit;
}