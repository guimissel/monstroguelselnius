<?php

class PokemonAguaVoador extends Pokemon
{
    use TipoAgua, TipoVoador;

    public function tipos(): array
    {
        return ['water', 'flying'];
    }
}
