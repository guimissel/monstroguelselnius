<?php

// Combina as regras de tipo veneno e terra para calcular efetividade de golpes desta variante.
class PokemonVenenoTerra extends Pokemon
{
    use TipoVeneno, TipoTerra;
}
