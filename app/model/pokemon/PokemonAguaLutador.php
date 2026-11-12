<?php

// Pokemon do tipo Água / Lutador (ex: Poliwrath).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonAguaLutador extends Pokemon
{
    use TipoAgua, TipoLutador;

    public function tipos(): array
    {
        return ['water', 'fighting'];
    }
}
