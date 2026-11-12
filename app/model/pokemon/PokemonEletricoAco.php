<?php

// Pokemon do tipo Elétrico / Aço (ex: Magnemite, Magneton).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonEletricoAco extends Pokemon
{
    use TipoEletrico, TipoAco;

    public function tipos(): array
    {
        return ['electric', 'steel'];
    }
}
