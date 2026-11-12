<?php

// O jogador visto como treinador pokemon.
//   COMPOSICAO: a Carteira e criada aqui dentro.
//   AGREGACAO : a Equipe junta pokemons que existem por conta propria.
//   ASSOCIACAO: o treinador conhece os pokemons da colecao dele (e eles nao sabem dele).
class Treinador
{
    private int $id;
    private string $nome;
    private Carteira $carteira;
    private Equipe $equipe;
    private array $colecao = [];

    public function __construct(int $id, string $nome, int $pokedolares)
    {
        $this->id = $id;
        $this->nome = $nome;
        $this->carteira = new Carteira($pokedolares);
        $this->equipe = new Equipe();
    }

    public function getId(): int { return $this->id; }
    public function getNome(): string { return $this->nome; }
    public function getCarteira(): Carteira { return $this->carteira; }
    public function getEquipe(): Equipe { return $this->equipe; }
    public function getColecao(): array { return $this->colecao; }

    public function adicionarNaColecao(Pokemon $pokemon): void
    {
        $this->colecao[$pokemon->getId()] = $pokemon;
    }

    // coloca na equipe um pokemon que o treinador tem
    public function escalar(int $idPokemon): void
    {
        if (!isset($this->colecao[$idPokemon])) {
            throw new RegraDeJogoException("Voce nao tem esse pokemon", 404);
        }
        $this->equipe->adicionar($this->colecao[$idPokemon]);
    }

    public function paraArray(): array
    {
        return [
            "id" => $this->id,
            "nome" => $this->nome,
            "pokedolares" => $this->carteira->getSaldo(),
            "equipe" => [
                "pokemons" => array_map(fn (Pokemon $p) => $p->paraArray(), $this->equipe->todos()),
                "poderTotal" => $this->equipe->poderTotal(),
                "maisRapido" => $this->equipe->maisRapido()?->getNome(),
                "fraquezasEmComum" => $this->equipe->fraquezasEmComum()
            ],
            "colecao" => count($this->colecao)
        ];
    }
}
