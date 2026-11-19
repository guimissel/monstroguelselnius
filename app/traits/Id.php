<?php

// Compartilha o identificador inteiro entre entidades do domínio.
trait Id {
    private int $id;

    public function getId (): int
    {
        return $this->id;
    }

    public function setId (int $novoId): void
    {
        $this->id = $novoId;
    }
}