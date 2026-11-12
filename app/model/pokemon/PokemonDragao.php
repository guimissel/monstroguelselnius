<?php

// Pokemon do tipo Dragão (ex: Dratini, Dragonair).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonDragao extends Pokemon
{
    use TipoDragao;

    public function tipos(): array
    {
        return ['dragon'];
    }
}
