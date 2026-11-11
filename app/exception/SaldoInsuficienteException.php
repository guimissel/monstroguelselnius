<?php

// Lancada quando o jogador tenta comprar sem ter pokedolares suficientes.
// Guarda quanto ele tinha e quanto custava, para quem quiser mostrar a diferenca.
class SaldoInsuficienteException extends RegraDeJogoException
{
    private int $saldo;
    private int $necessario;

    public function __construct(int $saldo, int $necessario)
    {
        parent::__construct("Pokedolares insuficientes", 409);
        $this->saldo = $saldo;
        $this->necessario = $necessario;
    }

    public function getSaldo(): int
    {
        return $this->saldo;
    }

    public function getNecessario(): int
    {
        return $this->necessario;
    }

    public function getFalta(): int
    {
        return $this->necessario - $this->saldo;
    }
}
