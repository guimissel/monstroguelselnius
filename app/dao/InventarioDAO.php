<?php

require_once __DIR__ . "/../model/ItemInventario.php";

class InventarioDAO extends DAO
{
    private const IDS_LENDARIOS = [144, 145, 146, 150];

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
                WHERE iu.id_usuario = :id AND i.chave = :chave AND iu.quantidade > 0
                  AND i.categoria NOT IN ('evolucao', 'pokebola')";
        $prepare = $this->pdo->prepare($sql);
        $prepare->execute(["id" => $idUsuario, "chave" => $chave]);

        return $prepare->rowCount() > 0;
    }

    // Sorteia e concede um Pokémon usando transação para consumir a Pokébola sem corrida concorrente.
    public function abrirPokebola(int $idUsuario): array
    {
        $this->pdo->beginTransaction();

        try {
            $prepare = $this->pdo->prepare(
                "SELECT iu.id_item_loja, iu.quantidade
                 FROM item_usuario iu
                 JOIN item_loja i ON i.id_item_loja = iu.id_item_loja
                 WHERE iu.id_usuario = :usuario AND i.chave = 'pokebola'
                 FOR UPDATE"
            );
            $prepare->execute(["usuario" => $idUsuario]);
            $item = $prepare->fetch();
            // O bloqueio da linha impede que duas aberturas consumam a mesma unidade.
            if (!$item || (int) $item["quantidade"] < 1) {
                throw new RegraDeJogoException("Voce nao tem uma Pokebola Surpresa", 409);
            }

            $prepare = $this->pdo->prepare(
                "SELECT p.id_pokemon, p.nome,
                        EXISTS (SELECT 1 FROM pokemon_usuario pu
                                WHERE pu.id_usuario = :usuario AND pu.id_pokemon = p.id_pokemon) AS ja_tinha
                 FROM vw_pokedex p"
            );
            $prepare->execute(["usuario" => $idUsuario]);
            $lendarios = [];
            $comuns = [];
            // Separa as categorias para aplicar a probabilidade especial dos lendários.
            foreach ($prepare->fetchAll() as $opcao) {
                if (in_array((int) $opcao["id_pokemon"], self::IDS_LENDARIOS, true)) {
                    $lendarios[] = $opcao;
                } else {
                    $comuns[] = $opcao;
                }
            }

            if (!$lendarios || !$comuns) {
                throw new RuntimeException("Catalogo sem Pokemons lendarios ou comuns para a Pokebola");
            }

            $lendario = random_int(1, 100) === 1;
            $opcoes = $lendario ? $lendarios : $comuns;
            $novos = array_values(array_filter($opcoes, fn (array $opcao) => !(bool) $opcao["ja_tinha"]));
            // Prefere recompensas ainda não possuídas quando houver opções disponíveis.
            if ($novos) {
                $opcoes = $novos;
            }
            $pokemon = $opcoes[random_int(0, count($opcoes) - 1)];

            $prepare = $this->pdo->prepare(
                "UPDATE item_usuario SET quantidade = quantidade - 1
                 WHERE id_usuario = :usuario AND id_item_loja = :item AND quantidade > 0"
            );
            $prepare->execute(["usuario" => $idUsuario, "item" => $item["id_item_loja"]]);
            // Confirma que a unidade continuava disponível antes de conceder o Pokémon.
            if ($prepare->rowCount() !== 1) {
                throw new RegraDeJogoException("Voce nao tem uma Pokebola Surpresa", 409);
            }

            $prepare = $this->pdo->prepare(
                "INSERT IGNORE INTO pokemon_usuario (id_usuario, id_pokemon) VALUES (:usuario, :pokemon)"
            );
            $prepare->execute(["usuario" => $idUsuario, "pokemon" => $pokemon["id_pokemon"]]);

            $prepare = $this->pdo->prepare(
                "SELECT quantidade FROM item_usuario WHERE id_usuario = :usuario AND id_item_loja = :item"
            );
            $prepare->execute(["usuario" => $idUsuario, "item" => $item["id_item_loja"]]);
            $quantidade = (int) $prepare->fetchColumn();

            $this->pdo->commit();

            return [
                "pokemon" => [
                    "id" => (int) $pokemon["id_pokemon"],
                    "nome" => $pokemon["nome"],
                    "lendario" => $lendario,
                    "jaTinha" => (bool) $pokemon["ja_tinha"]
                ],
                "quantidade" => $quantidade
            ];
        } catch (Throwable $erro) {
            // Desfaz tanto o consumo quanto a concessão quando qualquer etapa falha.
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $erro;
        }
    }

    // Consulta o saldo corrente usado nas telas de loja e inventário.
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
