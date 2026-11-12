<?php

// Equipe de ate 3 pokemons.
// AGREGACAO: a equipe guarda pokemons, mas eles existem sem ela (continuam na colecao do jogador).
class Equipe implements IteratorAggregate, Countable
{
    public const TAMANHO = 3;

    private array $pokemons = [];

    public function __construct(Pokemon ...$pokemons)
    {
        foreach ($pokemons as $pokemon) {
            $this->adicionar($pokemon);
        }
    }

    // SOBRECARGA (o PHP nao tem sobrecarga nativa, entao o __call escolhe a versao pelo tipo do argumento):
    //   adicionar(Pokemon $pokemon)                -> coloca o pokemon na equipe
    //   adicionar(array $linha, array $imagens)    -> monta o pokemon pela linha do banco e coloca
    //   buscar(int $id)                            -> acha o pokemon pelo numero
    //   buscar(string $nome)                       -> acha o pokemon pelo nome
    public function __call(string $metodo, array $argumentos): mixed
    {
        $primeiro = $argumentos[0] ?? null;

        if ($metodo === "adicionar") {
            if ($primeiro instanceof Pokemon) {
                return $this->colocar($primeiro);
            }
            if (is_array($primeiro)) {
                return $this->colocar(PokemonFactory::criar($primeiro, $argumentos[1] ?? []));
            }
            throw new InvalidArgumentException("adicionar() espera um Pokemon ou uma linha do banco");
        }

        if ($metodo === "buscar") {
            foreach ($this->pokemons as $pokemon) {
                if (is_int($primeiro) && $pokemon->getId() === $primeiro) {
                    return $pokemon;
                }
                if (is_string($primeiro) && strcasecmp($pokemon->getNome(), $primeiro) === 0) {
                    return $pokemon;
                }
            }
            return null;
        }

        throw new BadMethodCallException("Metodo " . $metodo . "() nao existe na Equipe");
    }

    private function colocar(Pokemon $pokemon): static
    {
        if (count($this->pokemons) >= self::TAMANHO) {
            throw new RegraDeJogoException("A equipe ja tem " . self::TAMANHO . " pokemons", 409);
        }
        $this->pokemons[] = $pokemon;
        return $this;
    }

    public function todos(): array
    {
        return $this->pokemons;
    }

    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->pokemons);
    }

    public function count(): int
    {
        return count($this->pokemons);
    }

    public function poderTotal(): int
    {
        return array_sum(array_map(fn (Pokemon $p) => $p->poder(), $this->pokemons));
    }

    // quem ataca primeiro numa batalha
    public function maisRapido(): ?Pokemon
    {
        $rapido = null;
        foreach ($this->pokemons as $pokemon) {
            if ($rapido === null || $pokemon->getVelocidade() > $rapido->getVelocidade()) {
                $rapido = $pokemon;
            }
        }
        return $rapido;
    }

    // tipos de golpe que deixam pelo menos 2 pokemons da equipe fracos (cada pokemon chama a sua propria versao: polimorfismo)
    public function fraquezasEmComum(): array
    {
        $contagem = [];
        foreach ($this->pokemons as $pokemon) {
            foreach ($pokemon->fraquezas() as $tipo) {
                $contagem[$tipo] = ($contagem[$tipo] ?? 0) + 1;
            }
        }
        return array_keys(array_filter($contagem, fn (int $n) => $n >= 2));
    }
}
