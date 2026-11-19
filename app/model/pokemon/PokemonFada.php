<?php

// Pokemon do tipo Fada (ex: Clefairy, Clefable).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonFada extends Pokemon
{
    use TipoFada;
}
