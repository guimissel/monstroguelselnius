<?php

class PokemonTerraPedra extends Pokemon
{
    use TipoTerra, TipoPedra;

    public function tipos(): array
    {
        return ['ground', 'rock'];
    }
}
