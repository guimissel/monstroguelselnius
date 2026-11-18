<?php

class PokemonInsetoVeneno extends Pokemon
{
    use TipoInseto, TipoVeneno;

    public function tipos(): array
    {
        return ['bug', 'poison'];
    }
}
