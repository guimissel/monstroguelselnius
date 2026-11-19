<?php

// Combina as regras de tipo gelo e psíquico para calcular efetividade de golpes desta variante.
class PokemonGeloPsiquico extends Pokemon
{
    use TipoGelo, TipoPsiquico;
}
