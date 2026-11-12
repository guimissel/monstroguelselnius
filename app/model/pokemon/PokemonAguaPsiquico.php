<?php

// Pokemon do tipo Água / Psíquico (ex: Slowpoke, Slowbro).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonAguaPsiquico extends Pokemon
{
    use TipoAgua, TipoPsiquico;

    public function tipos(): array
    {
        return ['water', 'psychic'];
    }
}
