<?php

class PokemonFadaPsiquico extends Pokemon
{
    use TipoFada, TipoPsiquico;

    public function tipos(): array
    {
        return ['fairy', 'psychic'];
    }
}
