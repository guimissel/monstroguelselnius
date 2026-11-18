<?php

class PokemonAguaPedra extends Pokemon
{
    use TipoAgua, TipoPedra;

    public function tipos(): array
    {
        return ['water', 'rock'];
    }
}
