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
    public function historico(int $idUsuario, int $limite = 10): array
    {
        $prepare = $this->pdo->prepare(
            "SELECT id_batalha, adversario, status, recompensa, iniciada_em, duracao_segundos
             FROM vw_historico_batalhas WHERE id_usuario = :id
             ORDER BY id_batalha DESC LIMIT " . (int) $limite
        );
        $prepare->execute(["id" => $idUsuario]);

        return array_map(fn (array $l) => [
            "id" => (int) $l["id_batalha"],
            "adversario" => $l["adversario"],
            "status" => $l["status"],
            "recompensa" => (int) $l["recompensa"],
            "iniciadaEm" => $l["iniciada_em"],
            "duracao" => $l["duracao_segundos"] === null ? null : (int) $l["duracao_segundos"]
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
            "naEquipe" => (bool) $l["na_equipe"]
        ], $prepare->fetchAll());
    }

    // itens (vw_inventario_usuario)
    public function itens(int $idUsuario): array
    {
        $prepare = $this->pdo->prepare(
            "SELECT item, quantidade FROM vw_inventario_usuario WHERE id_usuario = :id ORDER BY item"
        );
        $prepare->execute(["id" => $idUsuario]);

        return array_map(fn (array $l) => ["nome" => $l["item"], "quantidade" => (int) $l["quantidade"]], $prepare->fetchAll());
    }

    // posicao no ranking de vitorias (1 = quem mais venceu)
    public function posicao(int $vitorias): int
    {
        $prepare = $this->pdo->prepare("SELECT COUNT(*) + 1 FROM vw_ranking_jogadores WHERE vitorias > :v");
        $prepare->execute(["v" => $vitorias]);

        return (int) $prepare->fetchColumn();
    }
}
