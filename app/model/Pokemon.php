<?php

// Pokemon: classe abstrata. Cada tipo de pokemon (PokemonFogo, PokemonAguaVeneno...) estende esta classe
// e ganha as fraquezas/resistencias/imunidades pelas traits de tipo (app/model/tipos).
abstract class Pokemon implements Combatente
{
    // nome do tipo (como vem do banco) -> final do nome das traits e dos metodos (fraquezasFogo, TipoFogo...)
    public const TIPOS = [
        "normal" => "Normal", "fighting" => "Lutador", "flying" => "Voador", "poison" => "Veneno",
        "ground" => "Terra", "rock" => "Pedra", "bug" => "Inseto", "ghost" => "Fantasma",
        "steel" => "Aco", "fire" => "Fogo", "water" => "Agua", "grass" => "Planta",
        "electric" => "Eletrico", "psychic" => "Psiquico", "ice" => "Gelo", "dragon" => "Dragao",
        "dark" => "Sombrio", "fairy" => "Fada"
    ];

    // atributos (encapsulados: so se mexe por metodo)
    private int $id;
    private string $nome;
    private int $hpMaximo;
    private int $hpAtual;
    private int $ataque;
    private int $defesa;
    private int $ataqueEspecial;
    private int $defesaEspecial;
    private int $velocidade;
    private ?int $idEvolucao;
    private ?string $nomeEvolucao;

    // $dados e uma linha da view vw_pokedex
    public function __construct(array $dados)
    {
        $this->id = (int) $dados["id_pokemon"];
        $this->nome = $dados["nome"];
        $this->hpMaximo = (int) $dados["vida_base"];
        $this->hpAtual = $this->hpMaximo;
        $this->ataque = (int) $dados["ataque_base"];
        $this->defesa = (int) $dados["defesa_base"];
        $this->ataqueEspecial = (int) $dados["ataque_esp_base"];
        $this->defesaEspecial = (int) $dados["defesa_esp_base"];
        $this->velocidade = (int) $dados["velocidade_base"];
        $this->idEvolucao = isset($dados["id_evolucao"]) ? (int) $dados["id_evolucao"] : null;
        $this->nomeEvolucao = $dados["evolucao_nome"] ?? null;
    }

    // cada classe de tipo diz quais sao os tipos dela, ex: ["water", "poison"]
    abstract public function tipos(): array;

    public function getId(): int { return $this->id; }
    public function getNome(): string { return $this->nome; }
    public function getHpMaximo(): int { return $this->hpMaximo; }
    public function getHpAtual(): int { return $this->hpAtual; }
    public function getAtaque(): int { return $this->ataque; }
    public function getDefesa(): int { return $this->defesa; }
    public function getAtaqueEspecial(): int { return $this->ataqueEspecial; }
    public function getDefesaEspecial(): int { return $this->defesaEspecial; }
    public function getVelocidade(): int { return $this->velocidade; }
    public function getIdEvolucao(): ?int { return $this->idEvolucao; }
    public function getNomeEvolucao(): ?string { return $this->nomeEvolucao; }

    public function podeEvoluir(): bool
    {
        return $this->idEvolucao !== null;
    }

    // soma de todos os atributos: serve para comparar a forca dos pokemons
    public function poder(): int
    {
        return $this->hpMaximo + $this->ataque + $this->defesa
            + $this->ataqueEspecial + $this->defesaEspecial + $this->velocidade;
    }

    public function estaDesmaiado(): bool
    {
        return $this->hpAtual <= 0;
    }

    public function receberDano(int $dano): int
    {
        $tirado = min(max($dano, 0), $this->hpAtual);
        $this->hpAtual -= $tirado;
        return $tirado;
    }

    public function curar(int $quantidade): int
    {
        $antes = $this->hpAtual;
        $this->hpAtual = min($this->hpMaximo, $this->hpAtual + max($quantidade, 0));
        return $this->hpAtual - $antes;
    }

    // multiplicador de dano de um golpe deste tipo contra este pokemon.
    // Passa por cada tipo do pokemon e chama o metodo que a trait daquele tipo trouxe.
    public function multiplicadorContra(string $tipoDoGolpe): float
    {
        $multiplicador = 1.0;

        foreach ($this->tipos() as $tipo) {
            $sufixo = self::TIPOS[$tipo];

            if (in_array($tipoDoGolpe, $this->{"imunidades" . $sufixo}(), true)) {
                return 0.0;
            }
            if (in_array($tipoDoGolpe, $this->{"fraquezas" . $sufixo}(), true)) {
                $multiplicador *= 2;
            } elseif (in_array($tipoDoGolpe, $this->{"resistencias" . $sufixo}(), true)) {
                $multiplicador /= 2;
            }
        }

        return $multiplicador;
    }

    // golpes (por tipo) que causam mais de 100% de dano
    public function fraquezas(): array
    {
        return $this->tiposCom(fn (float $m) => $m > 1);
    }

    // golpes que causam menos de 100%, mas ainda causam dano
    public function resistencias(): array
    {
        return $this->tiposCom(fn (float $m) => $m > 0 && $m < 1);
    }

    // golpes que nao causam dano nenhum
    public function imunidades(): array
    {
        return $this->tiposCom(fn (float $m) => $m === 0.0);
    }

    private function tiposCom(callable $condicao): array
    {
        $lista = [];
        foreach (array_keys(self::TIPOS) as $tipoDoGolpe) {
            if ($condicao($this->multiplicadorContra($tipoDoGolpe))) {
                $lista[] = $tipoDoGolpe;
            }
        }
        return $lista;
    }

    // para mandar como JSON
    public function paraArray(): array
    {
        return [
            "id" => $this->id,
            "nome" => $this->nome,
            "tipos" => $this->tipos(),
            "stats" => [
                "hp" => $this->hpMaximo,
                "ataque" => $this->ataque,
                "defesa" => $this->defesa,
                "ataqueEspecial" => $this->ataqueEspecial,
                "defesaEspecial" => $this->defesaEspecial,
                "velocidade" => $this->velocidade
            ],
            "poder" => $this->poder(),
            "fraquezas" => $this->fraquezas(),
            "resistencias" => $this->resistencias(),
            "imunidades" => $this->imunidades(),
            "evolucao" => $this->idEvolucao === null ? null : ["id" => $this->idEvolucao, "nome" => $this->nomeEvolucao]
        ];
    }
}
