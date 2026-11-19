<?php

// Combina as regras de tipo planta e inseto para calcular efetividade de golpes desta variante.
class PokemonPlantaInseto extends Pokemon
{
    use TipoPlanta, TipoInseto;
}
