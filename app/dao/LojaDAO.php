<?php

require_once __DIR__ . "/../model/ItemInventario.php";

class LojaDAO
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // todos os itens a venda, com a quantidade que o jogador ja tem (pode ser 0)
    public function listar(int $idUsuario): array
    {
        $sql = "SELECT i.*, COALESCE(iu.quantidade, 0) AS quantidade
                FROM item_loja i
                LEFT JOIN item_usuario iu
                    ON iu.id_item_loja = i.id_item_loja AND iu.id_usuario = :id
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

    // compra "quantidade" unidades do item: desconta o dinheiro e coloca no inventario.
    // tudo na mesma transaction, entao nunca cobra sem entregar (nem entrega sem cobrar).
    // erros esperados saem como RegraDeJogoException (o codigo e o status http)
    public function comprar(int $idUsuario, string $chave, int $quantidade): array
    {
        $this->pdo->beginTransaction();

        try {
            // item que o jogador quer
            $prepare = $this->pdo->prepare("SELECT id_item_loja, preco FROM item_loja WHERE chave = :chave");
            $prepare->execute(["chave" => $chave]);
            $item = $prepare->fetch();

            if (!$item) {
                throw new RegraDeJogoException("Esse item nao existe", 404);
            }

            $total = (int) $item["preco"] * $quantidade;

            // trava a linha do jogador ate o fim da transaction (evita gastar o mesmo dinheiro 2 vezes)
            $prepare = $this->pdo->prepare("SELECT pokedolares FROM usuario WHERE id_usuario = :id FOR UPDATE");
            $prepare->execute(["id" => $idUsuario]);
            $saldo = (int) $prepare->fetchColumn();

            if ($saldo < $total) {
                throw new SaldoInsuficienteException($saldo, $total);
            }

            $prepare = $this->pdo->prepare("UPDATE usuario SET pokedolares = pokedolares - :total WHERE id_usuario = :id");
            $prepare->execute(["total" => $total, "id" => $idUsuario]);

            // se ja tem o item soma, senao cria a linha
            $prepare = $this->pdo->prepare(
                "INSERT INTO item_usuario (id_usuario, id_item_loja, quantidade)
                 VALUES (:id, :item, :qtd1)
                 ON DUPLICATE KEY UPDATE quantidade = quantidade + :qtd2"
            );
            $prepare->execute([
                "id" => $idUsuario,
                "item" => $item["id_item_loja"],
                "qtd1" => $quantidade,
                "qtd2" => $quantidade
            ]);

            // historico da loja (quem comprou o que e por quanto)
            $prepare = $this->pdo->prepare(
                "INSERT INTO log_loja (id_usuario, id_item_loja, quantidade, preco_unitario, total)
                 VALUES (:id, :item, :qtd, :preco, :total)"
            );
            $prepare->execute([
                "id" => $idUsuario,
                "item" => $item["id_item_loja"],
                "qtd" => $quantidade,
                "preco" => $item["preco"],
                "total" => $total
            ]);

            $prepare = $this->pdo->prepare(
                "SELECT quantidade FROM item_usuario WHERE id_usuario = :id AND id_item_loja = :item"
            );
            $prepare->execute(["id" => $idUsuario, "item" => $item["id_item_loja"]]);
            $novaQuantidade = (int) $prepare->fetchColumn();

            $this->pdo->commit();

            return [
                "pokedolares" => $saldo - $total,
                "quantidade" => $novaQuantidade
            ];
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $erro;
        }
    }
}
