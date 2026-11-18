<?php

class PokemonTerraVeneno extends Pokemon
{
    use TipoTerra, TipoVeneno;

    public function tipos(): array
    {
        return ['ground', 'poison'];
    }
}
