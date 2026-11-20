<?php
class UsuarioController extends Controller
{
    // Cria uma nova conta.
    public function criar () :void
    {
        header("Content-Type: application/json");

        // Impede que campos obrigatórios vazios cheguem às regras de criação da conta.
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

        // Mantém os limites compatíveis com o armazenamento do nome e da senha.
        if (strlen($_POST["usuario"]) > 50)
        {
            http_response_code(400);
            echo json_encode([
                "status"=>400,
                "mensagem"=>"O campo \"usuario\" ultrapassa o tamanho maximo"
            ]);
            return;
        }

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
            // Armazena somente o hash da senha e inicia a sessão após a persistência da conta.
            $conexao = Conexao::getConexao();

            $nome = $_POST["usuario"];
            $senha = $_POST["senha"];
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

            $usuario = new Usuario(
                -1,
                //  Informa o parâmetro $nome nesta assinatura ou chamada.
                $nome,
                $senhaHash
            );

            $usuarioDAO = UsuarioDAO::NovaInstancia();

            $usuario = $usuarioDAO->criarUsuario($usuario);
            
            //  Armazena $usuario->getId() na posição $_SESSION["id"].
            $_SESSION["id"] = $usuario->getId();
            //  Armazena Acesso::USUARIO->value na posição $_SESSION["role"].
            $_SESSION["role"] = Acesso::USUARIO->value;

            echo json_encode([
                "status" => 200,
                "mensagem" => "usuario criado com sucesso",
                "refresh"=>true
            ]);
            return;
        }

        catch (DomainException $e)
        {
            // Problemas esperados de domínio mantêm seu status e mensagem específicos.
            http_response_code($e->getCode());
            echo json_encode([
                "status" => $e->getCode(),
                "mensagem" => $e->getMessage()
            ]);
            return;
        }

        catch (PDOException $e)
        {    
            // Erros de banco são diferenciados dos demais erros internos da aplicação.
            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel criar esse usuario: " . $e->getMessage()
            ]);
            return;
        }

        catch (Exception $e)
        {
            // Não expõe detalhes de exceções inesperadas durante o cadastro.
            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Erro interno! tente novamente mais tarde"
            ]);
            return;
        }
    }

    // Autentica o jogador.
    public function entrar () :void
    {
        header("Content-Type: application/json");

        // Exige os campos do formulário antes de consultar as credenciais.
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

        // Rejeita entradas acima dos limites conhecidos pelo cadastro.
        if (strlen($_POST["usuario"]) > 50)
        {
            http_response_code(400);
            echo json_encode([
                "status"=>400,
                "mensagem"=>"O campo \"usuario\" ultrapassa o tamanho maximo"
            ]);
            return;
        }

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
            // Busca pela identificação e compara a senha com o hash armazenado.
            $conexao = Conexao::getConexao();
            $nome = $_POST["usuario"];
            $senha = $_POST["senha"];

            $usuarioDAO = UsuarioDAO::NovaInstancia();
            $usuario = $usuarioDAO->buscarUsuario(TipoBusca::NOME, $nome);

            // Usa uma resposta única para usuário inexistente e senha incorreta.
            if (!isset($usuario)) 
            {
                http_response_code(401);
                echo json_encode([
                    "status" => 401,
                    "mensagem" => "usuario ou senha incorretos"
                ]);
                return;
            }

            else if (!password_verify($senha, $usuario->getSenhaHash()))
            {
                http_response_code(401);
                echo json_encode([
                    "status" => 401,
                    "mensagem" => "usuario ou senha incorretos"
                ]);
                return;
            }

            //  Armazena $usuario->getId() na posição $_SESSION["id"].
            $_SESSION["id"] = $usuario->getId();
            //  Armazena Acesso::USUARIO->value na posição $_SESSION["role"].
            $_SESSION["role"] = Acesso::USUARIO->value;

            echo json_encode([
                "status" => 200,
                "mensagem" => "acesso liberado com sucesso",
            ]);
            return;
        }

        catch (PDOException $e)
        {    
            // Falhas de persistência não são confundidas com credenciais inválidas.
            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel criar esse usuario"
            ]);
            return;
        }

        catch (Exception $e)
        {
            // Oculta detalhes de falhas internas da autenticação.
            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Erro interno! tente novamente mais tarde"
            ]);
            return;
        }
    }

    // Retorna o jogador autenticado.
    public function eu () :void
    {
        $conexao = Conexao::getConexao();
        $meuId = $_SESSION["id"] ?? null;

        header("Content-Type: application/json");

        // Sem ID de sessão, não há identidade para consultar ou devolver.
        if (!isset($meuId))
        {
            http_response_code(401);
            echo json_encode([
                "status"=> 401,
                "mensagem"=> "usuario nao foi identificado"
            ]);
            return;
        }

        $usuarioDAO = UsuarioDAO::NovaInstancia();
        $meuUsuario = $usuarioDAO->buscarUsuario(TipoBusca::ID, $meuId);
        $role = $_SESSION["role"];

        echo json_encode([
            "id"=>$meuUsuario->getId(),
            "nome"=>$meuUsuario->getNome(),
            "role"=>$role
        ]);
        return;
    }

    public function sair () :void
    {
        header("Content-Type: application/json");

        //  Armazena null na posição $_SESSION["id"].
        $_SESSION["id"] = null;
        //  Armazena Acesso::VISITANTE->value na posição $_SESSION["role"].
        $_SESSION["role"] = Acesso::VISITANTE->value;
        
        echo json_encode([
            "status"=>200,
            "mensagem"=>"usuario saiu de sua conta",
            "refresh"=>true
        ]);
        return;
    }

    // Atualiza nome ou senha da conta autenticada.
    public function atualizar () :void
    {
        header("Content-Type: application/json");

        $meuId = $_SESSION["id"] ?? null;
        if (!isset($meuId))
        {
            http_response_code(401);
            echo json_encode([
                "status" => 401,
                "mensagem" => "usuario nao foi identificado"
            ]);
            return;
        }

        $nome = trim((string) ($_POST["nome"] ?? ""));
        $senhaNova = (string) ($_POST["senha_nova"] ?? "");
        $senhaAtual = (string) ($_POST["senha_atual"] ?? "");

        $erro = null;
        // Acumula uma única mensagem de validação antes de iniciar qualquer atualização.
        if ($senhaAtual === "") $erro = "Digite a sua senha atual para confirmar";
        else if ($nome === "" && $senhaNova === "") $erro = "Nada para mudar";
        else if (strlen($nome) > 50) $erro = "O campo \"nome\" ultrapassa o tamanho maximo";
        else if (strlen($senhaNova) > 256) $erro = "O campo \"senha\" ultrapassa o tamanho maximo";

        if ($erro !== null)
        {
            http_response_code(400);
            echo json_encode([
                "status" => 400,
                "mensagem" => $erro
            ]);
            return;
        }

        try
        {
            // A senha atual confirma a titularidade antes de gravar qualquer alteração.
            $usuarioDAO = UsuarioDAO::NovaInstancia();
            $usuario = $usuarioDAO->buscarUsuario(TipoBusca::ID, (int) $meuId);

            if ($usuario === null || !password_verify($senhaAtual, $usuario->getSenhaHash()))
            {
                http_response_code(401);
                echo json_encode([
                    "status" => 401,
                    "mensagem" => "senha atual incorreta"
                ]);
                return;
            }

            $usuarioDAO->atualizarUsuario(
                (int) $meuId,
                $nome !== "" ? $nome : null,
                $senhaNova !== "" ? password_hash($senhaNova, PASSWORD_DEFAULT) : null
            );

            echo json_encode([
                "status" => 200,
                "mensagem" => "perfil atualizado",
                "nome" => $nome !== "" ? $nome : $usuario->getNome()
            ]);
            return;
        }

        catch (DomainException $e)
        {
            // Propaga recusas esperadas, como nome já utilizado, com seu status de domínio.
            http_response_code($e->getCode());
            echo json_encode([
                "status" => $e->getCode(),
                "mensagem" => $e->getMessage()
            ]);
            return;
        }

        catch (Exception $e)
        {
            // Mantém o diagnóstico no servidor e a resposta pública genérica.
            error_log("UsuarioController::atualizar: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel atualizar o perfil"
            ]);
            return;
        }
    }

    // Exclui a propria conta ou uma conta informada pelo administrador.
    public function excluir (int $id) :void
    {
        header("Content-Type: application/json");

        $meuId = $_SESSION["id"] ?? null;
        $souAdmin = ($_SESSION["role"] ?? null) === Acesso::ADMIN->value;
        if (!isset($meuId) || (!$souAdmin && (int) $id !== (int) $meuId))
        {
            http_response_code(403);
            echo json_encode([
                "status"=>403,
                "mensagem"=>"Voce so pode excluir a sua propria conta"
            ]);
            return;
        }

        $usuarioDAO = UsuarioDAO::NovaInstancia();
        $usuarioExclusao = $usuarioDAO->buscarUsuario(TipoBusca::ID, $id);

        if (!isset($usuarioExclusao))
        {
            http_response_code(401);
            echo json_encode([
                "status"=>401,
                "mensagem"=>"Não foi possivel excluir"
            ]);
            return;
        }

        try {
            $usuarioDAO->excluirUsuario($usuarioExclusao);
            $this->sair();
            return;
        }

        catch (PDOException $e)
        {
            http_response_code(500);
            echo json_encode([
                "status"=>500,
                "mensagem"=>"O servidor não consegui excluir essa conta"
            ]);
            return;
        }

        catch (Exception $e) 
        {
            http_response_code(500);
            echo json_encode([
                "status"=>500,
                "mensagem"=>"O servidor não consegui excluir essa conta"
            ]);
            return;
        }
        
    }
}
