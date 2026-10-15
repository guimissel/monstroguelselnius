<?php

class Usuario 
{
    private ?int $id;
    private string $usuario;
    private string $senhaHash;

    public function __construct(?int $id, string $usuario, string $senhaHash) 
    {
        $this->id = $id;
        $this->usuario = $usuario;
        $this->senhaHash = $senhaHash;
    }

    public function getId (): int 
    {
        return $this->id;
    }

    public function getUsuario(): string
    {
        return $this->usuario;
    }

    public function getSenhaHash(): string
    {
        return $this->senhaHash;
    }
}