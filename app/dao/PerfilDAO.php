<?php

// Consultas da tela de perfil. A maior parte usa as views do banco (vw_*).
class PerfilDAO
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // jogador montado como Treinador (carteira, colecao e equipe)
    public function treinador(int $idUsuario): ?Treinador
    {
        $prepare = $this->pdo->prepare("SELECT id_usuario, nome, pokedolares FROM usuario WHERE id_usuario = :id");
        $prepare->execute(["id" => $idUsuario]);
        $linha = $prepare->fetch();
        if (!$linha) {
            return null;
        }

        $treinador = new Treinador((int) $linha["id_usuario"], $linha["nome"], (int) $linha["pokedolares"]);

        $pokemonDAO = new PokemonDAO($this->pdo);
        foreach ($pokemonDAO->daColecao($idUsuario) as $pokemon) {
            $treinador->adicionarNaColecao($pokemon);
        }
        foreach ($pokemonDAO->daEquipe($idUsuario)->todos() as $pokemon) {
            $treinador->getEquipe()->adicionar($pokemon);
        }

        return $treinador;
    }

    // vitorias, derrotas, fugas e total ganho (vw_ranking_jogadores)
    public function placar(int $idUsuario): array
    {
        $prepare = $this->pdo->prepare("SELECT * FROM vw_ranking_jogadores WHERE id_usuario = :id");
        $prepare->execute(["id" => $idUsuario]);
        $linha = $prepare->fetch() ?: [];

        return [
            "batalhas" => (int) ($linha["batalhas"] ?? 0),
            "vitorias" => (int) ($linha["vitorias"] ?? 0),
            "derrotas" => (int) ($linha["derrotas"] ?? 0),
            "fugas" => (int) ($linha["fugas"] ?? 0),
            "totalGanho" => (int) ($linha["total_ganho"] ?? 0)
        ];
    }

    // as 5 melhores campanhas (mais vitorias)
    public function ranking(): array
    {
        $linhas = $this->pdo->query(
            "SELECT nome, vitorias, batalhas, total_ganho FROM vw_ranking_jogadores
             WHERE batalhas > 0 ORDER BY vitorias DESC, total_ganho DESC, nome LIMIT 5"
        )->fetchAll();

        return array_map(fn (array $l) => [
            "nome" => $l["nome"],
            "vitorias" => (int) $l["vitorias"],
            "batalhas" => (int) $l["batalhas"],
            "totalGanho" => (int) $l["total_ganho"]
        ], $linhas);
    }

    // ultimas batalhas (vw_historico_batalhas)
    public function historico(int $idUsuario, ?int $limite = 10, bool $somenteConcluidas = false): array
    {
        $sql =
            "SELECT h.id_batalha, h.adversario, h.status, h.recompensa, h.iniciada_em, h.finalizada_em,
                    h.duracao_segundos, b.is_boss
             FROM vw_historico_batalhas h
             JOIN batalha b ON b.id_batalha = h.id_batalha
             WHERE h.id_usuario = :id
             " . ($somenteConcluidas ? "AND h.status <> 'em_andamento' " : "") . "
             ORDER BY h.id_batalha DESC";
        if ($limite !== null) {
            $sql .= " LIMIT " . max(0, $limite);
        }

        $prepare = $this->pdo->prepare($sql);
        $prepare->execute(["id" => $idUsuario]);

        return array_map(fn (array $l) => [
            "id" => (int) $l["id_batalha"],
            "adversario" => $l["adversario"],
            "status" => $l["status"],
            "recompensa" => (int) $l["recompensa"],
            "iniciadaEm" => $l["iniciada_em"],
            "finalizadaEm" => $l["finalizada_em"],
            "duracao" => $l["duracao_segundos"] === null ? null : (int) $l["duracao_segundos"],
            "chefe" => (bool) $l["is_boss"]
        ], $prepare->fetchAll());
    }

    // colecao com a marca de quem esta na equipe (vw_colecao_usuario)
    public function colecao(int $idUsuario): array
    {
        $prepare = $this->pdo->prepare(
            "SELECT id_pokemon, pokemon, conquistado_em, na_equipe FROM vw_colecao_usuario
             WHERE id_usuario = :id ORDER BY na_equipe DESC, id_pokemon"
        );
        $prepare->execute(["id" => $idUsuario]);

        return array_map(fn (array $l) => [
            "id" => (int) $l["id_pokemon"],
            "nome" => $l["pokemon"],
            "naEquipe" => (bool) $l["na_equipe"],
            "conquistadoEm" => $l["conquistado_em"]
        ], $prepare->fetchAll());
    }

    // itens (vw_inventario_usuario)
    public function itens(int $idUsuario): array
    {
        $prepare = $this->pdo->prepare(
            "SELECT chave, item, quantidade FROM vw_inventario_usuario WHERE id_usuario = :id ORDER BY item"
        );
        $prepare->execute(["id" => $idUsuario]);

        return array_map(fn (array $l) => [
            "chave" => $l["chave"],
            "nome" => $l["item"],
            "quantidade" => (int) $l["quantidade"]
        ], $prepare->fetchAll());
    }

    public function importarProgresso(
        int $idUsuario,
        int $pokedolares,
        array $colecao,
        array $equipe,
        array $itens,
        array $historico
    ): void {
        $catalogoPokemon = $this->pdo->query("SELECT id_pokemon FROM vw_pokedex")
            ->fetchAll(PDO::FETCH_COLUMN);
        $idsPokemonValidos = array_fill_keys(array_map("intval", $catalogoPokemon), true);

        foreach ($colecao as $pokemon) {
            if (!isset($idsPokemonValidos[$pokemon["id"]])) {
                throw new DomainException("O arquivo contém um Pokémon que não existe no catálogo atual");
            }
        }

        $catalogoItens = $this->pdo->query("SELECT id_item_loja, chave, nome FROM item_loja")->fetchAll();
        $itensPorChave = [];
        $itensPorNome = [];
        foreach ($catalogoItens as $item) {
            $itensPorChave[$item["chave"]] = (int) $item["id_item_loja"];
            $itensPorNome[$item["nome"]] = (int) $item["id_item_loja"];
        }

        $itensImportados = [];
        foreach ($itens as $item) {
            $idItem = $item["chave"] !== null
                ? ($itensPorChave[$item["chave"]] ?? null)
                : ($itensPorNome[$item["nome"]] ?? null);
            if ($idItem === null) {
                throw new DomainException("O arquivo contém um item que não existe na loja atual");
            }
            if (isset($itensImportados[$idItem])) {
                throw new DomainException("O arquivo contém itens duplicados");
            }
            $itensImportados[$idItem] = $item["quantidade"];
        }

        $this->pdo->beginTransaction();
        try {
            $prepare = $this->pdo->prepare("SELECT id_usuario FROM usuario WHERE id_usuario = :id FOR UPDATE");
            $prepare->execute(["id" => $idUsuario]);
            if (!$prepare->fetchColumn()) {
                throw new DomainException("Jogador nao encontrado");
            }

            $prepare = $this->pdo->prepare("DELETE FROM batalha WHERE id_usuario = :id");
            $prepare->execute(["id" => $idUsuario]);
            foreach (["equipe_usuario", "pokemon_usuario", "item_usuario"] as $tabela) {
                $prepare = $this->pdo->prepare("DELETE FROM {$tabela} WHERE id_usuario = :id");
                $prepare->execute(["id" => $idUsuario]);
            }

            $prepare = $this->pdo->prepare("UPDATE usuario SET pokedolares = :saldo WHERE id_usuario = :id");
            $prepare->execute(["saldo" => $pokedolares, "id" => $idUsuario]);

            $inserirPokemon = $this->pdo->prepare(
                "INSERT INTO pokemon_usuario (id_usuario, id_pokemon, conquistado_em)
                 VALUES (:usuario, :pokemon, COALESCE(:conquistado, CURRENT_TIMESTAMP))"
            );
            foreach ($colecao as $pokemon) {
                $inserirPokemon->execute([
                    "usuario" => $idUsuario,
                    "pokemon" => $pokemon["id"],
                    "conquistado" => $pokemon["conquistadoEm"]
                ]);
            }

            $inserirEquipe = $this->pdo->prepare(
                "INSERT INTO equipe_usuario (id_usuario, posicao, id_pokemon) VALUES (:usuario, :posicao, :pokemon)"
            );
            foreach ($equipe as $indice => $idPokemon) {
                $inserirEquipe->execute([
                    "usuario" => $idUsuario,
                    "posicao" => $indice + 1,
                    "pokemon" => $idPokemon
                ]);
            }

            $inserirItem = $this->pdo->prepare(
                "INSERT INTO item_usuario (id_usuario, id_item_loja, quantidade) VALUES (:usuario, :item, :quantidade)"
            );
            foreach ($itensImportados as $idItem => $quantidade) {
                if ($quantidade > 0) {
                    $inserirItem->execute([
                        "usuario" => $idUsuario,
                        "item" => $idItem,
                        "quantidade" => $quantidade
                    ]);
                }
            }

            $inserirBatalha = $this->pdo->prepare(
                "INSERT INTO batalha (id_usuario, is_boss, status, recompensa, iniciada_em, finalizada_em)
                 VALUES (:usuario, :chefe, :status, :recompensa, :iniciada, :finalizada)"
            );
            foreach (array_reverse($historico) as $batalha) {
                $inserirBatalha->execute([
                    "usuario" => $idUsuario,
                    "chefe" => (int) $batalha["chefe"],
                    "status" => $batalha["status"],
                    "recompensa" => $batalha["recompensa"],
                    "iniciada" => $batalha["iniciadaEm"],
                    "finalizada" => $batalha["finalizadaEm"]
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $erro;
        }
    }

    // posicao no ranking de vitorias (1 = quem mais venceu)
    public function posicao(int $vitorias): int
    {
        $prepare = $this->pdo->prepare("SELECT COUNT(*) + 1 FROM vw_ranking_jogadores WHERE vitorias > :v");
        $prepare->execute(["v" => $vitorias]);

        return (int) $prepare->fetchColumn();
    }
}
