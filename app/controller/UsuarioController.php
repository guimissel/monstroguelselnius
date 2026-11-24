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

        // tenta criar o usuario
        try 
        {
            // Armazena somente o hash da senha e inicia a sessão após a persistência da conta.
            $conexao = Conexao::getConexao();

            // dados vindos do input
            $nome = $_POST["usuario"];
            $senha = $_POST["senha"];
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

            // cria um novo usuario para ser usado no sistema
            $usuario = new Usuario(
                -1,
                $nome,
                $senhaHash
            );

            // cria a instancia do DAO
            $usuarioDAO = UsuarioDAO::NovaInstancia();

            // pede para o DAO criar usuario
            $usuario = $usuarioDAO->criarUsuario($usuario);
            $id = $usuario->getId();

            $_SESSION["id"] = $id;
            $_SESSION["role"] = Acesso::USUARIO->value;

            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [INFO] UsuarioController::criar: Nova conta criada (ID {$id}, nome '{$nome}')" . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );
                
            // responde com um json
            Controller::RespostaJson()
                ->status(200)
                ->mensagem("Usuario criado com sucesso")
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
            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [ERRO] UsuarioController::criar (PDO): " . $e->getMessage() . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

            // Erros de banco são diferenciados dos demais erros internos da aplicação.
            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Nao foi possivel criar esse usuario: " . $e->getMessage())
                ->enviar();
            return;
        }
        catch (Exception $e)
        {
            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [ERRO] UsuarioController::criar: " . $e->getMessage() . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

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
                Log::EscreverArquivo(
                    "[" . date("Y-m-d H:i:s") . "] [AVISO] UsuarioController::entrar: Tentativa de login com usuario inexistente '{$nome}'" . PHP_EOL,
                    "logs/sistema.log",
                    ["FILE_APPEND", "LOCK_EX"]
                );

                Controller::RespostaJson()
                    ->status(401)
                    ->mensagem("usuario ou senha incorretos")
                    ->enviar();
                return;
            }
            else if (!password_verify($senha, $usuario->getSenhaHash()))
            {
                Log::EscreverArquivo(
                    "[" . date("Y-m-d H:i:s") . "] [AVISO] UsuarioController::entrar: Senha incorreta para o usuario '{$nome}'" . PHP_EOL,
                    "logs/sistema.log",
                    ["FILE_APPEND", "LOCK_EX"]
                );

                Controller::RespostaJson()
                    ->status(401)
                    ->mensagem("usuario ou senha incorretos")
                    ->enviar();
                return;
            }

            $_SESSION["id"] = $usuario->getId();
            $_SESSION["role"] = Acesso::USUARIO->value;

            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [INFO] UsuarioController::entrar: Usuario ID {$usuario->getId()} ('{$nome}') autenticado" . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

            Controller::RespostaJson()
                ->status(200)
                ->mensagem("acesso liberado com sucesso")
                ->enviar();
            return;
        }
        catch (PDOException $e)
        {
            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [ERRO] UsuarioController::entrar (PDO): " . $e->getMessage() . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

            // Falhas de persistência não são confundidas com credenciais inválidas.
            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Nao foi possivel criar esse usuario")
                ->enviar();
            return;
        }
        catch (Exception $e)
        {
            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [ERRO] UsuarioController::entrar: " . $e->getMessage() . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

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
        $idAnterior = $_SESSION["id"] ?? "desconhecido";

        $_SESSION["id"] = null;
        $_SESSION["role"] = Acesso::VISITANTE->value;

        Log::EscreverArquivo(
            "[" . date("Y-m-d H:i:s") . "] [INFO] UsuarioController::sair: Usuario ID {$idAnterior} encerrou a sessao" . PHP_EOL,
            "logs/sistema.log",
            ["FILE_APPEND", "LOCK_EX"]
        );
        
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

            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [INFO] UsuarioController::atualizar: Usuario ID {$meuId} atualizou o perfil" . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
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
            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [ERRO] UsuarioController::atualizar: " . $e->getMessage() . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

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
            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [AVISO] UsuarioController::excluir: Tentativa nao autorizada de excluir conta ID {$id} por usuario ID " . ($meuId ?? "visitante") . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

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

            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [INFO] UsuarioController::excluir: Conta ID {$id} excluida por usuario ID {$meuId}" . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

            $this->sair();
            return;
        }
        catch (PDOException $e)
        {
            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [ERRO] UsuarioController::excluir (PDO): " . $e->getMessage() . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

            Controller::RespostaJson()
                ->status(500)
                ->mensagem("O servidor não consegui excluir essa conta")
                ->enviar();
            return;
        }
        catch (Exception $e) 
        {
            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [ERRO] UsuarioController::excluir: " . $e->getMessage() . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

            Controller::RespostaJson()
                ->status(500)
                ->mensagem("O servidor não consegui excluir essa conta")
                ->enviar();
            return;
        }
    }
}