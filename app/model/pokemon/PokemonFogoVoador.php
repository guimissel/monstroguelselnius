<?php

// Pokemon do tipo Fogo / Voador (ex: Charizard).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonFogoVoador extends Pokemon
{
    use TipoFogo, TipoVoador;

    public function tipos(): array
    {
        return ['fire', 'flying'];
    }
}
