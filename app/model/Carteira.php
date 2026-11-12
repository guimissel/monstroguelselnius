<?php

// Os pokedolares de um treinador.
// COMPOSICAO: a carteira so existe dentro do Treinador (ele a cria e ela some junto com ele).
class Carteira
{
    private int $saldo;

    public function __construct(int $saldo = 0)
    {
        $this->saldo = max($saldo, 0);
    }

    public function getSaldo(): int
    {
        return $this->saldo;
    }

    public function receber(int $valor): void
    {
        $this->saldo += max($valor, 0);
    }

    public function gastar(int $valor): void
    {
        if ($valor > $this->saldo) {
            throw new SaldoInsuficienteException($this->saldo, $valor);
        }
        $this->saldo -= $valor;
    }
}
