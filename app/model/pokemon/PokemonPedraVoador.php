<?php

class PokemonPedraVoador extends Pokemon
{
    use TipoPedra, TipoVoador;

    public function tipos(): array
    {
        return ['rock', 'flying'];
    }
}
