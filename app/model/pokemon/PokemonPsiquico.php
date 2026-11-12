<?php

// Pokemon do tipo Psíquico (ex: Abra, Kadabra, Alakazam).
// Os tipos vem das traits: cada uma traz as fraquezas, resistencias e imunidades do seu tipo.
class PokemonPsiquico extends Pokemon
{
    use TipoPsiquico;

    public function tipos(): array
    {
        return ['psychic'];
    }
}
