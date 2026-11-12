<?php

// Pokemon do tipo Lutador (ex: Mankey, Primeape, Machop).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonLutador extends Pokemon
{
    use TipoLutador;

    public function tipos(): array
    {
        return ['fighting'];
    }
}
