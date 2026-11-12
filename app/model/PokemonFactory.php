<?php

// Escolhe a classe certa para cada pokemon pelos tipos dele:
// ["water", "poison"] -> PokemonAguaVeneno, ["fire"] -> PokemonFogo...
class PokemonFactory
{
    // $dados: linha da vw_pokedex (o campo "tipos" vem como "water,poison")
    public static function criar(array $dados, array $imagens = []): Pokemon
    {
        $tipos = array_filter(explode(",", (string) ($dados["tipos"] ?? "")));

        $nomeDaClasse = "Pokemon";
        foreach ($tipos as $tipo) {
            if (!isset(Pokemon::TIPOS[$tipo])) {
                throw new InvalidArgumentException("Tipo desconhecido: " . $tipo);
            }
            $nomeDaClasse .= Pokemon::TIPOS[$tipo];
        }

        if ($nomeDaClasse === "Pokemon" || !class_exists($nomeDaClasse)) {
            throw new RuntimeException("Nao existe classe para os tipos do pokemon " . ($dados["nome"] ?? "?"));
        }

        return new $nomeDaClasse($dados, $imagens);
    }
}
