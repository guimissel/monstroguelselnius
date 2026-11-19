<?php

// Combina as regras de tipo voador e normal para calcular efetividade de golpes desta variante.
class PokemonVoadorNormal extends Pokemon
{
    use TipoVoador, TipoNormal;
}
