<?php

// Combina as regras de tipo psíquico e fada para calcular efetividade de golpes desta variante.
class PokemonPsiquicoFada extends Pokemon
{
    use TipoPsiquico, TipoFada;
}
