<?php

// Pokemon do tipo Água / Veneno (ex: Tentacool, Tentacruel).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonAguaVeneno extends Pokemon
{
    use TipoAgua, TipoVeneno;

    public function tipos(): array
    {
        return ['water', 'poison'];
    }
}
