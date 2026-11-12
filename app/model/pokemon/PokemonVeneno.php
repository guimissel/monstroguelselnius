<?php

// Pokemon do tipo Veneno (ex: Ekans, Arbok).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonVeneno extends Pokemon
{
    use TipoVeneno;

    public function tipos(): array
    {
        return ['poison'];
    }
}
