<?php

class PokemonInsetoPlanta extends Pokemon
{
    use TipoInseto, TipoPlanta;

    public function tipos(): array
    {
        return ['bug', 'grass'];
    }
}
