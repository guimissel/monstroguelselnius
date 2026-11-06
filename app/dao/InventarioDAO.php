<?php

require_once __DIR__ . "/../model/ItemInventario.php";

class InventarioDAO
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // itens que o jogador tem (quantidade maior que zero)
    public function listar(int $idUsuario): array
    {
        $sql = "SELECT i.*, iu.quantidade
                FROM item_usuario iu
                JOIN item_loja i ON i.id_item_loja = iu.id_item_loja
                WHERE iu.id_usuario = :id AND iu.quantidade > 0
                ORDER BY i.id_item_loja";
        $prepare = $this->pdo->prepare($sql);
        $prepare->execute(["id" => $idUsuario]);

        $itens = [];
        foreach ($prepare->fetchAll() as $linha) {
            $itens[] = new ItemInventario(
                (int) $linha["id_item_loja"],
                $linha["chave"],
                $linha["nome"],
                $linha["descricao"],
                $linha["categoria"],
                (int) $linha["preco"],
                $linha["imagem"],
                (int) $linha["cura_hp"],
                $linha["cura_status"],
                (bool) $linha["reviver"],
                (int) $linha["quantidade"]
            );
        }

        return $itens;
    }

    // gasta 1 unidade do item. retorna false se o jogador nao tem o item
    // (o "quantidade > 0" no WHERE evita ficar negativo mesmo com dois cliques ao mesmo tempo)
    public function usar(int $idUsuario, string $chave): bool
    {
        $sql = "UPDATE item_usuario iu
                JOIN item_loja i ON i.id_item_loja = iu.id_item_loja
                SET iu.quantidade = iu.quantidade - 1
                WHERE iu.id_usuario = :id AND i.chave = :chave AND iu.quantidade > 0";
        $prepare = $this->pdo->prepare($sql);
        $prepare->execute(["id" => $idUsuario, "chave" => $chave]);

        return $prepare->rowCount() > 0;
    }

    public function pokedolares(int $idUsuario): int
    {
        $prepare = $this->pdo->prepare("SELECT pokedolares FROM usuario WHERE id_usuario = :id");
        $prepare->execute(["id" => $idUsuario]);

        return (int) $prepare->fetchColumn();
    }

    // itens que toda conta nova recebe (coluna kit_inicial do item_loja)
    public function darKitInicial(int $idUsuario): void
    {
        $sql = "INSERT IGNORE INTO item_usuario (id_usuario, id_item_loja, quantidade)
                SELECT :id, id_item_loja, kit_inicial FROM item_loja WHERE kit_inicial > 0";
        $prepare = $this->pdo->prepare($sql);
        $prepare->execute(["id" => $idUsuario]);
    }
}
