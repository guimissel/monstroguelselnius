<?php

class Usuario 
{
    use Id;
    use Nome;

    private string $usuario;
    private ?string $senhaHash;

    public function __construct(int $id, string $nome, ?string $senhaHash) 
    {
        $this->setId($id);
        $this->setNome($nome);

        $this->senhaHash = $senhaHash;
    }

    public function getSenhaHash(): string
    {
        return $this->senhaHash;
    }
}