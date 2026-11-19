<?php

// Combina as regras de tipo pedra e voador para calcular efetividade de golpes desta variante.
class PokemonPedraVoador extends Pokemon
{
    use TipoPedra, TipoVoador;
}
