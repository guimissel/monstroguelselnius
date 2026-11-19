<?php

// Combina as regras de tipo veneno e voador para calcular efetividade de golpes desta variante.
class PokemonVenenoVoador extends Pokemon
{
    use TipoVeneno, TipoVoador;
}
