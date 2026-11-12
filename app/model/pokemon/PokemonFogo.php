<?php

// Pokemon do tipo Fogo (ex: Charmander, Charmeleon, Vulpix).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonFogo extends Pokemon
{
    use TipoFogo;

    public function tipos(): array
    {
        return ['fire'];
    }
}
