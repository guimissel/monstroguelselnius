<?php

class DAO {
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function novaInstancia ()
    {
        return new static (Conexao::getConexao());
    } 
}