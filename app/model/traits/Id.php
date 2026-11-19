<?php

// trait para id
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