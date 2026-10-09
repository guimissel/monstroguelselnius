<?php

include __DIR__ . "/../database/connect.php";

// define que a resposta será JSON
header("Content-Type: application/json");

// caso o metodo seja invalido
if($_SERVER["REQUEST_METHOD"] != "POST")
{
    http_response_code(405);
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

// inicia uma transação
$pdo->beginTransaction();

// query para ser executada no banco
$query = "SELECT nome, id_usuario FROM usuario WHERE nome = :nome";

try 
{
    $prepare = $pdo->prepare($query);
    $nome = $_POST["usuario"];
    
    // executa a query no banco
    $prepare->execute([
        "nome" => $nome
    ]);

    // retorno do usuario
    $usuario = $prepare->fetch(PDO::FETCH_ASSOC);

    // Verifica se encontrou o usuário
    if (!$usuario)
    {
        http_response_code(404);

        echo json_encode([
            "status" => 404,
            "mensagem" => "Usuário não encontrado"
        ]);

        return;
    }

    $pdo->commit();

    // Retorna os dados do usuário
    echo json_encode([
        "status" => 200,
        "usuario" => $usuario
    ]);
    return;
} 

catch (PDOException $e) 
{
    http_response_code(500);
    echo json_encode([
        "status" => 500,
        "mensagem" => "Erro interno no servidor"
    ]);
    return;
}