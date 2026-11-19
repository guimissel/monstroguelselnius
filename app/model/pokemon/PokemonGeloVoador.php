<?php

// Combina as regras de tipo gelo e voador para calcular efetividade de golpes desta variante.
class PokemonGeloVoador extends Pokemon
{
    use TipoGelo, TipoVoador;
}
