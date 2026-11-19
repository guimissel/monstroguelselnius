<?php

// Compartilha o nome usado para identificar entidades na interface e nas regras de domínio.
trait Nome {
    private string $nome;

    public function getNome (): string
    {
        return $this->nome;
    }

    public function setNome (string $novoNome): void
    {
        $this->nome = $novoNome;
    }
}