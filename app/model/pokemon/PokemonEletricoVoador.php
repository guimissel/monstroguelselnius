<?php

// Combina as regras de tipo elétrico e voador para calcular efetividade de golpes desta variante.
class PokemonEletricoVoador extends Pokemon
{
    use TipoEletrico, TipoVoador;
}
