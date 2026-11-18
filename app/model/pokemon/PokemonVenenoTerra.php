<?php

class PokemonVenenoTerra extends Pokemon
{
    use TipoVeneno, TipoTerra;

    public function tipos(): array
    {
        return ['poison', 'ground'];
    }
}
