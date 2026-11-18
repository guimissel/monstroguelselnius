<?php

class PokemonPsiquicoFada extends Pokemon
{
    use TipoPsiquico, TipoFada;

    public function tipos(): array
    {
        return ['psychic', 'fairy'];
    }
}
