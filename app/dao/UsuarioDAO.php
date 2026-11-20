<?php

require_once __DIR__ . "/../model/Usuario.php";
require_once __DIR__ . "/InventarioDAO.php";

/**
 * @var TipoBusca uma estrutura de enumeração para o tipo de busca
 */
enum TipoBusca: int{
    case ID = 0;
    case NOME = 1;
};

/**
 * Classe para acessar o banco de dados de usuarios
 * 
 * @author Miguel Granville
 * @method Usuario criarUsuario(Usuario $usuario)
 * @method ?Usuario buscarUsuario (TipoBusca $tipoBusca, mixed $chaveBusca)
 * @method void atualizarUsuario (int $id, ?string $nome, ?string $senhaHash)
 * @method void excluirUsuario (Usuario $usuario)
 */
class UsuarioDAO extends DAO
{

    /**
     * Cria um usuario no banco de dados
     * 
     * @author Miguel Granville
     * @param Usuario $usuario uma instancia do tipo Usuario, com Id definido como -1, Nome, SenhaHash
     * @return Usuario retorna um usuario criado, esse retorno é constituido de Id, Nome, SenhaHash com null por segurança
    **/
    public function criarUsuario(Usuario $usuario): Usuario
    {
        // tenta fazer esse código com tratamento de exceptions
        try {

            // inicia uma transaction 
            $this->pdo->beginTransaction();

            // atributos vindos do usuario passado pelo parametro
            $nome = $usuario->getNome();
            $senha = $usuario->getSenhaHash();

            // o login procura pelo nome, entao nome repetido nao pode
            if ($this->buscarUsuario(TipoBusca::NOME, $nome) !== null) {
                
                // dispara uma exceção de regra de jogo
                throw new RegraDeJogoException("Esse nome ja esta em uso", 409);
            }

            // inserção que retorna o ultimo id inserido
            $id = $this->Inserir([
                "colunas" => ["nome", "senha"],
                "tabela" => "usuario",
                "valores"=> [
                    "nome" => $nome,
                    "senha" => $senha
                ]
            ], true);

            // toda conta nova ganha o kit inicial de itens
            // se falhar (ex: o app/database/schema.sql ainda nao foi executado) a conta e criada do mesmo jeito
            try 
            {
                // concede o kit inicial para a nova instancia de inventario
                (InventarioDAO::NovaInstancia())->darKitInicial($id);
            } 
            
            // trata exception com relação ao banco de dados
            catch (PDOException $erroKit) 
            {
                error_log("kit inicial nao entregue: " . $erroKit->getMessage());
            }

            // se ocorrer como esperado, realiza o commit
            $this->pdo->commit();

            // retorna o novo usuario, sem o hash da senha por segurança
            return new Usuario($id, $nome, null);
        } 
        
        // resolve uma exceção generica
        catch (Throwable $e) 
        {
            // se possuir uma transaction
            if ($this->pdo->inTransaction()) 
            {
                // faça rollback
                $this->pdo->rollBack();
            }

            // trata a exceção
            throw $e;
        }
    }

    /**
     * Busca Usuario baseado em um tipo de busca
     * 
     * @author Miguel Granville
     * @param TipoBusca $tipoBusca uma estrutura enum com os diferentes tipos de busca
     * @param mixed $chaveBusca chave a ser buscada no banco de dados
     * @return ?Usuario retorna o usuario buscado ou null caso não encontre
    **/
    public function buscarUsuario (TipoBusca $tipoBusca, mixed $chaveBusca): ?Usuario
    {

        // dados do banco
        $dado = null; 

        //  Seleciona um ramo de execução com base em ($tipoBusca.
        switch ($tipoBusca) 
        {
            case TipoBusca::ID:
                $dado = $this->Buscar([
                    "colunas" => "*",
                    "tabela" => "usuario",
                    "pesquisa" => "id_usuario = :id",
                    "valores" => [
                        "id" => $chaveBusca
                    ]
                ]);
            break;
            
            case TipoBusca::NOME:
            //  Define o ramo usado quando nenhum caso do switch corresponde.
            default:
                $dado = $this->Buscar([
                    "colunas" => "*",
                    "tabela" => "usuario",
                    "pesquisa" => "nome = :nome",
                    "valores" => [
                        "nome" => $chaveBusca
                    ]
                ]);
            break;
        }

        if ($dado === false || $dado === null) 
        {
            return null;
        }

        return new Usuario(
            (int) $dado["id_usuario"],
            $dado["nome"],
            $dado["senha"]
        );
    }

    /**
     * Atualiza nome e/ou senha de um determinado usuario
     * 
     * @author Miguel Granville
     * @param int $id id do usuario para atualizar
     * @param ?string $nome nome do usuario que vai ser atulizado, ou null caso não seja atualizado o nome
     * @param ?string $senhaHash hash da senha do usuario que vai ser atulizado, ou null caso não seja atualizado
     * @return void não retorna nenhuma confirmação
     */
    public function atualizarUsuario (int $id, ?string $nome, ?string $senhaHash): void
    {
        // caso atualização de nome
        if ($nome !== null) {

            // busca o usuario pelo nome para evitar duplicidade
            $existente = $this->buscarUsuario(TipoBusca::NOME, $nome);

            // exepction 409 caso exista o usuario com esse nome
            if ($existente !== null && $existente->getId() !== $id) 
            {
                // trata a exceção
                throw new RegraDeJogoException("Esse nome ja esta em uso", 409);
            }
        }

        // atualiza os dados do usuario
        $this->Atualizar([
            "tabela" => "usuario",
            "condicao" => "id_usuario = :id",

            "colunas" => [
                "nome" => "COALESCE(:nome, nome)",
                "senha" => "COALESCE(:senha, senha)"
            ],
            
            "valores" => [
                "nome" => $nome, 
                "senha" => $senhaHash, 
                "id" => $id
            ]
        ]);
    }

    /**
     * Excluir um determinado usuario
     * 
     * @author Miguel Granville
     * @param Usuario $usuario usuario para excluir
     * @return void não retorna nenhuma confirmação
     */
    public function excluirUsuario (Usuario $usuario): void
    {
        // inicia a transação
        $this->pdo->beginTransaction();

        // tenta executar e se não conseguir cai no catch
        try {

            // pega o id do usuario
            $id = $usuario->getId();

            // as batalhas do jogador precisam sair antes (batalha referencia usuario)
            $this->Excluir([
                "tabela" => "batalha",
                "condicao" => "id_usuario = :id OR id_usuario_2 = :id2",
                "valores" => [
                    "id" => $id, 
                    "id2" => $id
                ]
            ]);

            // excluindo o usuario do banco de dados
            $this->Excluir([
                "tabela" => "usuario",
                "condicao" => "id_usuario = :id",
                "valores" => [
                    "id" => $id
                ]
            ]);

            // finaliza a transação
            $this->pdo->commit();
        } 
        
        // trata uma exceção generica
        catch (Throwable $e) 
        {
            // se estiver em uma transaction
            if ($this->pdo->inTransaction()) 
            {
                // reverta ela
                $this->pdo->rollBack();
            }

            // trata a exceção
            throw $e;
        }
    }
}