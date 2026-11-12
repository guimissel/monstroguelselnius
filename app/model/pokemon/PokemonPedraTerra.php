<?php

// Pokemon do tipo Pedra / Terra (ex: Geodude, Graveler, Golem).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonPedraTerra extends Pokemon
{
    use TipoPedra, TipoTerra;

    public function tipos(): array
    {
        return ['rock', 'ground'];
    }
}
