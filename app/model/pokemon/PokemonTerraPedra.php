<?php

// Combina as regras de tipo terra e pedra para calcular efetividade de golpes desta variante.
class PokemonTerraPedra extends Pokemon
{
    use TipoTerra, TipoPedra;
}
