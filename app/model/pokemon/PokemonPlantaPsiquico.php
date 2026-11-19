<?php

// Combina as regras de tipo planta e psíquico para calcular efetividade de golpes desta variante.
class PokemonPlantaPsiquico extends Pokemon
{
    use TipoPlanta, TipoPsiquico;
}
