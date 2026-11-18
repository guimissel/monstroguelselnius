<?php

class PokemonPlanta extends Pokemon
{
    use TipoPlanta;

    public function tipos(): array
    {
        return ['grass'];
    }
}
