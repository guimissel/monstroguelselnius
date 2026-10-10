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