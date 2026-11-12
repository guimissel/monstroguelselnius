<?php

// Pokemon do tipo Água (ex: Squirtle, Wartortle, Blastoise).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonAgua extends Pokemon
{
    use TipoAgua;

    public function tipos(): array
    {
        return ['water'];
    }
}
