<?php

class PokemonVenenoVoador extends Pokemon
{
    use TipoVeneno, TipoVoador;

    public function tipos(): array
    {
        return ['poison', 'flying'];
    }
}
