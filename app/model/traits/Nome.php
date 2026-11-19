<?php

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