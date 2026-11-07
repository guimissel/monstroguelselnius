<?php

class BatalhaDAO
{
    // uma batalha de verdade dura mais que isso (so contando as mensagens do log)
    private const SEGUNDOS_MINIMOS = 10;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // a tabela batalha numera sozinha (AUTO_INCREMENT)? o loja.sql/equipe.sql configuram isso
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
    private function inserirBatalha(int $idUsuario): int
    {
        if ($this->numeraSozinha()) {
            $prepare = $this->pdo->prepare("INSERT INTO batalha (id_usuario) VALUES (:id)");
            $prepare->execute(["id" => $idUsuario]);

            return (int) $this->pdo->lastInsertId();
        }

        for ($tentativa = 0; $tentativa < 10; $tentativa++) {
            $proximo = (int) $this->pdo->query("SELECT COALESCE(MAX(id_batalha), 0) + 1 FROM batalha")->fetchColumn();

            try {
                $prepare = $this->pdo->prepare("INSERT INTO batalha (id_batalha, id_usuario) VALUES (:batalha, :usuario)");
                $prepare->execute(["batalha" => $proximo, "usuario" => $idUsuario]);

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

    // abre uma batalha para o jogador e devolve o id e o time do robo (so quem tem equipe de 3 pode batalhar).
    // quem sorteia o robo e o servidor: assim ele sabe quais pokemons o jogador ganha se vencer
    // (3 basicos que nao estao na equipe do jogador)
    public function iniciar(int $idUsuario): array
    {
        $prepare = $this->pdo->prepare("SELECT COUNT(*) FROM equipe_usuario WHERE id_usuario = :id");
        $prepare->execute(["id" => $idUsuario]);
        if ((int) $prepare->fetchColumn() !== 3) {
            throw new DomainException("Escolha sua equipe antes de batalhar", 409);
        }

        $this->pdo->beginTransaction();

        try {
            $prepare = $this->pdo->prepare(
                "SELECT id_pokemon FROM pokemon_basico
                 WHERE id_pokemon NOT IN (SELECT id_pokemon FROM equipe_usuario WHERE id_usuario = :id)
                 ORDER BY RAND()
                 LIMIT 3"
            );
            $prepare->execute(["id" => $idUsuario]);
            $robo = array_map("intval", $prepare->fetchAll(PDO::FETCH_COLUMN));

            if (count($robo) !== 3) {
                throw new RuntimeException("pokemons basicos insuficientes para montar o time do robo");
            }

            $idBatalha = $this->inserirBatalha($idUsuario);

            $prepare = $this->pdo->prepare(
                "INSERT INTO batalha_robo (id_batalha, posicao, id_pokemon) VALUES (:batalha, :posicao, :pokemon)"
            );
            foreach ($robo as $indice => $idPokemon) {
                $prepare->execute(["batalha" => $idBatalha, "posicao" => $indice + 1, "pokemon" => $idPokemon]);
            }

            $this->pdo->commit();

            return ["id" => $idBatalha, "robo" => $robo];
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
             LEFT JOIN pokemon_basico b ON b.id_pokemon = r.id_pokemon
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
    public static function calcularRecompensa(string $resultado, int $vivos): int
    {
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
    // erros esperados saem como DomainException (o codigo e o status http)
    public function finalizar(int $idBatalha, int $idUsuario, string $resultado, int $vivos): array
    {
        $this->pdo->beginTransaction();

        try {
            // trava a batalha (so a do proprio jogador) ate o fim da transaction
            $prepare = $this->pdo->prepare(
                "SELECT status, TIMESTAMPDIFF(SECOND, iniciada_em, NOW()) AS segundos
                 FROM batalha
                 WHERE id_batalha = :batalha AND id_usuario = :usuario
                 FOR UPDATE"
            );
            $prepare->execute(["batalha" => $idBatalha, "usuario" => $idUsuario]);
            $batalha = $prepare->fetch();

            if (!$batalha) {
                throw new DomainException("Batalha nao encontrada", 404);
            }

            if ($batalha["status"] !== "em_andamento") {
                throw new DomainException("Essa batalha ja foi finalizada", 409);
            }

            // fugir nao paga nada, entao pode ser a qualquer momento
            if ($resultado !== "fuga" && (int) $batalha["segundos"] < self::SEGUNDOS_MINIMOS) {
                throw new DomainException("Batalha rapida demais", 409);
            }

            $recompensa = self::calcularRecompensa($resultado, $vivos);

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

            $prepare = $this->pdo->prepare("SELECT pokedolares FROM usuario WHERE id_usuario = :usuario");
            $prepare->execute(["usuario" => $idUsuario]);
            $saldo = (int) $prepare->fetchColumn();

            $this->pdo->commit();

            return ["recompensa" => $recompensa, "pokedolares" => $saldo, "pokemons" => $pokemons];
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $erro;
        }
    }
}
