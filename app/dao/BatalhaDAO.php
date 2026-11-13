<?php

class BatalhaDAO
{
    // uma batalha de verdade dura mais que isso (so contando as mensagens do log)
    private const SEGUNDOS_MINIMOS = 10;

    // depois de tantas batalhas normais, a proxima e contra o chefe
    private const BATALHAS_PARA_CHEFE = 7;

    // nivel dos pokemons (quanto menor, mais fracos). O chefe e um pouco mais forte
    public const NIVEL_NORMAL = 10;
    public const NIVEL_CHEFE = 11;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // a tabela batalha numera sozinha (AUTO_INCREMENT)? o app/database/schema.sql configura isso
    private function numeraSozinha(): bool
    {
        $prepare = $this->pdo->query(
            "SELECT EXTRA FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'batalha' AND COLUMN_NAME = 'id_batalha'"
        );
        return stripos((string) $prepare->fetchColumn(), "auto_increment") !== false;
    }

    // cria a linha da batalha e devolve o numero dela.
    // normalmente o banco numera sozinho. se a tabela estiver sem AUTO_INCREMENT (sql nao rodou direito),
    // o numero e calculado aqui (maior + 1) para o jogo nao travar com "Duplicate entry '0'"
    private function inserirBatalha(int $idUsuario, bool $chefe): int
    {
        if ($this->numeraSozinha()) {
            $prepare = $this->pdo->prepare("INSERT INTO batalha (id_usuario, is_boss) VALUES (:id, :chefe)");
            $prepare->execute(["id" => $idUsuario, "chefe" => (int) $chefe]);

            return (int) $this->pdo->lastInsertId();
        }

        for ($tentativa = 0; $tentativa < 10; $tentativa++) {
            $proximo = (int) $this->pdo->query("SELECT COALESCE(MAX(id_batalha), 0) + 1 FROM batalha")->fetchColumn();

            try {
                $prepare = $this->pdo->prepare(
                    "INSERT INTO batalha (id_batalha, id_usuario, is_boss) VALUES (:batalha, :usuario, :chefe)"
                );
                $prepare->execute(["batalha" => $proximo, "usuario" => $idUsuario, "chefe" => (int) $chefe]);

                return $proximo;
            } catch (PDOException $erro) {
                // 1062 = numero repetido (outro jogador abriu uma batalha ao mesmo tempo): espera um pouco e tenta o proximo
                if (($erro->errorInfo[1] ?? 0) !== 1062) {
                    throw $erro;
                }
                usleep(random_int(5000, 40000));
            }
        }

        throw new RuntimeException("nao consegui gerar o numero da batalha");
    }

    // quantas batalhas normais faltam para o chefe (0 = a proxima batalha e contra o chefe).
    // conta as batalhas normais terminadas depois da ultima vez que o jogador venceu o chefe
    // (se perder ou fugir do chefe, ele continua esperando)
    public function batalhasParaChefe(int $idUsuario): int
    {
        $prepare = $this->pdo->prepare(
            "SELECT COUNT(*) FROM batalha
             WHERE id_usuario = :id AND is_boss = 0 AND status <> 'em_andamento'
               AND id_batalha > COALESCE(
                   (SELECT MAX(id_batalha) FROM batalha WHERE id_usuario = :id2 AND is_boss = 1 AND status = 'vitoria'), 0)"
        );
        $prepare->execute(["id" => $idUsuario, "id2" => $idUsuario]);

        return max(0, self::BATALHAS_PARA_CHEFE - (int) $prepare->fetchColumn());
    }

    // abre uma batalha para o jogador e devolve o id e o time do robo (so quem tem equipe de 3 pode batalhar).
    // a cada 7 batalhas a vez e do chefe: 3 pokemons de forma final e nivel maior.
    // quem sorteia o robo e o servidor: assim ele sabe quais pokemons o jogador ganha se vencer
    // (3 basicos que nao estao na equipe do jogador)
    public function iniciar(int $idUsuario): array
    {
        $prepare = $this->pdo->prepare("SELECT COUNT(*) FROM equipe_usuario WHERE id_usuario = :id");
        $prepare->execute(["id" => $idUsuario]);
        if ((int) $prepare->fetchColumn() !== 3) {
            throw new RegraDeJogoException("Escolha sua equipe antes de batalhar", 409);
        }

        $this->pdo->beginTransaction();

        try {
            $chefe = $this->batalhasParaChefe($idUsuario) === 0;

            // chefe: so pokemons que ja evoluiram ao maximo. robo normal: so basicos
            $origem = $chefe
                ? "SELECT id_monstro AS id_pokemon FROM monstro
                   WHERE id_monstro IN (SELECT id_evolucao FROM evolucao_monstro)
                     AND id_monstro NOT IN (SELECT id_monstro FROM evolucao_monstro)"
                : "SELECT id_pokemon FROM pokemon_basico";

            $prepare = $this->pdo->prepare(
                "SELECT id_pokemon FROM ($origem) AS candidatos
                 WHERE id_pokemon NOT IN (SELECT id_pokemon FROM equipe_usuario WHERE id_usuario = :id)
                 ORDER BY RAND()
                 LIMIT 3"
            );
            $prepare->execute(["id" => $idUsuario]);
            $robo = array_map("intval", $prepare->fetchAll(PDO::FETCH_COLUMN));

            if (count($robo) !== 3) {
                throw new RuntimeException("pokemons basicos insuficientes para montar o time do robo");
            }

            $idBatalha = $this->inserirBatalha($idUsuario, $chefe);

            $prepare = $this->pdo->prepare(
                "INSERT INTO batalha_robo (id_batalha, posicao, id_pokemon) VALUES (:batalha, :posicao, :pokemon)"
            );
            foreach ($robo as $indice => $idPokemon) {
                $prepare->execute(["batalha" => $idBatalha, "posicao" => $indice + 1, "pokemon" => $idPokemon]);
            }

            $this->pdo->commit();

            return [
                "id" => $idBatalha,
                "robo" => $robo,
                "chefe" => $chefe,
                "nivel" => $chefe ? self::NIVEL_CHEFE : self::NIVEL_NORMAL
            ];
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $erro;
        }
    }

    // quem venceu leva os 3 pokemons do robo para a colecao (so os que ainda nao tinha).
    // roda dentro da transaction do finalizar, que ja garante que so acontece uma vez por batalha
    private function conquistarPokemons(int $idBatalha, int $idUsuario): array
    {
        $prepare = $this->pdo->prepare(
            "SELECT r.id_pokemon, b.nome,
                    EXISTS (SELECT 1 FROM pokemon_usuario pu
                            WHERE pu.id_usuario = :usuario AND pu.id_pokemon = r.id_pokemon) AS tinha
             FROM batalha_robo r
             LEFT JOIN monstro b ON b.id_monstro = r.id_pokemon
             WHERE r.id_batalha = :batalha
             ORDER BY r.posicao"
        );
        $prepare->execute(["usuario" => $idUsuario, "batalha" => $idBatalha]);

        $inserir = $this->pdo->prepare(
            "INSERT IGNORE INTO pokemon_usuario (id_usuario, id_pokemon) VALUES (:usuario, :pokemon)"
        );

        $lista = [];
        foreach ($prepare->fetchAll() as $linha) {
            $id = (int) $linha["id_pokemon"];
            $novo = !(bool) $linha["tinha"];

            if ($novo) {
                $inserir->execute(["usuario" => $idUsuario, "pokemon" => $id]);
            }
            $lista[] = ["id" => $id, "nome" => $linha["nome"] ?? "#" . $id, "novo" => $novo];
        }
        return $lista;
    }

    // quem decide quanto vale a batalha e o servidor, nunca o navegador
    // vitoria: 50 com 1 pokemon vivo, 75 com 2, 100 com 3 | derrota: 20 | fuga: 0
    // chefe: vitoria 300 | derrota 40 | fuga 0
    public static function calcularRecompensa(string $resultado, int $vivos, bool $chefe = false): int
    {
        if ($chefe) {
            return ["vitoria" => 300, "derrota" => 40][$resultado] ?? 0;
        }

        if ($resultado === "vitoria") {
            $vivos = max(1, min(3, $vivos));
            return (int) (50 * [1 => 1, 2 => 1.5, 3 => 2][$vivos]);
        }

        if ($resultado === "derrota") {
            return 20;
        }

        return 0;
    }

    // fecha a batalha e paga a recompensa. so funciona uma vez por batalha.
    // erros esperados saem como RegraDeJogoException (o codigo e o status http)
    public function finalizar(int $idBatalha, int $idUsuario, string $resultado, int $vivos): array
    {
        $this->pdo->beginTransaction();

        try {
            // trava a batalha (so a do proprio jogador) ate o fim da transaction
            $prepare = $this->pdo->prepare(
                "SELECT status, is_boss, TIMESTAMPDIFF(SECOND, iniciada_em, NOW()) AS segundos
                 FROM batalha
                 WHERE id_batalha = :batalha AND id_usuario = :usuario
                 FOR UPDATE"
            );
            $prepare->execute(["batalha" => $idBatalha, "usuario" => $idUsuario]);
            $batalha = $prepare->fetch();

            if (!$batalha) {
                throw new RegraDeJogoException("Batalha nao encontrada", 404);
            }

            if ($batalha["status"] !== "em_andamento") {
                throw new RegraDeJogoException("Essa batalha ja foi finalizada", 409);
            }

            // fugir nao paga nada, entao pode ser a qualquer momento
            if ($resultado !== "fuga" && (int) $batalha["segundos"] < self::SEGUNDOS_MINIMOS) {
                throw new RegraDeJogoException("Batalha rapida demais", 409);
            }

            $chefe = (bool) $batalha["is_boss"];
            $recompensa = self::calcularRecompensa($resultado, $vivos, $chefe);

            $prepare = $this->pdo->prepare(
                "UPDATE batalha
                 SET status = :status, recompensa = :recompensa, finalizada_em = NOW()
                 WHERE id_batalha = :batalha"
            );
            $prepare->execute([
                "status" => $resultado,
                "recompensa" => $recompensa,
                "batalha" => $idBatalha
            ]);

            $prepare = $this->pdo->prepare(
                "UPDATE usuario SET pokedolares = pokedolares + :recompensa WHERE id_usuario = :usuario"
            );
            $prepare->execute(["recompensa" => $recompensa, "usuario" => $idUsuario]);

            // vitoria: os pokemons do robo vao para o jogador
            $pokemons = $resultado === "vitoria" ? $this->conquistarPokemons($idBatalha, $idUsuario) : [];

            // vencer o chefe tambem da uma Pedra Evolutiva
            $pedra = $chefe && $resultado === "vitoria";
            if ($pedra) {
                $prepare = $this->pdo->prepare(
                    "INSERT INTO item_usuario (id_usuario, id_item_loja, quantidade)
                     SELECT :usuario, id_item_loja, 1 FROM item_loja WHERE chave = 'pedra-evolutiva'
                     ON DUPLICATE KEY UPDATE quantidade = quantidade + 1"
                );
                $prepare->execute(["usuario" => $idUsuario]);
            }

            $prepare = $this->pdo->prepare("SELECT pokedolares FROM usuario WHERE id_usuario = :usuario");
            $prepare->execute(["usuario" => $idUsuario]);
            $saldo = (int) $prepare->fetchColumn();

            $this->pdo->commit();

            return [
                "recompensa" => $recompensa,
                "pokedolares" => $saldo,
                "pokemons" => $pokemons,
                "chefe" => $chefe,
                "pedra" => $pedra
            ];
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $erro;
        }
    }
}
