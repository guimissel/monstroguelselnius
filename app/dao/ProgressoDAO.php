<?php

// Salvar o progresso de uma conta num arquivo (exportar) e carregar esse arquivo em outra conta/computador (importar).
// O arquivo vai assinado (HMAC): se alguem editar o conteudo no bloco de notas, a assinatura nao bate e o jogo recusa.
class ProgressoDAO
{
    private const VERSAO = 1;
    private const SEGREDO = "monstrobolso-progresso-v1";
    private const MAX_ITEM = 999;
    private const MAX_POKEDOLARES = 1000000;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // mesma conta de assinatura na ida e na volta (os dados sempre sao codificados do mesmo jeito)
    private static function assinar(array $dados): string
    {
        $texto = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash_hmac("sha256", $texto, self::SEGREDO);
    }

    // monta o conteudo do arquivo com o progresso atual da conta
    public function exportar(int $idUsuario): array
    {
        $prepare = $this->pdo->prepare("SELECT nome, pokedolares FROM usuario WHERE id_usuario = :id");
        $prepare->execute(["id" => $idUsuario]);
        $usuario = $prepare->fetch();
        if (!$usuario) {
            throw new RegraDeJogoException("Jogador nao encontrado", 404);
        }

        $prepare = $this->pdo->prepare("SELECT id_pokemon FROM pokemon_usuario WHERE id_usuario = :id ORDER BY id_pokemon");
        $prepare->execute(["id" => $idUsuario]);
        $colecao = array_map("intval", $prepare->fetchAll(PDO::FETCH_COLUMN));

        $prepare = $this->pdo->prepare("SELECT id_pokemon FROM equipe_usuario WHERE id_usuario = :id ORDER BY posicao");
        $prepare->execute(["id" => $idUsuario]);
        $equipe = array_map("intval", $prepare->fetchAll(PDO::FETCH_COLUMN));

        $prepare = $this->pdo->prepare(
            "SELECT i.chave, iu.quantidade
             FROM item_usuario iu JOIN item_loja i ON i.id_item_loja = iu.id_item_loja
             WHERE iu.id_usuario = :id AND iu.quantidade > 0
             ORDER BY i.chave"
        );
        $prepare->execute(["id" => $idUsuario]);
        $itens = array_map(
            fn (array $l) => ["chave" => $l["chave"], "quantidade" => (int) $l["quantidade"]],
            $prepare->fetchAll()
        );

        $dados = [
            "pokedolares" => (int) $usuario["pokedolares"],
            "equipe" => $equipe,
            "colecao" => $colecao,
            "itens" => $itens
        ];

        return [
            "jogo" => "MonstroBolso",
            "versao" => self::VERSAO,
            "conta" => $usuario["nome"],
            "salvoEm" => date("Y-m-d H:i:s"),
            "dados" => $dados,
            "assinatura" => self::assinar($dados)
        ];
    }

    // troca o progresso da conta pelo do arquivo. Tudo numa transaction: ou entra tudo ou nada muda.
    // erros esperados saem como RegraDeJogoException 400 (arquivo invalido, alterado, com pokemon ou item que nao existe...)
    public function importar(int $idUsuario, mixed $arquivo): array
    {
        $dados = $this->validar($arquivo);

        $this->pdo->beginTransaction();

        try {
            $prepare = $this->pdo->prepare("UPDATE usuario SET pokedolares = :p WHERE id_usuario = :id");
            $prepare->execute(["p" => $dados["pokedolares"], "id" => $idUsuario]);

            // equipe e colecao: apaga as atuais e coloca as do arquivo
            $this->pdo->prepare("DELETE FROM equipe_usuario WHERE id_usuario = :id")->execute(["id" => $idUsuario]);
            $this->pdo->prepare("DELETE FROM pokemon_usuario WHERE id_usuario = :id")->execute(["id" => $idUsuario]);

            $inserir = $this->pdo->prepare("INSERT INTO pokemon_usuario (id_usuario, id_pokemon) VALUES (:id, :pokemon)");
            foreach ($dados["colecao"] as $idPokemon) {
                $inserir->execute(["id" => $idUsuario, "pokemon" => $idPokemon]);
            }

            $inserir = $this->pdo->prepare(
                "INSERT INTO equipe_usuario (id_usuario, posicao, id_pokemon) VALUES (:id, :posicao, :pokemon)"
            );
            foreach ($dados["equipe"] as $indice => $idPokemon) {
                $inserir->execute(["id" => $idUsuario, "posicao" => $indice + 1, "pokemon" => $idPokemon]);
            }

            // itens: zera todos e coloca as quantidades do arquivo
            $this->pdo->prepare("UPDATE item_usuario SET quantidade = 0 WHERE id_usuario = :id")->execute(["id" => $idUsuario]);

            $inserir = $this->pdo->prepare(
                "INSERT INTO item_usuario (id_usuario, id_item_loja, quantidade)
                 SELECT :id, id_item_loja, :qtd FROM item_loja WHERE chave = :chave
                 ON DUPLICATE KEY UPDATE quantidade = VALUES(quantidade)"
            );
            foreach ($dados["itens"] as $item) {
                $inserir->execute(["id" => $idUsuario, "qtd" => $item["quantidade"], "chave" => $item["chave"]]);
            }

            $this->pdo->commit();

            return [
                "pokedolares" => $dados["pokedolares"],
                "pokemons" => count($dados["colecao"]),
                "itens" => array_sum(array_column($dados["itens"], "quantidade"))
            ];
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $erro;
        }
    }

    // confere o arquivo inteiro antes de mexer em qualquer coisa. Devolve so os "dados" ja limpos
    private function validar(mixed $arquivo): array
    {
        if (!is_array($arquivo) || ($arquivo["jogo"] ?? null) !== "MonstroBolso"
            || ($arquivo["versao"] ?? null) !== self::VERSAO || !is_array($arquivo["dados"] ?? null)) {
            throw new RegraDeJogoException("Esse arquivo nao e um progresso do MonstroBolso", 400);
        }

        $dados = $arquivo["dados"];
        $assinatura = $arquivo["assinatura"] ?? "";
        if (!is_string($assinatura) || !hash_equals(self::assinar($dados), $assinatura)) {
            throw new RegraDeJogoException("Esse arquivo foi alterado e nao pode ser carregado", 400);
        }

        $pokedolares = $dados["pokedolares"] ?? null;
        $colecao = $dados["colecao"] ?? null;
        $equipe = $dados["equipe"] ?? null;
        $itens = $dados["itens"] ?? null;

        if (!is_int($pokedolares) || $pokedolares < 0 || $pokedolares > self::MAX_POKEDOLARES
            || !is_array($colecao) || !is_array($equipe) || !is_array($itens)) {
            throw new RegraDeJogoException("O arquivo de progresso esta incompleto", 400);
        }

        // pokemons: tem que existir no banco, sem repetir; a equipe tem 0 ou 3 e sai da colecao
        if (count($colecao) > 200 || count($colecao) !== count(array_unique($colecao))) {
            throw new RegraDeJogoException("Colecao invalida no arquivo", 400);
        }
        foreach (array_merge($colecao, $equipe) as $id) {
            if (!is_int($id)) {
                throw new RegraDeJogoException("Colecao invalida no arquivo", 400);
            }
        }
        if (count($colecao) > 0) {
            $marcas = implode(",", array_fill(0, count($colecao), "?"));
            $prepare = $this->pdo->prepare("SELECT COUNT(*) FROM monstro WHERE id_monstro IN ($marcas)");
            $prepare->execute($colecao);
            if ((int) $prepare->fetchColumn() !== count($colecao)) {
                throw new RegraDeJogoException("O arquivo tem pokemon que nao existe neste jogo", 400);
            }
        }
        if (!in_array(count($equipe), [0, 3], true) || count($equipe) !== count(array_unique($equipe))
            || count(array_diff($equipe, $colecao)) > 0) {
            throw new RegraDeJogoException("Equipe invalida no arquivo", 400);
        }

        // itens: tem que existir na loja e a quantidade tem limite
        $limpos = [];
        foreach ($itens as $item) {
            $chave = is_array($item) ? ($item["chave"] ?? null) : null;
            $quantidade = is_array($item) ? ($item["quantidade"] ?? null) : null;
            if (!is_string($chave) || !is_int($quantidade) || $quantidade < 0 || $quantidade > self::MAX_ITEM) {
                throw new RegraDeJogoException("Itens invalidos no arquivo", 400);
            }

            $prepare = $this->pdo->prepare("SELECT 1 FROM item_loja WHERE chave = :chave");
            $prepare->execute(["chave" => $chave]);
            if (!$prepare->fetchColumn()) {
                throw new RegraDeJogoException("O arquivo tem um item que nao existe neste jogo (" . $chave . ")", 400);
            }
            $limpos[] = ["chave" => $chave, "quantidade" => $quantidade];
        }

        return ["pokedolares" => $pokedolares, "colecao" => $colecao, "equipe" => $equipe, "itens" => $limpos];
    }
}
