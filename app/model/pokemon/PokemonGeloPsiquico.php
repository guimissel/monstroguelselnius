<?php

class PokemonGeloPsiquico extends Pokemon
{
    use TipoGelo, TipoPsiquico;

    public function tipos(): array
    {
        return ['ice', 'psychic'];
    }
}
