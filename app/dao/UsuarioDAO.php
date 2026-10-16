<?php

require_once __DIR__ . "/../model/Usuario.php";

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

            $sql = "INSERT INTO usuario (nome, senha) VALUES (:nome, :senha)";
            $prepare = $this->pdo->prepare($sql);

            $prepare->execute([
                "nome" => $nome,
                "senha" => $senha
            ]);

            $id = (int) $this->pdo->lastInsertId();

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

    public function excluir (Usuario $usuario): void
    {
        // inicia a transação
        $this->pdo->beginTransaction();

        // prepara a query
        $prepare = $this->pdo->prepare("DELETE FROM usuario WHERE id_usuario = :id");

        $id = $usuario->getId();
        $prepare->execute([
            "id"=> $id
        ]);

        // finaliza a transação
        $this->pdo->commit();
        return;
    }
}