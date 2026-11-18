<?php

class PokemonVoadorNormal extends Pokemon
{
    use TipoVoador, TipoNormal;

    public function tipos(): array
    {
        return ['flying', 'normal'];
    }
}
