<?php

// Combina as regras de tipo fada e psíquico para calcular efetividade de golpes desta variante.
class PokemonFadaPsiquico extends Pokemon
{
    use TipoFada, TipoPsiquico;
}
