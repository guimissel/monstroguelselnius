<?php

class PokemonPlantaInseto extends Pokemon
{
    use TipoPlanta, TipoInseto;

    public function tipos(): array
    {
        return ['grass', 'bug'];
    }
}
