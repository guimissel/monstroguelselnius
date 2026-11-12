<?php

// Pokemon do tipo Elétrico (ex: Pikachu, Raichu).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonEletrico extends Pokemon
{
    use TipoEletrico;

    public function tipos(): array
    {
        return ['electric'];
    }
}
