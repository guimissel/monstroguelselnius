<?php

class PokemonInseto extends Pokemon
{
    use TipoInseto;

    public function tipos(): array
    {
        return ['bug'];
    }
}
