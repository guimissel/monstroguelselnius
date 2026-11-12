<?php

// Pokemon do tipo Planta / Veneno (ex: Bulbasaur, Ivysaur, Venusaur).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonPlantaVeneno extends Pokemon
{
    use TipoPlanta, TipoVeneno;

    public function tipos(): array
    {
        return ['grass', 'poison'];
    }
}
