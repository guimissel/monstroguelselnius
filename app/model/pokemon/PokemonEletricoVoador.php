<?php

class PokemonEletricoVoador extends Pokemon
{
    use TipoEletrico, TipoVoador;

    public function tipos(): array
    {
        return ['electric', 'flying'];
    }
}
