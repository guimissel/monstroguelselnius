<?php

class PokemonInsetoVoador extends Pokemon
{
    use TipoInseto, TipoVoador;

    public function tipos(): array
    {
        return ['bug', 'flying'];
    }
}
