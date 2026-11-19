<?php

// Descobre os tipos pela composição de traits da subclasse, sem duplicar a lista em cada Pokémon.
trait TipoPokemon
{
    // Converte os nomes das traits de tipo para as chaves utilizadas pela Pokédex e pela API.
    public function tipos(): array
    {
        $tipos = [];
        $sufixos = [
            "TipoNormal" => "normal",
            "TipoLutador" => "fighting",
            "TipoVoador" => "flying",
            "TipoVeneno" => "poison",
            "TipoTerra" => "ground",
            "TipoPedra" => "rock",
            "TipoInseto" => "bug",
            "TipoFantasma" => "ghost",
            "TipoAco" => "steel",
            "TipoFogo" => "fire",
            "TipoAgua" => "water",
            "TipoPlanta" => "grass",
            "TipoEletrico" => "electric",
            "TipoPsiquico" => "psychic",
            "TipoGelo" => "ice",
            "TipoDragao" => "dragon",
            "TipoSombrio" => "dark",
            "TipoFada" => "fairy"
        ];

        // Considera apenas traits de tipo conhecidas; outras traits não alteram a tipagem.
        foreach (class_uses(static::class) as $trait) {
            $nome = basename(str_replace("\\", "/", $trait));
            if (isset($sufixos[$nome])) {
                $tipos[] = $sufixos[$nome];
            }
        }

        return $tipos;
    }
}