<?php

// Pokemon do tipo Dragão / Voador (ex: Dragonite).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonDragaoVoador extends Pokemon
{
    use TipoDragao, TipoVoador;

    public function tipos(): array
    {
        return ['dragon', 'flying'];
    }
}
