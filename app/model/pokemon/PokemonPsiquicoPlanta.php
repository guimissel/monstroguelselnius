<?php

class PokemonPsiquicoPlanta extends Pokemon
{
    use TipoPsiquico, TipoPlanta;

    public function tipos(): array
    {
        return ['psychic', 'grass'];
    }
}
