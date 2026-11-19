<?php

// Combina as regras de tipo água e voador para calcular efetividade de golpes desta variante.
class PokemonAguaVoador extends Pokemon
{
    use TipoAgua, TipoVoador;
}
