<?php

class PokemonPsiquicoAgua extends Pokemon
{
    use TipoPsiquico, TipoAgua;

    public function tipos(): array
    {
        return ['psychic', 'water'];
    }
}
