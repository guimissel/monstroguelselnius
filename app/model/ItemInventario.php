<?php

// um item do catalogo (item_loja) junto com a quantidade que o jogador tem
class ItemInventario
{
    private int $id;
    private string $chave;
    private string $nome;
    private string $descricao;
    private string $categoria;
    private int $preco;
    private string $imagem;
    private int $curaHp;
    private ?string $curaStatus;
    private bool $reviver;
    private int $quantidade;

    public function __construct(
        int $id,
        string $chave,
        string $nome,
        string $descricao,
        string $categoria,
        int $preco,
        string $imagem,
        int $curaHp,
        ?string $curaStatus,
        bool $reviver,
        int $quantidade
    ) {
        $this->id = $id;
        $this->chave = $chave;
        $this->nome = $nome;
        $this->descricao = $descricao;
        $this->categoria = $categoria;
        $this->preco = $preco;
        $this->imagem = $imagem;
        $this->curaHp = $curaHp;
        $this->curaStatus = $curaStatus;
        $this->reviver = $reviver;
        $this->quantidade = $quantidade;
    }

    public function getChave(): string
    {
        return $this->chave;
    }

    public function getQuantidade(): int
    {
        return $this->quantidade;
    }

    // formato que vai para o javascript
    public function paraArray(): array
    {
        // cura_status no banco: null, 'todos' ou 'poison,burn'
        $curaStatus = null;
        if ($this->curaStatus === "todos") {
            $curaStatus = "todos";
        } else if ($this->curaStatus !== null && $this->curaStatus !== "") {
            $curaStatus = explode(",", $this->curaStatus);
        }

        return [
            "id" => $this->id,
            "chave" => $this->chave,
            "nome" => $this->nome,
            "descricao" => $this->descricao,
            "categoria" => $this->categoria,
            "preco" => $this->preco,
            "imagem" => $this->imagem,
            "quantidade" => $this->quantidade,
            "efeito" => [
                "curaHp" => $this->curaHp,      // 0 = nao cura, -1 = cura tudo
                "curaStatus" => $curaStatus,    // null, "todos" ou lista
                "reviver" => $this->reviver
            ]
        ];
    }
}
