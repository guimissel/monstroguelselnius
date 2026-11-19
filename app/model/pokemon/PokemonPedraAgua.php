<?php

// Combina as regras de tipo pedra e água para calcular efetividade de golpes desta variante.
class PokemonPedraAgua extends Pokemon
{
    use TipoPedra, TipoAgua;
}
