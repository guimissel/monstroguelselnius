<?php

/**
 * Controla as ações de conta e autenticação dos usuários
 * 
 * @author Miguel Granville / Vinicius Grub
 * @method void criar()
 * @method void entrar()
 * @method void eu()
 * @method void sair()
 * @method void atualizar()
 * @method void excluir(int $id)
 */
class UsuarioController extends Controller
{
    /**
     * Cria uma nova conta.
     * 
     * @author Miguel Granville / Vinicius Grub
     * @return void
     */
    public function criar () :void
    {
        // Impede que campos obrigatórios vazios cheguem às regras de criação da conta.
        foreach ($_POST as $chave => $valor) {
            if (trim((string) $valor) === '')
            {
                Controller::RespostaJson()
                    ->status(400)
                    ->mensagem("O campo \"" . $chave . "\" está vazio")
                    ->enviar();
                return;
            }    
        }

        // Mantém os limites compatíveis com o armazenamento do nome e da senha.
        if (strlen($_POST["usuario"]) > 50)
        {
            Controller::RespostaJson()
                ->status(400)
                ->mensagem("O campo \"usuario\" ultrapassa o tamanho maximo")
                ->enviar();
            return;
        }
        else if (strlen($_POST["senha"]) > 256)
        {
            Controller::RespostaJson()
                ->status(400)
                ->mensagem("O campo \"senha\" ultrapassa o tamanho maximo")
                ->enviar();
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

            Controller::RespostaJson()
                ->status(200)
                ->mensagem("usuario criado com sucesso")
                ->adicionar("refresh", true)
                ->enviar();
            return;
        }
        catch (DomainException $e)
        {
            // Problemas esperados de domínio mantêm seu status e mensagem específicos.
            Controller::RespostaJson()
                ->status($e->getCode())
                ->mensagem($e->getMessage())
                ->enviar();
            return;
        }
        catch (PDOException $e)
        {    
            // Erros de banco são diferenciados dos demais erros internos da aplicação.
            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Nao foi possivel criar esse usuario: " . $e->getMessage())
                ->enviar();
            return;
        }
        catch (Exception $e)
        {
            // Não expõe detalhes de exceções inesperadas durante o cadastro.
            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Erro interno! tente novamente mais tarde")
                ->enviar();
            return;
        }
    }

    /**
     * Autentica o jogador.
     * 
     * @author Miguel Granville / Vinicius Grub
     * @return void
     */
    public function entrar () :void
    {
        // Exige os campos do formulário antes de consultar as credenciais.
        foreach ($_POST as $chave => $valor) {
            if (trim((string) $valor) === '')
            {
                Controller::RespostaJson()
                    ->status(400)
                    ->mensagem("O campo \"" . $chave . "\" está vazio")
                    ->enviar();
                return;
            }    
        }

        // Rejeita entradas acima dos limites conhecidos pelo cadastro.
        if (strlen($_POST["usuario"]) > 50)
        {
            Controller::RespostaJson()
                ->status(400)
                ->mensagem("O campo \"usuario\" ultrapassa o tamanho maximo")
                ->enviar();
            return;
        }
        else if (strlen($_POST["senha"]) > 256)
        {
            Controller::RespostaJson()
                ->status(400)
                ->mensagem("O campo \"senha\" ultrapassa o tamanho maximo")
                ->enviar();
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
                Controller::RespostaJson()
                    ->status(401)
                    ->mensagem("usuario ou senha incorretos")
                    ->enviar();
                return;
            }
            else if (!password_verify($senha, $usuario->getSenhaHash()))
            {
                Controller::RespostaJson()
                    ->status(401)
                    ->mensagem("usuario ou senha incorretos")
                    ->enviar();
                return;
            }

            //  Armazena $usuario->getId() na posição $_SESSION["id"].
            $_SESSION["id"] = $usuario->getId();
            //  Armazena Acesso::USUARIO->value na posição $_SESSION["role"].
            $_SESSION["role"] = Acesso::USUARIO->value;

            Controller::RespostaJson()
                ->status(200)
                ->mensagem("acesso liberado com sucesso")
                ->enviar();
            return;
        }
        catch (PDOException $e)
        {    
            // Falhas de persistência não são confundidas com credenciais inválidas.
            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Nao foi possivel criar esse usuario")
                ->enviar();
            return;
        }
        catch (Exception $e)
        {
            // Oculta detalhes de falhas internas da autenticação.
            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Erro interno! tente novamente mais tarde")
                ->enviar();
            return;
        }
    }

    /**
     * Retorna o jogador autenticado.
     * 
     * @author Miguel Granville / Vinicius Grub
     * @return void
     */
    public function eu () :void
    {
        $conexao = Conexao::getConexao();
        $meuId = $_SESSION["id"] ?? null;

        // Sem ID de sessão, não há identidade para consultar ou devolver.
        if (!isset($meuId))
        {
            Controller::RespostaJson()
                ->status(401)
                ->mensagem("usuario nao foi identificado")
                ->enviar();
            return;
        }

        $usuarioDAO = UsuarioDAO::NovaInstancia();
        $meuUsuario = $usuarioDAO->buscarUsuario(TipoBusca::ID, $meuId);
        $role = $_SESSION["role"];

        Controller::RespostaJson()
            ->status(200)
            ->adicionar("id", $meuUsuario->getId())
            ->adicionar("nome", $meuUsuario->getNome())
            ->adicionar("role", $role)
            ->enviar();
        return;
    }

    /**
     * Remove a sessão do usuário.
     * 
     * @author Miguel Granville / Vinicius Grub
     * @return void
     */
    public function sair () :void
    {
        //  Armazena null na posição $_SESSION["id"].
        $_SESSION["id"] = null;
        //  Armazena Acesso::VISITANTE->value na posição $_SESSION["role"].
        $_SESSION["role"] = Acesso::VISITANTE->value;
        
        Controller::RespostaJson()
            ->status(200)
            ->mensagem("usuario saiu de sua conta")
            ->adicionar("refresh", true)
            ->enviar();
        return;
    }

    /**
     * Atualiza nome ou senha da conta autenticada.
     * 
     * @author Miguel Granville / Vinicius Grub
     * @return void
     */
    public function atualizar () :void
    {
        $meuId = $_SESSION["id"] ?? null;
        if (!isset($meuId))
        {
            Controller::RespostaJson()
                ->status(401)
                ->mensagem("usuario nao foi identificado")
                ->enviar();
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
            Controller::RespostaJson()
                ->status(400)
                ->mensagem($erro)
                ->enviar();
            return;
        }

        try
        {
            // A senha atual confirma a titularidade antes de gravar qualquer alteração.
            $usuarioDAO = UsuarioDAO::NovaInstancia();
            $usuario = $usuarioDAO->buscarUsuario(TipoBusca::ID, (int) $meuId);

            if ($usuario === null || !password_verify($senhaAtual, $usuario->getSenhaHash()))
            {
                Controller::RespostaJson()
                    ->status(401)
                    ->mensagem("senha atual incorreta")
                    ->enviar();
                return;
            }

            $usuarioDAO->atualizarUsuario(
                (int) $meuId,
                $nome !== "" ? $nome : null,
                $senhaNova !== "" ? password_hash($senhaNova, PASSWORD_DEFAULT) : null
            );

            Controller::RespostaJson()
                ->status(200)
                ->mensagem("perfil atualizado")
                ->adicionar("nome", $nome !== "" ? $nome : $usuario->getNome())
                ->enviar();
            return;
        }
        catch (DomainException $e)
        {
            // Propaga recusas esperadas, como nome já utilizado, com seu status de domínio.
            Controller::RespostaJson()
                ->status($e->getCode())
                ->mensagem($e->getMessage())
                ->enviar();
            return;
        }
        catch (Exception $e)
        {
            // Mantém o diagnóstico no servidor e a resposta pública genérica.
            error_log("UsuarioController::atualizar: " . $e->getMessage());

            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Nao foi possivel atualizar o perfil")
                ->enviar();
            return;
        }
    }

    /**
     * Exclui a propria conta ou uma conta informada pelo administrador.
     * 
     * @author Miguel Granville / Vinicius Grub
     * @param int $id Identificador da conta a ser excluída
     * @return void
     */
    public function excluir (int $id) :void
    {
        $meuId = $_SESSION["id"] ?? null;
        $souAdmin = ($_SESSION["role"] ?? null) === Acesso::ADMIN->value;
        
        if (!isset($meuId) || (!$souAdmin && (int) $id !== (int) $meuId))
        {
            Controller::RespostaJson()
                ->status(403)
                ->mensagem("Voce so pode excluir a sua propria conta")
                ->enviar();
            return;
        }

        $usuarioDAO = UsuarioDAO::NovaInstancia();
        $usuarioExclusao = $usuarioDAO->buscarUsuario(TipoBusca::ID, $id);

        if (!isset($usuarioExclusao))
        {
            Controller::RespostaJson()
                ->status(401)
                ->mensagem("Não foi possivel excluir")
                ->enviar();
            return;
        }

        try {
            $usuarioDAO->excluirUsuario($usuarioExclusao);
            $this->sair();
            return;
        }
        catch (PDOException $e)
        {
            Controller::RespostaJson()
                ->status(500)
                ->mensagem("O servidor não consegui excluir essa conta")
                ->enviar();
            return;
        }
        catch (Exception $e) 
        {
            Controller::RespostaJson()
                ->status(500)
                ->mensagem("O servidor não consegui excluir essa conta")
                ->enviar();
            return;
        }
    }
}