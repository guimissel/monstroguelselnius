<?php


require_once __DIR__ . "/AutenticacaoController.php";
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
                http_response_code(400);
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
            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel criar esse usuario"
            ]);
            return;
        }

        catch (Exception $e)
        {
            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Erro interno! tente novamente mais tarde"
            ]);
            return;
        }
    }

    public function entrar () :void
    {
        // define que a resposta será JSON
        header("Content-Type: application/json");

        // verifica se todos os campos estão preenchidos
        foreach ($_POST as $chave => $valor) {
            if (trim((string) $valor) === '')
            {
                http_response_code(400);
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
            $conexao = Conexao::getConexao();
            $nome = $_POST["usuario"];
            $senha = $_POST["senha"];

            $usuarioDAO = new UsuarioDAO($conexao);
            $usuario = $usuarioDAO->buscar(TipoBusca::NOME, $nome);

            // caso não exista o usuario
            if (!isset($usuario)) 
            {
                http_response_code(401);
                // retorna sucesso ao usuario
                echo json_encode([
                    "status" => 401,
                    "mensagem" => "usuario ou senha incorretos"
                ]);
                return;
            }

            // caso senha incorreta
            else if (!password_verify($senha, $usuario->getSenhaHash()))
            {
                http_response_code(401);
                // retorna sucesso ao usuario
                echo json_encode([
                    "status" => 401,
                    "mensagem" => "usuario ou senha incorretos"
                ]);
                return;
            }

            $_SESSION["id"] = $usuario->getId();
            $_SESSION["role"] = Acesso::USUARIO->value;

            // retorna sucesso ao usuario
            echo json_encode([
                "status" => 200,
                "mensagem" => "acesso liberado com sucesso",
            ]);
            return;
        }

        catch (PDOException $e)
        {    
            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel criar esse usuario"
            ]);
            return;
        }

        catch (Exception $e)
        {
            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Erro interno! tente novamente mais tarde"
            ]);
            return;
        }
    }

    public function eu () :void
    {
        $conexao = Conexao::getConexao();
        $meuId = $_SESSION["id"];

        // define que a resposta será JSON
        header("Content-Type: application/json");

        if (!isset($meuId))
        {
            http_response_code(401);
            echo json_encode([
                "status"=> 401,
                "mensagem"=> "usuario nao foi identificado"
            ]);
            return;
        }

        $usuarioDAO = new UsuarioDAO($conexao);
        $meuUsuario = $usuarioDAO->buscar(TipoBusca::ID, $meuId);
        $role = $_SESSION["role"];

        echo json_encode([
            "usuarioExiste"=>true,
            "id"=>$meuUsuario->getId(),
            "nome"=>$meuUsuario->getUsuario(),
            "role"=>$role
        ]);
        return;
    }

    public function sair () :void
    {
         // define que a resposta será JSON
        header("Content-Type: application/json");

        $_SESSION["id"] = null;
        $_SESSION["role"] = Acesso::VISITANTE->value;
        
        echo json_encode([
            "status"=>200,
            "mensagem"=>"usuario saiu de sua conta",
            "refresh"=>true
        ]);
        return;
    }
}