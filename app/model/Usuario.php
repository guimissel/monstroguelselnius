<?php

// Representa a identidade persistida do jogador e mantém o hash separado do nome público.
class Usuario 
{
    use Id;
    use Nome;

    private string $usuario;
    private ?string $senhaHash;

    // Aceita hash nulo para objetos usados em respostas que não precisam carregar credenciais.
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