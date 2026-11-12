<?php

// O que qualquer coisa precisa saber fazer para entrar numa batalha.
// Pokemon implementa esta interface (e o robo/treinador poderia implementar tambem).
interface Combatente
{
    public function getNome(): string;

    public function getVelocidade(): int;

    public function estaDesmaiado(): bool;

    // devolve o dano que realmente foi tirado do HP
    public function receberDano(int $dano): int;

    // quanto dano um golpe deste tipo causa (0 = imune, 0.5 = resiste, 2 = fraco...)
    public function multiplicadorContra(string $tipoDoGolpe): float;
}
