<?php

// Combina as regras de tipo terra e veneno para calcular efetividade de golpes desta variante.
class PokemonTerraVeneno extends Pokemon
{
    use TipoTerra, TipoVeneno;
}
