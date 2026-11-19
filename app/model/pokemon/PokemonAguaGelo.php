<?php

// Combina as regras de tipo água e gelo para calcular efetividade de golpes desta variante.
class PokemonAguaGelo extends Pokemon
{
    use TipoAgua, TipoGelo;
}
