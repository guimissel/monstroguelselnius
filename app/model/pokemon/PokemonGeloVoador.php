<?php

class PokemonGeloVoador extends Pokemon
{
    use TipoGelo, TipoVoador;

    public function tipos(): array
    {
        return ['ice', 'flying'];
    }
}
