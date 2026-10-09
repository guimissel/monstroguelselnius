<?php

// requer a conexão do banco de dados
include __DIR__ . "/../database/connect.php";

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

// verifica se todos os campos estão preenchidos
foreach ($_POST as $chave => $valor) {
    if (empty($valor))
    {
        echo json_encode([
            "status" => 400,
            "mensagem" => "O campo \"" . $chave . "\" está vazio"
        ]);
        return;
    }    
}

try 
{
    // inicia uma transação
    $pdo->beginTransaction();

    // prepara o sql para ser executado
    $prepare = $pdo->prepare("INSERT INTO usuario (nome, senha) VALUES (:nome, :senha)");
    $nome = $_POST["usuario"];
    $senha = $_POST["senha"];
    $hash = password_hash($senha, PASSWORD_DEFAULT);

    // executa o sql
    $prepare->execute([
        "nome" => $_POST["usuario"],
        "senha" => $hash
    ]);

    // finaliza a transação
    $pdo->commit();

    // retorna sucesso ao usuario
    echo json_encode([
        "status" => 200,
        "mensagem" => "usuário criado com sucesso",
    ]);
    return;
}

catch (PDOException $e)
{
    $pdo->rollback();
    echo json_encode([
        "status" => 500,
        "mensagem" => "Não foi possivel criar esse usuário"
    ]);
    return;
}