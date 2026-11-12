<?php

require_once __DIR__ . "/../model/Usuario.php";
require_once __DIR__ . "/InventarioDAO.php";

enum TipoBusca: int{
    case ID = 0;
    case NOME = 1;
};

class UsuarioDAO
{
    private PDO $pdo;

    public function __construct(PDO $pdo) 
    {
        $this->pdo = $pdo;
    }

    public function criar(Usuario $usuario): Usuario
    {
        try {
            // inicia uma transaction
            $this->pdo->beginTransaction();

            $nome = $usuario->getUsuario();
            $senha = $usuario->getSenhaHash();

            // o login procura pelo nome, entao nome repetido nao pode
            if ($this->buscar(TipoBusca::NOME, $nome) !== null) {
                throw new RegraDeJogoException("Esse nome ja esta em uso", 409);
            }

            $sql = "INSERT INTO usuario (nome, senha) VALUES (:nome, :senha)";
            $prepare = $this->pdo->prepare($sql);

            $prepare->execute([
                "nome" => $nome,
                "senha" => $senha
            ]);

            $id = (int) $this->pdo->lastInsertId();

            // toda conta nova ganha o kit inicial de itens
            // se falhar (ex: o app/database/schema.sql ainda nao foi executado) a conta e criada do mesmo jeito
            try {
                (new InventarioDAO($this->pdo))->darKitInicial($id);
            } catch (PDOException $erroKit) {
                error_log("kit inicial nao entregue: " . $erroKit->getMessage());
            }

            // finaliza a transaction
            $this->pdo->commit();

            return new Usuario($id, $nome, null);
        } 
        
        catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function buscar (TipoBusca $tipoBusca, mixed $chaveBusca): ?Usuario
    {
        switch ($tipoBusca) {
            case TipoBusca::ID:
                $sql = "SELECT * FROM usuario WHERE id_usuario = :id";
                $prepare = $this->pdo->prepare($sql);
                $prepare->execute([
                    "id"=>$chaveBusca
                ]);
            break;
            
            case TipoBusca::NOME:
            default:
                $sql = "SELECT * FROM usuario WHERE nome = :nome";
                $prepare = $this->pdo->prepare($sql);
                $prepare->execute([
                    "nome"=>$chaveBusca
                ]);
            break;
        }

        $dado = $prepare->fetch();
        if ($dado === false) {
            return null;
        }

        return new Usuario(
            (int) $dado["id_usuario"],
            $dado["nome"],
            $dado["senha"]
        );
    }

    // troca o nome e/ou a senha (null = nao muda). Nome repetido sai como RegraDeJogoException 409
    public function atualizar (int $id, ?string $nome, ?string $senhaHash): void
    {
        if ($nome !== null) {
            $existente = $this->buscar(TipoBusca::NOME, $nome);
            if ($existente !== null && $existente->getId() !== $id) {
                throw new RegraDeJogoException("Esse nome ja esta em uso", 409);
            }
        }

        $prepare = $this->pdo->prepare(
            "UPDATE usuario SET nome = COALESCE(:nome, nome), senha = COALESCE(:senha, senha) WHERE id_usuario = :id"
        );
        $prepare->execute(["nome" => $nome, "senha" => $senhaHash, "id" => $id]);
    }

    public function excluir (Usuario $usuario): void
    {
        // inicia a transação
        $this->pdo->beginTransaction();

        try {
            $id = $usuario->getId();

            // as batalhas do jogador precisam sair antes (batalha referencia usuario)
            $apagarBatalhas = $this->pdo->prepare("DELETE FROM batalha WHERE id_usuario = :id OR id_usuario_2 = :id2");
            $apagarBatalhas->execute(["id" => $id, "id2" => $id]);

            // prepara a query
            $prepare = $this->pdo->prepare("DELETE FROM usuario WHERE id_usuario = :id");
            $prepare->execute([
                "id"=> $id
            ]);

            // finaliza a transação
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}