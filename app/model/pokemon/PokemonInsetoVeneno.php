<?php

// Combina as regras de tipo inseto e veneno para calcular efetividade de golpes desta variante.
class PokemonInsetoVeneno extends Pokemon
{
    use TipoInseto, TipoVeneno;
}
