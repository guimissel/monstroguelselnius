<?php

// Pokemon do tipo Normal (ex: Rattata, Raticate, Meowth).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonNormal extends Pokemon
{
    use TipoNormal;

    public function tipos(): array
    {
        return ['normal'];
    }
}
