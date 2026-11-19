<?php

// Fornece aos DAOs a conexão comum e a fábrica que resolve automaticamente suas dependências.
class DAO
{
    use NovaInstancia;

    protected PDO $pdo;

    // Guarda a conexão recebida para que as consultas da subclasse usem o mesmo recurso.
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

}