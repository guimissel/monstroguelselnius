<?php

// Os pokedolares de um treinador.
// COMPOSICAO: a carteira so existe dentro do Treinador (ele a cria e ela some junto com ele).
class Carteira
{
    private int $saldo;

    // Garante que a carteira nunca comece com saldo negativo.
    public function __construct(int $saldo = 0)
    {
        $this->saldo = max($saldo, 0);
    }

    public function getSaldo(): int
    {
        return $this->saldo;
    }

    // Ignora valores negativos para que uma recompensa nunca reduza o saldo.
    public function receber(int $valor): void
    {
        //  Soma o valor atual de $this->saldo em max($valor, 0).
        $this->saldo += max($valor, 0);
    }

    // Impede saldo negativo e sinaliza explicitamente quando a compra excede o disponível.
    public function gastar(int $valor): void
    {
        if ($valor > $this->saldo) {
            throw new SaldoInsuficienteException($this->saldo, $valor);
        }
        $this->saldo -= $valor;
    }
}
