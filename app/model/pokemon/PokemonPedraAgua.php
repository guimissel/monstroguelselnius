<?php

class PokemonPedraAgua extends Pokemon
{
    use TipoPedra, TipoAgua;

    public function tipos(): array
    {
        return ['rock', 'water'];
    }
}
