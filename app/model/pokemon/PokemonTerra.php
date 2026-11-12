<?php

// Pokemon do tipo Terra (ex: Sandshrew, Sandslash, Cubone).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonTerra extends Pokemon
{
    use TipoTerra;

    public function tipos(): array
    {
        return ['ground'];
    }
}
