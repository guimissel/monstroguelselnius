<?php

// Combina as regras de tipo água e pedra para calcular efetividade de golpes desta variante.
class PokemonAguaPedra extends Pokemon
{
    use TipoAgua, TipoPedra;
}
