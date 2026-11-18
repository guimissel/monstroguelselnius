<?php

class PokemonPlantaPsiquico extends Pokemon
{
    use TipoPlanta, TipoPsiquico;

    public function tipos(): array
    {
        return ['grass', 'psychic'];
    }
}
