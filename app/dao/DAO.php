<?php

class DAO
{
    use NovaInstancia;

    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

}