<?php

// Pokemon do tipo Fantasma / Veneno (ex: Gastly, Haunter, Gengar).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonFantasmaVeneno extends Pokemon
{
    use TipoFantasma, TipoVeneno;

    public function tipos(): array
    {
        return ['ghost', 'poison'];
    }
}
