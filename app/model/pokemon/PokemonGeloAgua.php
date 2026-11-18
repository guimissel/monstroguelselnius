<?php

class PokemonGeloAgua extends Pokemon
{
    use TipoGelo, TipoAgua;

    public function tipos(): array
    {
        return ['ice', 'water'];
    }
}
