<?php

require_once __DIR__ . "/../database/Conexao.php";
require_once __DIR__ . "/../model/Usuario.php";
require_once __DIR__ . "/../dao/UsuarioDAO.php";

class UsuarioController 
{
    public function criar () :void
    {
        // define que a resposta será JSON
        header("Content-Type: application/json");

        // verifica se todos os campos estão preenchidos
        foreach ($_POST as $chave => $valor) {
            if (trim((string) $valor) === '')
            {
                echo json_encode([
                    "status" => 400,
                    "mensagem" => "O campo \"" . $chave . "\" está vazio"
                ]);
                return;
            }    
        }

        // caso o campo usuario ultrapasse o tamanho
        if (strlen($_POST["usuario"]) > 50)
        {
            http_response_code(400);
            echo json_encode([
                "status"=>400,
                "mensagem"=>"O campo \"usuario\" ultrapassa o tamanho maximo"
            ]);
            return;
        }

        // caso o campo de senha ultrapasse o tamanho
        else if (strlen($_POST["senha"]) > 256)
        {
            http_response_code(400);
            echo json_encode([
                "status"=>400,
                "mensagem"=>"O campo \"senha\" ultrapassa o tamanho maximo"
            ]);
            return;
        }

        try 
        {
            // criar uma nova conexao ao banco
            $conexao = Conexao::getConexao();

            // variaveis para facilitar
            $nome = $_POST["usuario"];
            $senha = $_POST["senha"];
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

            // uma nova instancia de usuario
            $usuario = new Usuario(
                null,
                $nome,
                $senhaHash
            );

            // cria uma instancia DAO para utilizar o banco de dados
            $usuarioDAO = new UsuarioDAO($conexao);

            // cria o usuario
            $usuarioDAO->criar($usuario);

            // retorna sucesso ao usuario
            echo json_encode([
                "status" => 200,
                "mensagem" => "usuario criado com sucesso",
            ]);
            return;
        }

        catch (PDOException $e)
        {    
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel criar esse usuario"
            ]);
            return;
        }
    }
}