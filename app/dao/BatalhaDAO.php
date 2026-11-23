<?php

/**
 * Controla as ações e regras referentes ao sistema de batalhas
 * 
 * @author Miguel Granville / Vinicius Grub
 */
class BatalhaDAO extends DAO
{
    /**
     * @var int Tempo mínimo para impedir farm via bots ou requisições rápidas
     */
    private const SEGUNDOS_MINIMOS = 10;

    /**
     * @var int Quantidade de batalhas antes do chefe
     */
    private const BATALHAS_PARA_CHEFE = 7;

    /**
     * @var int Nível base dos pokemons inimigos comuns
     */
    public const NIVEL_NORMAL = 10;

    /**
     * @var int Nível base dos pokemons do chefe
     */
    public const NIVEL_CHEFE = 10;

    /**
     * Verifica se a tabela batalha possui o atributo AUTO_INCREMENT
     * 
     * @author Miguel Granville / Vinicius Grub
     * @return bool Retorna true se possuir AUTO_INCREMENT, falso caso contrário
     */
    private function numeraSozinha(): bool
    {
        // Busca a propriedade EXTRA na tabela de colunas do banco, sem usar chaves externas
        $resultado =$this->Buscar([
            "colunas" => "EXTRA",
            "tabela" => "information_schema.COLUMNS",
            "pesquisa" => "TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'batalha' AND COLUMN_NAME = 'id_batalha'",
            "valores" => []
        ]);

        return $resultado && stripos((string)$resultado["EXTRA"], "auto_increment") !== false;
    }

    /**
     * Insere uma nova linha de batalha no banco de dados
     * 
     * @author Miguel Granville / Vinicius Grub
     * @param int $idUsuario ID do usuário da batalha
     * @param bool $chefe Define se é uma batalha contra o chefe
     * @return int Retorna o ID da batalha inserida
     * @throws RuntimeException Caso falhe na inserção do ID da batalha após 10 tentativas
     */
    private function inserirBatalha(int $idUsuario, bool$chefe): int
    {
        $idUsuarioBot = $this->buscarUsuarioBot();

        if ($this->numeraSozinha())
        {
            // Insere uma nova batalha automática usando as chaves id_usuario e is_boss
            return $this->Inserir([
                "tabela" => "batalha",
                "colunas" => ["id_usuario", "id_usuario_2", "is_boss"],
                "valores" => [
                    "id_usuario" => $idUsuario,
                    "id_usuario_2" => $idUsuarioBot,
                    "is_boss" => (int) $chefe
                ]
            ], true);
        }

        for ($tentativa = 0; $tentativa < 10; $tentativa++)
        {
            // Busca o próximo ID de batalha disponível somando 1 ao máximo existente
            $proximoData =$this->Buscar([
                "colunas" => "COALESCE(MAX(id_batalha), 0) + 1 AS proximo",
                "tabela" => "batalha",
                "pesquisa" => "1",
                "valores" => []
            ]);
            $proximo = (int)$proximoData["proximo"];

            try
            {
                // Insere a batalha de forma manual usando as chaves id_batalha, id_usuario e is_boss
                $this->Inserir([
                    "tabela" => "batalha",
                    "colunas" => ["id_batalha", "id_usuario", "id_usuario_2", "is_boss"],
                    "valores" => [
                        "id_batalha" => $proximo,
                        "id_usuario" => $idUsuario,
                        "id_usuario_2" => $idUsuarioBot,
                        "is_boss" => (int) $chefe
                    ]
                ]);
                return $proximo;
            }
            catch (PDOException $erro)
            {
                if (($erro->errorInfo[1] ?? 0) !== 1062)
                {
                    throw $erro;
                }
                usleep(random_int(5000, 40000));
            }
        }

        throw new RuntimeException("nao consegui gerar o numero da batalha");
    }

    /**
     * Calcula quantas batalhas o jogador teve desde o último chefe
     * 
     * @author Miguel Granville / Vinicius Grub
     * @param int $idUsuario ID do usuário
     * @return int Retorna a quantidade de batalhas contabilizadas para o chefe
     */
    private function batalhasParaChefe(int $idUsuario): int
    {
        // Busca a quantidade de batalhas normais concluídas usando a chave usuario e usuario_chefe
        $batalhasNormaisData =$this->Buscar([
            "colunas" => "COUNT(*) as total",
            "tabela" => "batalha",
            "pesquisa" => "id_usuario = :usuario AND is_boss = 0 AND status <> 'em_andamento' AND id_batalha > COALESCE((SELECT MAX(id_batalha) FROM batalha WHERE id_usuario = :usuario_chefe AND is_boss = 1), 0)",
            "valores" => ["usuario" => $idUsuario, "usuario_chefe" => $idUsuario]
        ]);
        $batalhasNormais = (int)$batalhasNormaisData["total"];

        // Busca se já existe uma batalha de chefe em andamento usando a chave usuario
        $emAndamento =$this->Buscar([
            "colunas" => "1",
            "tabela" => "batalha",
            "pesquisa" => "id_usuario = :usuario AND is_boss = 1 AND status = 'em_andamento' LIMIT 1",
            "valores" => ["usuario" => $idUsuario]
        ]);

        if ($emAndamento)
        {
            return self::BATALHAS_PARA_CHEFE;
        }

        return $batalhasNormais;
    }

    /**
     * Inicia uma batalha (normal ou de chefe) sorteando o time oponente
     * 
     * @author Miguel Granville / Vinicius Grub
     * @param int $idUsuario ID do usuário
     * @return array Retorna um array com id da batalha, robôs oponentes e flags
     */
    public function iniciar(int $idUsuario): array
    {
        // Busca a quantidade de pokemons na equipe atual usando a chave id
        $equipe =$this->Buscar([
            "colunas" => "COUNT(*) AS total",
            "tabela" => "equipe_usuario",
            "pesquisa" => "id_usuario = :id",
            "valores" => ["id" => $idUsuario]
        ]);

        if ((int) $equipe["total"] !== 3)
        {
            throw new RegraDeJogoException("Escolha sua equipe antes de batalhar", 409);
        }

        if (!$this->IniciarTransaction())
        {
            throw new PDOException("Não foi possivel iniciar uma transaction", 500);
        }

        try
        {
            $chefe = $this->batalhasParaChefe($idUsuario) >= self::BATALHAS_PARA_CHEFE;

            if ($chefe)
            {
                // Busca um pokemon forte para o chefe usando a chave id para evitar duplicatas do jogador
                $roboData =$this->Buscar([
                    "colunas" => "id_pokemon",
                    "tabela" => "(SELECT id_monstro AS id_pokemon FROM monstro WHERE id_monstro IN (SELECT id_evolucao FROM evolucao_monstro) AND id_monstro NOT IN (SELECT id_monstro FROM evolucao_monstro) AND id_monstro NOT IN (SELECT id_pokemon FROM equipe_usuario WHERE id_usuario = :id) ORDER BY vida_base + ataque_base + defesa_base + ataque_esp_base + defesa_esp_base + velocidade_base DESC LIMIT 3) AS fortes",
                    "pesquisa" => "1 ORDER BY RAND() LIMIT 1",
                    "valores" => ["id" => $idUsuario]
                ], true);
                $quantos = 1;
            }
            else
            {
                // Busca 3 pokemons comuns para formar o time inimigo usando a chave id
                $roboData =$this->Buscar([
                    "colunas" => "id_pokemon",
                    "tabela" => "pokemon_basico",
                    "pesquisa" => "id_pokemon NOT IN (SELECT id_pokemon FROM equipe_usuario WHERE id_usuario = :id) ORDER BY RAND() LIMIT 3",
                    "valores" => ["id" => $idUsuario]
                ], true);
                $quantos = 3;
            }

            $robo = array_map(fn($r) => (int) $r["id_pokemon"], $roboData);

            if (count($robo) !==$quantos)
            {
                throw new RuntimeException("pokemons insuficientes para montar o time do robo");
            }

            $idBatalha =$this->inserirBatalha($idUsuario,$chefe);
            $idUsuarioBot = $this->buscarUsuarioBot();

            $equipeHumana = $this->Buscar([
                "colunas" => "id_pokemon",
                "tabela" => "equipe_usuario",
                "pesquisa" => "id_usuario = :id ORDER BY posicao",
                "valores" => ["id" => $idUsuario]
            ], true);

            foreach ($equipeHumana as $indice => $linha)
            {
                $this->inserirMonstroDaBatalha(
                    $idBatalha,
                    $idUsuario,
                    (int) $linha["id_pokemon"],
                    $indice
                );
            }

            foreach ($robo as $indice =>$idPokemon)
            {
                $this->inserirMonstroDaBatalha(
                    $idBatalha,
                    $idUsuarioBot,
                    $idPokemon,
                    $indice
                );
            }

            $this->FinalizarTransaction();

            return [
                "id" => $idBatalha,
                "robo" => $robo,
                "chefe" => $chefe,
                "nivel" => $chefe ? self::NIVEL_CHEFE : self::NIVEL_NORMAL
            ];
        }
        catch (Throwable $erro)
        {
            $this->ReverterTransaction();
            throw $erro;
        }
    }

    /**
     * Verifica e adiciona para a coleção os pokemons do robô que o jogador venceu
     * 
     * @author Miguel Granville / Vinicius Grub
     */
    private function conquistarPokemons(int $idBatalha, int$idUsuario): array
    {
        // Busca os pokemons inimigos da batalha informando se o jogador já os tem usando as chaves usuario e batalha
        $batalha =$this->Buscar([
            "colunas" => "id_usuario_2",
            "tabela" => "batalha",
            "pesquisa" => "id_batalha = :batalha",
            "valores" => ["batalha" => $idBatalha]
        ]);

        if (!$batalha)
        {
            throw new RegraDeJogoException("Batalha nao encontrada", 404);
        }

        $roboData =$this->Buscar([
            "colunas" => "mb.id_monstro AS id_pokemon, m.nome, EXISTS (SELECT 1 FROM pokemon_usuario pu WHERE pu.id_usuario = :usuario AND pu.id_pokemon = mb.id_monstro) AS tinha",
            "tabela" => ["monstro_batalha_usuario mb", "JOIN monstro m", "ON m.id_monstro = mb.id_monstro"],
            "pesquisa" => "mb.id_batalha = :batalha AND mb.id_usuario = :bot",
            "valores" => [
                "usuario" => $idUsuario,
                "batalha" => $idBatalha,
                "bot" => (int) $batalha["id_usuario_2"]
            ]
        ], true);

        $lista = [];
        foreach ($roboData as$linha)
        {
            $id = (int)$linha["id_pokemon"];
            $novo = !(bool)$linha["tinha"];

            if ($novo)
            {
                // Insere o novo pokemon conquistado na coleção usando as chaves id_usuario e id_pokemon
                $this->Inserir([
                    "tabela" => "pokemon_usuario",
                    "colunas" => ["id_usuario", "id_pokemon"],
                    "extra" => "ON DUPLICATE KEY UPDATE id_pokemon = id_pokemon",
                    "valores" => ["id_usuario" => $idUsuario, "id_pokemon" => $id]
                ]);
            }
            $lista[] = ["id" => $id, "nome" => $linha["nome"] ?? "#" . $id, "novo" => $novo];
        }

        return $lista;
    }

    /**
     * Calcula o prêmio em pokedólares recebidos no final de um embate
     * 
     * @author Miguel Granville / Vinicius Grub
     */
    public static function calcularRecompensa(string $resultado, int$vivos): int
    {
        if ($resultado === "vitoria")
        {
            $vivos = max(1, min(3,$vivos));
            return (int) (50 * [1 => 1, 2 => 1.5, 3 => 2][$vivos]);
        }

        if ($resultado === "derrota")
        {
            return 20;
        }

        return 0;
    }

    /**
     * Finaliza a batalha gravando o histórico, pagando os prêmios e adicionando monstros
     * 
     * @author Miguel Granville / Vinicius Grub
     */
    public function finalizar(int $idBatalha, int$idUsuario, string $resultado, int$vivos): array
    {
        if (!$this->IniciarTransaction())
        {
            throw new PDOException("Não foi possivel iniciar uma transaction", 500);
        }

        try
        {
            // Busca o estado atual da batalha e sua duração usando as chaves batalha e usuario
            $batalha =$this->Buscar([
                "colunas" => "status, TIMESTAMPDIFF(SECOND, iniciada_em, NOW()) AS segundos",
                "tabela" => "batalha",
                "pesquisa" => "id_batalha = :batalha AND id_usuario = :usuario FOR UPDATE",
                "valores" => ["batalha" => $idBatalha, "usuario" => $idUsuario]
            ]);

            if (!$batalha)
            {
                throw new RegraDeJogoException("Batalha nao encontrada", 404);
            }

            if ($batalha["status"] !== "em_andamento")
            {
                throw new RegraDeJogoException("Essa batalha ja foi finalizada", 409);
            }

            if ($resultado !== "fuga" && (int) $batalha["segundos"] < self::SEGUNDOS_MINIMOS)
            {
                throw new RegraDeJogoException("Batalha rapida demais", 409);
            }

            $recompensa = self::calcularRecompensa($resultado,$vivos);

            // Atualiza a tabela batalha finalizando-a, aplicando status e recompensa baseado na chave batalha
            $this->Atualizar([
                "tabela" => "batalha",
                "colunas" => [
                    "status" => ":status",
                    "recompensa" => ":recompensa",
                    "finalizada_em" => "NOW()"
                ],
                "condicao" => "id_batalha = :batalha",
                "valores" => [
                    "status" => $resultado,
                    "recompensa" => $recompensa,
                    "batalha" => $idBatalha
                ]
            ]);

            // Atualiza o saldo de pokedolares do usuário baseando-se nas chaves recompensa e usuario
            $this->Atualizar([
                "tabela" => "usuario",
                "colunas" => ["pokedolares" => "pokedolares + :recompensa"],
                "condicao" => "id_usuario = :usuario",
                "valores" => ["recompensa" => $recompensa, "usuario" => $idUsuario]
            ]);

            $pokemons = $resultado === "vitoria" ? $this->conquistarPokemons($idBatalha,$idUsuario) : [];

            // Busca o novo saldo de dinheiro do jogador usando a chave usuario
            $saldoData =$this->Buscar([
                "colunas" => "pokedolares",
                "tabela" => "usuario",
                "pesquisa" => "id_usuario = :usuario",
                "valores" => ["usuario" => $idUsuario]
            ]);
            $saldo = (int)$saldoData["pokedolares"];

            $this->FinalizarTransaction();

            return ["recompensa" => $recompensa, "pokedolares" => $saldo, "pokemons" => $pokemons];
        }
        catch (Throwable $erro)
        {
            $this->ReverterTransaction();
            throw $erro;
        }
    }

    private function buscarUsuarioBot(): int
    {
        $bot = $this->Buscar([
            "colunas" => "id_usuario",
            "tabela" => "usuario",
            "pesquisa" => "is_bot = 1 AND is_ativo = 1 ORDER BY id_usuario LIMIT 1",
            "valores" => []
        ]);

        if (!$bot)
        {
            throw new RuntimeException("Nenhum usuario bot cadastrado");
        }

        return (int) $bot["id_usuario"];
    }

    private function inserirMonstroDaBatalha(
        int $idBatalha,
        int $idUsuario,
        int $idPokemon,
        int $indice
    ): void {
        $monstro = $this->Buscar([
            "colunas" => "vida_base",
            "tabela" => "monstro",
            "pesquisa" => "id_monstro = :id",
            "valores" => ["id" => $idPokemon]
        ]);

        if (!$monstro)
        {
            throw new RuntimeException("Pokemon nao encontrado no catalogo");
        }

        $ataques = $this->Buscar([
            "colunas" => "id_ataque",
            "tabela" => "ataque_possivel_monstro",
            "pesquisa" => "id_monstro = :id ORDER BY id_ataque LIMIT 4",
            "valores" => ["id" => $idPokemon]
        ], true);

        if (count($ataques) === 0)
        {
            $ataquePadrao = $this->Buscar([
                "colunas" => "id_ataque",
                "tabela" => "ataque",
                "pesquisa" => "is_ativo = 1 ORDER BY id_ataque LIMIT 1",
                "valores" => []
            ]);

            if (!$ataquePadrao)
            {
                throw new RuntimeException("Nao existe ataque disponivel");
            }

            $idsAtaques = [(int) $ataquePadrao["id_ataque"]];
        }
        else
        {
            $idsAtaques = array_map(fn($ataque) => (int) $ataque["id_ataque"], $ataques);
        }

        $idsAtaques = array_pad($idsAtaques, 4, null);

        $this->Inserir([
            "tabela" => "monstro_batalha_usuario",
            "colunas" => [
                "id_monstro", "id_batalha", "id_usuario", "is_ativo", "vida_atual",
                "id_ataque_1", "id_ataque_2", "id_ataque_3", "id_ataque_4"
            ],
            "valores" => [
                "id_monstro" => $idPokemon,
                "id_batalha" => $idBatalha,
                "id_usuario" => $idUsuario,
                "is_ativo" => $indice === 0 ? 1 : 0,
                "vida_atual" => (int) $monstro["vida_base"],
                "id_ataque_1" => $idsAtaques[0],
                "id_ataque_2" => $idsAtaques[1],
                "id_ataque_3" => $idsAtaques[2],
                "id_ataque_4" => $idsAtaques[3]
            ]
        ]);
    }
}