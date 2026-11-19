<?php

// Combina as regras de tipo psíquico e planta para calcular efetividade de golpes desta variante.
class PokemonPsiquicoPlanta extends Pokemon
{
    use TipoPsiquico, TipoPlanta;
}
