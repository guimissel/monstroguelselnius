<?php

// Pokemon do tipo Normal / Voador (ex: Pidgey, Pidgeotto, Pidgeot).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonNormalVoador extends Pokemon
{
    use TipoNormal, TipoVoador;

    public function tipos(): array
    {
        return ['normal', 'flying'];
    }
}
