<?php

class PokemonAguaGelo extends Pokemon
{
    use TipoAgua, TipoGelo;

    public function tipos(): array
    {
        return ['water', 'ice'];
    }
}
