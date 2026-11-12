<?php

// Pokemon do tipo Normal / Fada (ex: Jigglypuff, Wigglytuff).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonNormalFada extends Pokemon
{
    use TipoNormal, TipoFada;

    public function tipos(): array
    {
        return ['normal', 'fairy'];
    }
}
