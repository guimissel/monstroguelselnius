<?php

// Combina as regras de tipo inseto e planta para calcular efetividade de golpes desta variante.
class PokemonInsetoPlanta extends Pokemon
{
    use TipoInseto, TipoPlanta;
}
