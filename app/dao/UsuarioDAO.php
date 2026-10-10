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

    public function criar (Usuario $usuario): void
    {
        // inicia uma transação
        $this->pdo->beginTransaction();

        $nome = $usuario->getUsuario();
        $senha = $usuario->getSenhaHash();

        // prepara o sql
        $prepare = $this->pdo->prepare("INSERT INTO usuario (nome, senha) VALUES (:nome, :senha)");
        
        // insere os valores
        $prepare->execute([
            "nome"=> $nome,
            "senha"=> $senha
        ]);

        // finaliza transação
        $this->pdo->commit();

        return;
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