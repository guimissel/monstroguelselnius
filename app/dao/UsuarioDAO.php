<?php

require_once __DIR__ . "/../model/Usuario.php";
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

        return;
    }
}