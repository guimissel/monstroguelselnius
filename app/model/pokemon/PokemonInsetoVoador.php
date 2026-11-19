<?php

// Combina as regras de tipo inseto e voador para calcular efetividade de golpes desta variante.
class PokemonInsetoVoador extends Pokemon
{
    use TipoInseto, TipoVoador;
}
