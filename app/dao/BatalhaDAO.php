<?php

/**
 * Controla as ações e regras referentes ao sistema de batalhas.
 *
 * Responsável por iniciar batalhas, persistir e carregar o estado completo
 * de cada turno (monstros, vida, efeitos, golpes e cooldowns), integrar com
 * o MotorDeBatalha e finalizar a partida concedendo recompensas e conquistas.
 *
 * @author Miguel Granville / Vinicius Grub
 */
class BatalhaDAO extends DAO
{
    /** @var int Nível base dos pokémons inimigos comuns */
    public const NIVEL_NORMAL = ConfigBatalha::NIVEL_ROBO;

    /** @var int Nível base dos pokémons do chefe */
    public const NIVEL_CHEFE = ConfigBatalha::NIVEL_CHEFE;

    /**
     * Verifica se a tabela batalha possui o atributo AUTO_INCREMENT.
     *
     * @author Miguel Granville / Vinicius Grub
     * @return bool Retorna true se possuir AUTO_INCREMENT, falso caso contrário
     */
    private function numeraSozinha(): bool
    {
        $resultado = $this->Buscar([
            "colunas"  => "EXTRA",
            "tabela"   => "information_schema.COLUMNS",
            "pesquisa" => "TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'batalha' AND COLUMN_NAME = 'id_batalha'",
            "valores"  => []
        ]);

        return $resultado && stripos((string) $resultado["EXTRA"], "auto_increment") !== false;
    }

    /**
     * Insere uma nova linha de batalha no banco de dados.
     *
     * @author Miguel Granville / Vinicius Grub
     * @param int  $idUsuario ID do usuário da batalha
     * @param bool $chefe     Define se é uma batalha contra o chefe
     * @return int            Retorna o ID da batalha inserida
     * @throws RuntimeException Caso falhe na inserção após 10 tentativas
     */
    private function inserirBatalha(int $idUsuario, bool $chefe): int
    {
        $idUsuarioBot = $this->buscarUsuarioBot();

        if ($this->numeraSozinha()) {
            return $this->Inserir([
                "tabela"  => "batalha",
                "colunas" => ["id_usuario", "id_usuario_2", "is_boss"],
                "valores" => [
                    "id_usuario"   => $idUsuario,
                    "id_usuario_2" => $idUsuarioBot,
                    "is_boss"      => (int) $chefe
                ]
            ], true);
        }

        for ($tentativa = 0; $tentativa < 10; $tentativa++) {
            $proximoData = $this->Buscar([
                "colunas"  => "COALESCE(MAX(id_batalha), 0) + 1 AS proximo",
                "tabela"   => "batalha",
                "pesquisa" => "1",
                "valores"  => []
            ]);
            $proximo = (int) $proximoData["proximo"];

            try {
                $this->Inserir([
                    "tabela"  => "batalha",
                    "colunas" => ["id_batalha", "id_usuario", "id_usuario_2", "is_boss"],
                    "valores" => [
                        "id_batalha"   => $proximo,
                        "id_usuario"   => $idUsuario,
                        "id_usuario_2" => $idUsuarioBot,
                        "is_boss"      => (int) $chefe
                    ]
                ]);
                return $proximo;
            } catch (PDOException $erro) {
                if (($erro->errorInfo[1] ?? 0) !== 1062) {
                    throw $erro;
                }
                usleep(random_int(5000, 40000));
            }
        }

        throw new RuntimeException("Não consegui gerar o número da batalha");
    }

    /**
     * Calcula quantas batalhas normais o jogador teve desde o último chefe.
     *
     * @author Miguel Granville / Vinicius Grub
     * @param int $idUsuario ID do usuário
     * @return int           Quantidade de batalhas contabilizadas para o chefe
     */
    private function batalhasParaChefe(int $idUsuario): int
    {
        $batalhasNormaisData = $this->Buscar([
            "colunas"  => "COUNT(*) as total",
            "tabela"   => "batalha",
            "pesquisa" => "id_usuario = :usuario AND is_boss = 0 AND status <> 'em_andamento' AND id_batalha > COALESCE((SELECT MAX(id_batalha) FROM batalha WHERE id_usuario = :usuario_chefe AND is_boss = 1), 0)",
            "valores"  => ["usuario" => $idUsuario, "usuario_chefe" => $idUsuario]
        ]);
        $batalhasNormais = (int) $batalhasNormaisData["total"];

        $emAndamento = $this->Buscar([
            "colunas"  => "1",
            "tabela"   => "batalha",
            "pesquisa" => "id_usuario = :usuario AND is_boss = 1 AND status = 'em_andamento' LIMIT 1",
            "valores"  => ["usuario" => $idUsuario]
        ]);

        if ($emAndamento) {
            return ConfigBatalha::BATALHAS_PARA_CHEFE;
        }

        return $batalhasNormais;
    }

    /**
     * Inicia uma batalha (normal ou de chefe), sorteia o time oponente,
     * grava os monstros e golpes no banco e devolve as configurações e o estado inicial.
     *
     * @author Miguel Granville / Vinicius Grub
     * @param int $idUsuario ID do usuário autenticado
     * @return array         ID da batalha, time do robô, flags, configurações e estado inicial
     */
    public function iniciar(int $idUsuario): array
    {
        // Confere se o jogador já montou sua equipe completa de 3 pokémons
        $equipe = $this->Buscar([
            "colunas"  => "COUNT(*) AS total",
            "tabela"   => "equipe_usuario",
            "pesquisa" => "id_usuario = :id",
            "valores"  => ["id" => $idUsuario]
        ]);

        if ((int) $equipe["total"] !== 3) {
            throw new RegraDeJogoException("Escolha sua equipe antes de batalhar", 409);
        }

        if (!$this->IniciarTransaction()) {
            throw new PDOException("Não foi possível iniciar uma transaction", 500);
        }

        try {
            $chefe = $this->batalhasParaChefe($idUsuario) >= ConfigBatalha::BATALHAS_PARA_CHEFE;

            if ($chefe) {
                // Chefe: sorteia 1 pokémon evoluído forte que não esteja na equipe do jogador
                $roboData = $this->Buscar([
                    "colunas"  => "id_pokemon",
                    "tabela"   => "(SELECT id_monstro AS id_pokemon FROM monstro WHERE id_monstro IN (SELECT id_evolucao FROM evolucao_monstro) AND id_monstro NOT IN (SELECT id_monstro FROM evolucao_monstro) AND id_monstro NOT IN (SELECT id_pokemon FROM equipe_usuario WHERE id_usuario = :id) ORDER BY vida_base + ataque_base + defesa_base + ataque_esp_base + defesa_esp_base + velocidade_base DESC LIMIT 3) AS fortes",
                    "pesquisa" => "1 ORDER BY RAND() LIMIT 1",
                    "valores"  => ["id" => $idUsuario]
                ], true);
                $quantos = 1;
            } else {
                // Batalha comum: sorteia 3 pokémons básicos diferentes dos do jogador
                $roboData = $this->Buscar([
                    "colunas"  => "id_pokemon",
                    "tabela"   => "pokemon_basico",
                    "pesquisa" => "id_pokemon NOT IN (SELECT id_pokemon FROM equipe_usuario WHERE id_usuario = :id) ORDER BY RAND() LIMIT 3",
                    "valores"  => ["id" => $idUsuario]
                ], true);
                $quantos = 3;
            }

            $robo = array_map(fn($r) => (int) $r["id_pokemon"], $roboData);

            if (count($robo) !== $quantos) {
                throw new RuntimeException("Pokémons insuficientes para montar o time do robô");
            }

            $idBatalha    = $this->inserirBatalha($idUsuario, $chefe);
            $idUsuarioBot = $this->buscarUsuarioBot();
            $nivelRobo    = $chefe ? self::NIVEL_CHEFE : self::NIVEL_NORMAL;

            // Registra os pokémons do jogador na ordem da sua equipe
            $equipeHumana = $this->Buscar([
                "colunas"  => "id_pokemon",
                "tabela"   => "equipe_usuario",
                "pesquisa" => "id_usuario = :id ORDER BY posicao",
                "valores"  => ["id" => $idUsuario]
            ], true);

            foreach ($equipeHumana as $indice => $linha) {
                $this->inserirMonstroDaBatalha(
                    $idBatalha,
                    $idUsuario,
                    (int) $linha["id_pokemon"],
                    $indice,
                    ConfigBatalha::NIVEL_JOGADOR,
                    false
                );
            }

            // Registra a ordem e os monstros do robô em batalha_robo e monstro_batalha_usuario
            foreach ($robo as $indice => $idPokemon) {
                $this->Inserir([
                    "tabela"  => "batalha_robo",
                    "colunas" => ["id_batalha", "posicao", "id_pokemon"],
                    "valores" => [
                        "id_batalha" => $idBatalha,
                        "posicao"    => $indice + 1,
                        "id_pokemon" => $idPokemon
                    ]
                ]);

                $this->inserirMonstroDaBatalha(
                    $idBatalha,
                    $idUsuarioBot,
                    $idPokemon,
                    $indice,
                    $nivelRobo,
                    $chefe
                );
            }

            // Monta o estado inicial completo (com stats, tipos e golpes) antes de confirmar a transação
            $estadoInicial = $this->estadoInicialParaCliente($idBatalha, $idUsuario);

            $this->FinalizarTransaction();

            return [
                "id"            => $idBatalha,
                "robo"          => $robo,
                "chefe"         => $chefe,
                "nivel"         => $nivelRobo,
                "configuracoes" => ConfigBatalha::paraCliente(),
                "batalha"       => $estadoInicial
            ];
        } catch (Throwable $erro) {
            $this->ReverterTransaction();
            throw $erro;
        }
    }

    /**
     * Processa uma ação/turno de batalha de forma totalmente validada no servidor.
     *
     * Abre uma transação com bloqueio de linha (FOR UPDATE) para impedir jogadas
     * simultâneas ou duplicadas, executa o MotorDeBatalha, persiste a nova situação
     * de HP/status/turnos no banco e finaliza automaticamente se houver vencedor.
     *
     * @author Miguel Granville / Vinicius Grub
     * @param int   $idBatalha ID da batalha em andamento
     * @param int   $idUsuario ID do jogador autenticado
     * @param array $acao      Ação enviada pelo cliente
     * @return array           Eventos do turno, estado sincronizado e (se acabou) recompensa
     */
    public function processarTurno(int $idBatalha, int $idUsuario, array $acao): array
    {
        if (!$this->IniciarTransaction()) {
            throw new PDOException("Não foi possível iniciar uma transaction", 500);
        }

        try {
            // Trava a linha da batalha durante o turno para evitar condições de corrida
            $estado = $this->carregarEstadoBatalha($idBatalha, $idUsuario, true);

            // Callback para validar e consumir 1 unidade de item dentro da mesma transação
            $consumirItem = function (string $chave, array $monstro) use ($idUsuario): ?array {
                $dadosItem = $this->buscarDadosItem($chave);
                if (!$dadosItem) {
                    return null;
                }

                // Impede desperdiçar item num alvo incompatível (ex.: Reviver em vivo, Poção em desmaiado)
                if (!$this->itemServeParaMonstro($dadosItem, $monstro)) {
                    throw new RegraDeJogoException("Esse item não tem efeito nesse pokémon", 409);
                }

                if (!$this->gastarItem($idUsuario, $chave)) {
                    return null;
                }

                return $dadosItem;
            };

            $motor   = new MotorDeBatalha($consumirItem);
            $retorno = $motor->processarTurno($estado, $acao);

            // Grava o novo estado dos monstros e dos contadores da batalha no banco
            $this->atualizarEstadoBatalha($idBatalha, $estado);
            $this->atualizarEstadoMonstros($idBatalha, $estado);

            // Se a partida chegou ao fim neste turno, finaliza e calcula recompensa no servidor
            if (!empty($estado["fim"])) {
                $resultado = match ($estado["vencedor"]) {
                    'jogador' => 'vitoria',
                    'robo'    => 'derrota',
                    default   => 'fuga'
                };
                $vivos = $resultado === 'fuga' ? 0 : $motor->contarVivos($estado["monstros_jogador"]);

                $dadosFinal = $this->finalizarInterno($idBatalha, $idUsuario, $resultado, $vivos);

                $retorno["eventos"][] = [
                    "tipo"  => "fim",
                    "dados" => [
                        "resultado"   => $resultado,
                        "recompensa"  => $dadosFinal["recompensa"],
                        "pokedolares" => $dadosFinal["pokedolares"],
                        "pokemons"    => $dadosFinal["pokemons"]
                    ]
                ];
            }

            $this->FinalizarTransaction();

            return $retorno;
        } catch (Throwable $erro) {
            $this->ReverterTransaction();
            throw $erro;
        }
    }

    /**
     * Verifica e adiciona para a coleção os pokémons do robô que o jogador venceu.
     *
     * @author Miguel Granville / Vinicius Grub
     */
    private function conquistarPokemons(int $idBatalha, int $idUsuario): array
    {
        $batalha = $this->Buscar([
            "colunas"  => "id_usuario_2",
            "tabela"   => "batalha",
            "pesquisa" => "id_batalha = :batalha",
            "valores"  => ["batalha" => $idBatalha]
        ]);

        if (!$batalha) {
            throw new RegraDeJogoException("Batalha não encontrada", 404);
        }

        $roboData = $this->Buscar([
            "colunas"  => "mb.id_monstro AS id_pokemon, m.nome, EXISTS (SELECT 1 FROM pokemon_usuario pu WHERE pu.id_usuario = :usuario AND pu.id_pokemon = mb.id_monstro) AS tinha",
            "tabela"   => ["monstro_batalha_usuario mb", "JOIN monstro m", "ON m.id_monstro = mb.id_monstro"],
            "pesquisa" => "mb.id_batalha = :batalha AND mb.id_usuario = :bot",
            "valores"  => [
                "usuario" => $idUsuario,
                "batalha" => $idBatalha,
                "bot"     => (int) $batalha["id_usuario_2"]
            ]
        ], true);

        $lista = [];
        foreach ($roboData as $linha) {
            $id   = (int) $linha["id_pokemon"];
            $novo = !(bool) $linha["tinha"];

            if ($novo) {
                $this->Inserir([
                    "tabela"  => "pokemon_usuario",
                    "colunas" => ["id_usuario", "id_pokemon"],
                    "extra"   => "ON DUPLICATE KEY UPDATE id_pokemon = id_pokemon",
                    "valores" => ["id_usuario" => $idUsuario, "id_pokemon" => $id]
                ]);
            }
            $lista[] = [
                "id"   => $id,
                "nome" => ConfigBatalha::formatarNome((string) ($linha["nome"] ?? "#" . $id)),
                "novo" => $novo
            ];
        }

        return $lista;
    }

    /**
     * Calcula o prêmio em pokédolares recebidos no final de um embate.
     *
     * @author Miguel Granville / Vinicius Grub
     */
    public static function calcularRecompensa(string $resultado, int $vivos): int
    {
        if ($resultado === "vitoria") {
            $vivos = max(1, min(3, $vivos));
            return (int) (ConfigBatalha::RECOMPENSA_BASE_VITORIA * [1 => 1, 2 => 1.5, 3 => 2][$vivos]);
        }

        if ($resultado === "derrota") {
            return ConfigBatalha::RECOMPENSA_DERROTA;
        }

        return 0;
    }

    /**
     * Finaliza a batalha a partir de requisição externa (ex.: fuga ao fechar a aba).
     *
     * Para evitar trapaças via requisição direta à API, vitórias ou derrotas só são
     * aceitas se o estado real dos pokémons no banco comprovar que o time adversário
     * (ou do jogador) chegou a 0 de HP.
     *
     * @author Miguel Granville / Vinicius Grub
     */
    public function finalizar(int $idBatalha, int $idUsuario, string $resultado, int $vivos): array
    {
        if (!$this->IniciarTransaction()) {
            throw new PDOException("Não foi possível iniciar uma transaction", 500);
        }

        try {
            $batalha = $this->Buscar([
                "colunas"  => "id_usuario_2, status",
                "tabela"   => "batalha",
                "pesquisa" => "id_batalha = :batalha AND id_usuario = :usuario FOR UPDATE",
                "valores"  => ["batalha" => $idBatalha, "usuario" => $idUsuario]
            ]);

            if (!$batalha) {
                throw new RegraDeJogoException("Batalha não encontrada", 404);
            }

            if ($batalha["status"] !== "em_andamento") {
                throw new RegraDeJogoException("Essa batalha já foi finalizada", 409);
            }

            // Validação anti-trapaça: não aceita vitória/derrota declarada pelo cliente sem comprovação no banco
            if ($resultado !== "fuga") {
                $idBot = (int) $batalha["id_usuario_2"];

                $vivosJogadorData = $this->Buscar([
                    "colunas"  => "COUNT(*) AS total",
                    "tabela"   => "monstro_batalha_usuario",
                    "pesquisa" => "id_batalha = :batalha AND id_usuario = :usuario AND vida_atual > 0",
                    "valores"  => ["batalha" => $idBatalha, "usuario" => $idUsuario]
                ]);
                $vivosRoboData = $this->Buscar([
                    "colunas"  => "COUNT(*) AS total",
                    "tabela"   => "monstro_batalha_usuario",
                    "pesquisa" => "id_batalha = :batalha AND id_usuario = :bot AND vida_atual > 0",
                    "valores"  => ["batalha" => $idBatalha, "bot" => $idBot]
                ]);

                $vivosReaisJogador = (int) $vivosJogadorData["total"];
                $vivosReaisRobo    = (int) $vivosRoboData["total"];

                if ($resultado === "vitoria" && $vivosReaisRobo > 0) {
                    throw new RegraDeJogoException("Vitória não validada: o robô ainda possui pokémons vivos", 409);
                }
                if ($resultado === "derrota" && $vivosReaisJogador > 0) {
                    throw new RegraDeJogoException("Derrota não validada: você ainda possui pokémons vivos", 409);
                }

                // Usa sempre a contagem real de vivos do banco para calcular a recompensa
                $vivos = $vivosReaisJogador;
            }

            $dados = $this->finalizarInterno($idBatalha, $idUsuario, $resultado, $vivos);

            $this->FinalizarTransaction();

            return $dados;
        } catch (Throwable $erro) {
            $this->ReverterTransaction();
            throw $erro;
        }
    }

    /**
     * Grava o encerramento da batalha, credita pokédolares e registra pokémons conquistados.
     * Deve ser chamado sempre dentro de uma transação já aberta e com a linha travada.
     */
    private function finalizarInterno(int $idBatalha, int $idUsuario, string $resultado, int $vivos): array
    {
        $recompensa = self::calcularRecompensa($resultado, $vivos);

        $this->Atualizar([
            "tabela"  => "batalha",
            "colunas" => [
                "status"        => ":status",
                "recompensa"    => ":recompensa",
                "finalizada_em" => "NOW()"
            ],
            "condicao" => "id_batalha = :batalha",
            "valores"  => [
                "status"     => $resultado,
                "recompensa" => $recompensa,
                "batalha"    => $idBatalha
            ]
        ]);

        if ($recompensa > 0) {
            $this->Atualizar([
                "tabela"   => "usuario",
                "colunas"  => ["pokedolares" => "pokedolares + :recompensa"],
                "condicao" => "id_usuario = :usuario",
                "valores"  => ["recompensa" => $recompensa, "usuario" => $idUsuario]
            ]);
        }

        $pokemons = $resultado === "vitoria" ? $this->conquistarPokemons($idBatalha, $idUsuario) : [];

        $saldoData = $this->Buscar([
            "colunas"  => "pokedolares",
            "tabela"   => "usuario",
            "pesquisa" => "id_usuario = :usuario",
            "valores"  => ["usuario" => $idUsuario]
        ]);
        $saldo = (int) $saldoData["pokedolares"];

        return [
            "recompensa"  => $recompensa,
            "pokedolares" => $saldo,
            "pokemons"    => $pokemons
        ];
    }

    /**
     * Localiza o usuário bot ativo que representa o treinador robô no banco.
     */
    private function buscarUsuarioBot(): int
    {
        $bot = $this->Buscar([
            "colunas"  => "id_usuario",
            "tabela"   => "usuario",
            "pesquisa" => "is_bot = 1 AND is_ativo = 1 ORDER BY id_usuario LIMIT 1",
            "valores"  => []
        ]);

        if (!$bot) {
            throw new RuntimeException("Nenhum usuário bot cadastrado");
        }

        return (int) $bot["id_usuario"];
    }

    /**
     * Insere um monstro na tabela monstro_batalha_usuario com HP inicial calculado
     * pelo nível da batalha e até 4 golpes válidos selecionados do catálogo.
     */
    private function inserirMonstroDaBatalha(
        int $idBatalha,
        int $idUsuario,
        int $idPokemon,
        int $indice,
        int $nivel,
        bool $chefe
    ): void {
        $monstro = $this->Buscar([
            "colunas"  => "vida_base",
            "tabela"   => "monstro",
            "pesquisa" => "id_monstro = :id",
            "valores"  => ["id" => $idPokemon]
        ]);

        if (!$monstro) {
            throw new RuntimeException("Pokémon #{$idPokemon} não encontrado no catálogo");
        }

        // Calcula o HP real no nível da batalha para não divergir do HP máximo em combate
        $hpInicial  = ConfigBatalha::calcularHpMaximo((int) $monstro["vida_base"], $nivel);
        $idsAtaques = $this->selecionarAtaquesBatalha($idPokemon, $chefe);
        $idsAtaques = array_pad($idsAtaques, 4, null);

        $this->Inserir([
            "tabela"  => "monstro_batalha_usuario",
            "colunas" => [
                "id_monstro", "id_batalha", "id_usuario", "is_ativo", "vida_atual",
                "id_ataque_1", "id_ataque_2", "id_ataque_3", "id_ataque_4"
            ],
            "valores" => [
                "id_monstro"  => $idPokemon,
                "id_batalha"  => $idBatalha,
                "id_usuario"  => $idUsuario,
                "is_ativo"    => $indice === 0 ? 1 : 0,
                "vida_atual"  => $hpInicial,
                "id_ataque_1" => $idsAtaques[0],
                "id_ataque_2" => $idsAtaques[1],
                "id_ataque_3" => $idsAtaques[2],
                "id_ataque_4" => $idsAtaques[3]
            ]
        ]);
    }

    /**
     * Seleciona até 4 golpes válidos para o pokémon usar em batalha:
     *   - Ignora golpes proibidos (2 turnos, recarga, auto-KO).
     *   - Aceita golpes de dano com poder >= 40 (limitados a PODER_MAX_CHEFE se for chefe)
     *     ou golpes de status que apliquem condições válidas (sono, paralisia, etc.).
     *   - Dá preferência a até 3 golpes fortes de dano + 1 golpe variado e garante
     *     sempre pelo menos 1 golpe de dano (fallback para Tackle).
     */
    private function selecionarAtaquesBatalha(int $idPokemon, bool $chefe): array
    {
        $sql = "SELECT a.id_ataque, a.nome, a.poder, a.precisao, e.nome AS efeito_nome,
                       EXISTS (
                           SELECT 1 FROM tipo_monstro tm
                           WHERE tm.id_monstro = :monstro AND tm.id_tipo = a.id_tipo
                       ) AS tem_stab
                FROM ataque_possivel_monstro apm
                JOIN ataque a ON a.id_ataque = apm.id_ataque
                LEFT JOIN efeito e ON e.id_efeito = a.id_efeito
                WHERE apm.id_monstro = :monstro_where AND a.is_ativo = 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(["monstro" => $idPokemon, "monstro_where" => $idPokemon]);
        $candidatos = $stmt->fetchAll();

        $proibidos = array_flip(ConfigBatalha::GOLPES_PROIBIDOS);
        $poderMax  = $chefe ? ConfigBatalha::PODER_MAX_CHEFE : PHP_INT_MAX;

        $danos  = [];
        $status = [];

        foreach ($candidatos as $c) {
            $nome = strtolower((string) $c["nome"]);
            if (isset($proibidos[$nome])) {
                continue;
            }

            $poder = $c["poder"] !== null ? (int) $c["poder"] : 0;
            if ($poder > 0) {
                if ($poder < ConfigBatalha::PODER_MIN_GOLPE || $poder > $poderMax) {
                    continue;
                }
                $precisao = $c["precisao"] !== null ? (int) $c["precisao"] : 100;
                $stab     = !empty($c["tem_stab"]) ? ConfigBatalha::MULT_STAB : 1.0;
                $c["nota"] = $poder * ($precisao / 100.0) * $stab;
                $danos[] = $c;
            } else {
                $efeito = strtolower((string) ($c["efeito_nome"] ?? ""));
                if (in_array($efeito, ConfigBatalha::STATUS_VALIDOS, true)) {
                    $status[] = $c;
                }
            }
        }

        // Ordena golpes de dano do melhor para o mais fraco (com pequeno embaralhamento antes para variar)
        shuffle($danos);
        usort($danos, fn(array $a, array $b): int => $b["nota"] <=> $a["nota"]);

        $resto = array_merge(array_slice($danos, 3), $status);
        shuffle($resto);

        $escolhidos = array_merge(array_slice($danos, 0, 3), $resto);
        $escolhidos = array_slice($escolhidos, 0, 4);

        $ids     = array_map(fn(array $g): int => (int) $g["id_ataque"], $escolhidos);
        $temDano = false;
        foreach ($escolhidos as $g) {
            if ((int) ($g["poder"] ?? 0) > 0) {
                $temDano = true;
                break;
            }
        }

        // Garante que todo pokémon tenha pelo menos 1 golpe de dano (Tackle / ataque ativo)
        if (count($ids) < 4 || !$temDano) {
            $padrao = $this->Buscar([
                "colunas"  => "id_ataque",
                "tabela"   => "ataque",
                "pesquisa" => "is_ativo = 1 AND (nome = 'tackle' OR (poder IS NOT NULL AND poder >= 40)) ORDER BY (nome = 'tackle') DESC, id_ataque LIMIT 1",
                "valores"  => []
            ]);

            if (!$padrao) {
                $padrao = $this->Buscar([
                    "colunas"  => "id_ataque",
                    "tabela"   => "ataque",
                    "pesquisa" => "is_ativo = 1 ORDER BY id_ataque LIMIT 1",
                    "valores"  => []
                ]);
            }

            if ($padrao) {
                $idPadrao = (int) $padrao["id_ataque"];
                if (count($ids) >= 4) {
                    array_pop($ids);
                }
                if (!in_array($idPadrao, $ids, true)) {
                    $ids[] = $idPadrao;
                }
            }
        }

        if (empty($ids)) {
            throw new RuntimeException("Não existe ataque disponível no banco de dados");
        }

        return $ids;
    }

    // =====================================================================
    //  CARREGAMENTO E PERSISTÊNCIA DO ESTADO DA BATALHA
    // =====================================================================

    /**
     * Carrega o estado completo de uma batalha para o MotorDeBatalha processar.
     *
     * @author Miguel Granville / Vinicius Grub
     * @param int  $idBatalha ID da batalha
     * @param int  $idUsuario ID do jogador autenticado
     * @param bool $travar    Se true, aplica FOR UPDATE na tabela batalha
     * @return array          Estado completo da batalha
     */
    public function carregarEstadoBatalha(int $idBatalha, int $idUsuario, bool $travar = false): array
    {
        $clausulaLock = $travar ? " FOR UPDATE" : "";

        $batalha = $this->Buscar([
            "colunas"  => "id_batalha, id_usuario, id_usuario_2, turno_atual, cooldown_troca_1, cooldown_troca_2, is_boss, status",
            "tabela"   => "batalha",
            "pesquisa" => "id_batalha = :batalha AND id_usuario = :usuario" . $clausulaLock,
            "valores"  => ["batalha" => $idBatalha, "usuario" => $idUsuario]
        ]);

        if (!$batalha) {
            throw new RegraDeJogoException("Batalha não encontrada", 404);
        }

        $idBot     = (int) $batalha["id_usuario_2"];
        $isChefe   = (bool) $batalha["is_boss"];
        $nivelRobo = $isChefe ? self::NIVEL_CHEFE : self::NIVEL_NORMAL;

        $monstrosJogador = $this->carregarMonstrosDaBatalha($idBatalha, $idUsuario, ConfigBatalha::NIVEL_JOGADOR, true);
        $monstrosRobo    = $this->carregarMonstrosDaBatalha($idBatalha, $idBot, $nivelRobo, false);

        // Verifica se o jogador está aguardando troca obrigatória (nenhum vivo marcado como ativo)
        $temAtivoVivo = false;
        $temAlgumVivo = false;
        foreach ($monstrosJogador as $mon) {
            if ($mon["vida_atual"] > 0) {
                $temAlgumVivo = true;
                if ($mon["is_ativo"]) {
                    $temAtivoVivo = true;
                }
            }
        }

        return [
            "id_batalha"       => $idBatalha,
            "id_usuario"       => $idUsuario,
            "id_bot"           => $idBot,
            "turno_atual"      => (int) $batalha["turno_atual"],
            "cooldown_troca_1" => (int) $batalha["cooldown_troca_1"],
            "cooldown_troca_2" => (int) $batalha["cooldown_troca_2"],
            "is_boss"          => $isChefe,
            "status"           => $batalha["status"],
            "nivel_robo"       => $nivelRobo,
            "aguardando_troca" => (!$temAtivoVivo && $temAlgumVivo),
            "monstros_jogador" => $monstrosJogador,
            "monstros_robo"    => $monstrosRobo,
            "fim"              => false,
            "vencedor"         => null
        ];
    }

    /**
     * Carrega os monstros de um participante mantendo a ordem original das posições
     * (equipe_usuario.posicao para o jogador e batalha_robo.posicao para o robô),
     * evitando que os índices [0, 1, 2] mudem após trocas.
     */
    private function carregarMonstrosDaBatalha(
        int $idBatalha,
        int $idUsuario,
        int $nivel,
        bool $ehJogador
    ): array {
        $joinPosicao = $ehJogador
            ? "LEFT JOIN equipe_usuario pos ON pos.id_usuario = mb.id_usuario AND pos.id_pokemon = mb.id_monstro"
            : "LEFT JOIN batalha_robo pos ON pos.id_batalha = mb.id_batalha AND pos.id_pokemon = mb.id_monstro";

        $sql = "SELECT 
                    mb.id_monstro, mb.is_ativo, mb.vida_atual,
                    mb.id_efeito_atual, mb.turnos_restantes_efeito,
                    mb.id_ataque_1, mb.id_ataque_2, mb.id_ataque_3, mb.id_ataque_4,
                    m.nome, m.vida_base, m.ataque_base, m.defesa_base,
                    m.ataque_esp_base, m.defesa_esp_base, m.velocidade_base
                FROM monstro_batalha_usuario mb
                JOIN monstro m ON m.id_monstro = mb.id_monstro
                {$joinPosicao}
                WHERE mb.id_batalha = :batalha AND mb.id_usuario = :usuario
                ORDER BY COALESCE(pos.posicao, 99), mb.id_monstro";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(["batalha" => $idBatalha, "usuario" => $idUsuario]);
        $linhas = $stmt->fetchAll();

        $monstros = [];
        foreach ($linhas as $linha) {
            $tipos       = $this->carregarTiposMonstro((int) $linha["id_monstro"]);
            $golpes      = $this->carregarGolpesMonstro($linha);
            $hpMaximo    = ConfigBatalha::calcularHpMaximo((int) $linha["vida_base"], $nivel);
            $statusAtual = $this->converterEfeitoParaStatus((int) ($linha["id_efeito_atual"] ?? 0));

            $monstros[] = [
                "id_monstro"              => (int) $linha["id_monstro"],
                "nome"                    => ConfigBatalha::formatarNome((string) $linha["nome"]),
                "nivel"                   => $nivel,
                "tipos"                   => $tipos,
                "hp_maximo"               => $hpMaximo,
                "vida_atual"              => min($hpMaximo, (int) $linha["vida_atual"]),
                "ataque"                  => ConfigBatalha::calcularAtributo((int) $linha["ataque_base"], $nivel),
                "defesa"                  => ConfigBatalha::calcularAtributo((int) $linha["defesa_base"], $nivel),
                "ataque_especial"         => ConfigBatalha::calcularAtributo((int) $linha["ataque_esp_base"], $nivel),
                "defesa_especial"         => ConfigBatalha::calcularAtributo((int) $linha["defesa_esp_base"], $nivel),
                "velocidade"              => ConfigBatalha::calcularAtributo((int) $linha["velocidade_base"], $nivel),
                "status_atual"            => $statusAtual,
                "turnos_restantes_efeito" => $linha["turnos_restantes_efeito"] !== null
                                             ? (int) $linha["turnos_restantes_efeito"] : null,
                "is_ativo"                => (bool) $linha["is_ativo"],
                "golpes"                  => $golpes
            ];
        }

        return $monstros;
    }

    /**
     * Carrega os tipos elementais (fire, water, etc.) de um monstro.
     */
    private function carregarTiposMonstro(int $idMonstro): array
    {
        $dados = $this->Buscar([
            "colunas"  => "t.chave",
            "tabela"   => ["tipo_monstro tm", "JOIN tipo t", "ON t.id_tipo = tm.id_tipo"],
            "pesquisa" => "tm.id_monstro = :id ORDER BY tm.ordem",
            "valores"  => ["id" => $idMonstro]
        ], true);

        return array_map(fn($row) => $row["chave"], $dados);
    }

    /**
     * Carrega os dados completos dos até 4 golpes atribuídos ao monstro na batalha.
     */
    private function carregarGolpesMonstro(array $linha): array
    {
        $golpes = [];
        $sql = "SELECT 
                    a.id_ataque, a.nome, a.poder, a.precisao, a.chance_efeito,
                    a.id_categoria_dano,
                    t.chave AS tipo,
                    cd.nome AS classe_nome,
                    e.nome  AS efeito_nome
                FROM ataque a
                JOIN tipo t ON t.id_tipo = a.id_tipo
                JOIN categoria_dano cd ON cd.id_categoria_dano = a.id_categoria_dano
                LEFT JOIN efeito e ON e.id_efeito = a.id_efeito
                WHERE a.id_ataque = :id AND a.is_ativo = 1";
        $stmt = $this->pdo->prepare($sql);

        for ($i = 1; $i <= 4; $i++) {
            $idAtaque = $linha["id_ataque_{$i}"] ?? null;
            if ($idAtaque === null) {
                continue;
            }

            $stmt->execute(["id" => $idAtaque]);
            $ataque = $stmt->fetch();
            if (!$ataque) {
                continue;
            }

            $nomeTecnico = strtolower((string) $ataque["nome"]);
            $poder       = (int) ($ataque["poder"] ?? 0);
            $classe      = $this->resolverClasseDano(
                $poder,
                (int) $ataque["id_categoria_dano"],
                $ataque["classe_nome"]
            );
            $statusGolpe = $this->normalizarEfeitoGolpe($ataque["efeito_nome"]);

            $golpes[] = [
                "id"            => (int) $ataque["id_ataque"],
                "nome"          => ConfigBatalha::formatarNome($nomeTecnico),
                "poder"         => $poder,
                "precisao"      => $ataque["precisao"] !== null ? (int) $ataque["precisao"] : null,
                "tipo"          => $ataque["tipo"] ?? "normal",
                "classe"        => $classe,
                "prioridade"    => ConfigBatalha::PRIORIDADE_GOLPES[$nomeTecnico] ?? 0,
                "status"        => $statusGolpe,
                "chance_status" => $ataque["chance_efeito"] !== null
                                   ? (int) $ataque["chance_efeito"]
                                   : ($statusGolpe ? ($poder > 0 ? 10 : 100) : 0),
                "drenagem"      => ConfigBatalha::DRENAGEM_GOLPES[$nomeTecnico] ?? 0
            ];
        }

        if (empty($golpes)) {
            $golpes[] = [
                "id"            => 0,
                "nome"          => "Tackle",
                "poder"         => 40,
                "precisao"      => 100,
                "tipo"          => "normal",
                "classe"        => "physical",
                "prioridade"    => 0,
                "status"        => null,
                "chance_status" => 0,
                "drenagem"      => 0
            ];
        }

        return $golpes;
    }

    /**
     * Resolve corretamente se um golpe é 'physical', 'special' ou 'status'.
     *
     * Nota importante: nas seeds geradas pela PokeAPI (popularbanco.sql), os IDs da
     * categoria seguem o padrão da API (1 = status, 2 = physical, 3 = special), embora
     * a tabela categoria_dano tenha os textos cadastrados em outra ordem.
     */
    private function resolverClasseDano(int $poder, int $idCategoria, ?string $nomeCategoria): string
    {
        if ($poder <= 0) {
            return "status";
        }

        return match ($idCategoria) {
            2 => "physical",
            3 => "special",
            1 => "status",
            default => match (mb_strtolower(trim((string) $nomeCategoria))) {
                "special", "especial" => "special",
                "status"              => "status",
                default               => "physical"
            }
        };
    }

    /**
     * Converte o nome do efeito salvo no banco para o identificador usado no jogo.
     */
    private function normalizarEfeitoGolpe(?string $efeito): ?string
    {
        if ($efeito === null) {
            return null;
        }
        $lower = mb_strtolower(trim($efeito));
        $mapa  = [
            "queimadura"    => "burn",      "burn"      => "burn",
            "paralisia"     => "paralysis", "paralysis" => "paralysis",
            "envenenamento" => "poison",    "veneno"    => "poison", "poison" => "poison",
            "sono"          => "sleep",     "sleep"     => "sleep",
            "congelamento"  => "freeze",    "freeze"    => "freeze"
        ];
        return $mapa[$lower] ?? null;
    }

    /**
     * Converte o id_efeito do banco para o nome do status do jogo.
     */
    private function converterEfeitoParaStatus(int $idEfeito): ?string
    {
        if ($idEfeito === 0) {
            return null;
        }

        $efeito = $this->Buscar([
            "colunas"  => "nome",
            "tabela"   => "efeito",
            "pesquisa" => "id_efeito = :id",
            "valores"  => ["id" => $idEfeito]
        ]);

        return $efeito ? $this->normalizarEfeitoGolpe($efeito["nome"]) : null;
    }

    /**
     * Converte o status do jogo para o id_efeito correspondente na tabela efeito.
     */
    private function converterStatusParaEfeito(?string $status): ?int
    {
        if ($status === null) {
            return null;
        }

        // Busca diretamente pelo nome em inglês (padrão das seeds) ou fallback em português
        $efeito = $this->Buscar([
            "colunas"  => "id_efeito",
            "tabela"   => "efeito",
            "pesquisa" => "LOWER(nome) = LOWER(:nome)",
            "valores"  => ["nome" => $status]
        ]);

        if (!$efeito) {
            $mapaPt = [
                "burn"      => "Queimadura",
                "poison"    => "Envenenamento",
                "paralysis" => "Paralisia",
                "sleep"     => "Sono",
                "freeze"    => "Congelamento"
            ];
            if (isset($mapaPt[$status])) {
                $efeito = $this->Buscar([
                    "colunas"  => "id_efeito",
                    "tabela"   => "efeito",
                    "pesquisa" => "LOWER(nome) = LOWER(:nome)",
                    "valores"  => ["nome" => $mapaPt[$status]]
                ]);
            }
        }

        return $efeito ? (int) $efeito["id_efeito"] : null;
    }

    /**
     * Atualiza turno_atual e cooldowns na tabela batalha.
     */
    public function atualizarEstadoBatalha(int $idBatalha, array $estado): void
    {
        $this->Atualizar([
            "tabela"   => "batalha",
            "colunas"  => [
                "turno_atual"      => ":turno",
                "cooldown_troca_1" => ":cd1",
                "cooldown_troca_2" => ":cd2"
            ],
            "condicao" => "id_batalha = :batalha",
            "valores"  => [
                "turno"   => $estado["turno_atual"],
                "cd1"     => $estado["cooldown_troca_1"],
                "cd2"     => $estado["cooldown_troca_2"],
                "batalha" => $idBatalha
            ]
        ]);
    }

    /**
     * Atualiza vida_atual, is_ativo, efeito e turnos restantes de todos os monstros da partida.
     */
    public function atualizarEstadoMonstros(int $idBatalha, array $estado): void
    {
        $sql = "UPDATE monstro_batalha_usuario 
                SET vida_atual = :vida, is_ativo = :ativo,
                    id_efeito_atual = :efeito, turnos_restantes_efeito = :turnos
                WHERE id_batalha = :batalha AND id_usuario = :usuario AND id_monstro = :monstro";
        $stmt = $this->pdo->prepare($sql);

        foreach ($estado["monstros_jogador"] as $mon) {
            $stmt->execute([
                "vida"    => $mon["vida_atual"],
                "ativo"   => $mon["is_ativo"] ? 1 : 0,
                "efeito"  => $this->converterStatusParaEfeito($mon["status_atual"]),
                "turnos"  => $mon["turnos_restantes_efeito"],
                "batalha" => $idBatalha,
                "usuario" => $estado["id_usuario"],
                "monstro" => $mon["id_monstro"]
            ]);
        }

        foreach ($estado["monstros_robo"] as $mon) {
            $stmt->execute([
                "vida"    => $mon["vida_atual"],
                "ativo"   => $mon["is_ativo"] ? 1 : 0,
                "efeito"  => $this->converterStatusParaEfeito($mon["status_atual"]),
                "turnos"  => $mon["turnos_restantes_efeito"],
                "batalha" => $idBatalha,
                "usuario" => $estado["id_bot"],
                "monstro" => $mon["id_monstro"]
            ]);
        }
    }

    /**
     * Verifica se um item realmente produz algum efeito no pokémon escolhido.
     */
    private function itemServeParaMonstro(array $item, array $monstro): bool
    {
        $hp    = (int) $monstro["vida_atual"];
        $maxHp = (int) $monstro["hp_maximo"];

        if (!empty($item["reviver"])) {
            return $hp <= 0;
        }
        if ($hp <= 0) {
            return false;
        }

        $curaHp = !empty($item["cura_hp"]) && $hp < $maxHp;
        $status = $monstro["status_atual"] ?? null;
        $curaStatus = false;

        if ($status !== null && !empty($item["cura_status"])) {
            if ($item["cura_status"] === "todos") {
                $curaStatus = true;
            } else {
                $lista = array_map("trim", explode(",", (string) $item["cura_status"]));
                $curaStatus = in_array($status, $lista, true);
            }
        }

        return $curaHp || $curaStatus;
    }

    /**
     * Busca as propriedades de um item consumível de batalha pela chave.
     */
    public function buscarDadosItem(string $chave): ?array
    {
        $item = $this->Buscar([
            "colunas"  => "chave, nome, cura_hp, cura_status, reviver",
            "tabela"   => "item_loja",
            "pesquisa" => "chave = :chave AND categoria NOT IN ('evolucao', 'pokebola')",
            "valores"  => ["chave" => $chave]
        ]);

        if (!$item) {
            return null;
        }

        return [
            "chave"       => $item["chave"],
            "nome"        => $item["nome"],
            "cura_hp"     => (int) $item["cura_hp"],
            "cura_status" => $item["cura_status"],
            "reviver"     => (bool) $item["reviver"]
        ];
    }

    /**
     * Desconta 1 unidade de um item do inventário do jogador dentro da transação atual.
     */
    public function gastarItem(int $idUsuario, string $chave): bool
    {
        $item = $this->Buscar([
            "colunas"  => "1",
            "tabela"   => ["item_usuario iu", "JOIN item_loja i", "ON i.id_item_loja = iu.id_item_loja"],
            "pesquisa" => "iu.id_usuario = :id AND i.chave = :chave AND iu.quantidade > 0 AND i.categoria NOT IN ('evolucao', 'pokebola') FOR UPDATE",
            "valores"  => ["id" => $idUsuario, "chave" => $chave]
        ]);

        if (!$item) {
            return false;
        }

        $this->Atualizar([
            "tabela"   => "item_usuario iu JOIN item_loja i ON i.id_item_loja = iu.id_item_loja",
            "colunas"  => ["iu.quantidade" => "iu.quantidade - 1"],
            "condicao" => "iu.id_usuario = :id AND i.chave = :chave",
            "valores"  => ["id" => $idUsuario, "chave" => $chave]
        ]);

        return true;
    }

    /**
     * Retorna o estado inicial completo da batalha para o frontend montar a tela
     * usando exatamente os mesmos pokémons, golpes e stats salvos no banco.
     */
    public function estadoInicialParaCliente(int $idBatalha, int $idUsuario): array
    {
        $estado = $this->carregarEstadoBatalha($idBatalha, $idUsuario);

        $formatarMonstro = function (array $mon): array {
            return [
                "id"       => $mon["id_monstro"],
                "nome"     => $mon["nome"],
                "nivel"    => $mon["nivel"],
                "tipos"    => $mon["tipos"],
                "hp"       => $mon["vida_atual"],
                "maxHp"    => $mon["hp_maximo"],
                "atk"      => $mon["ataque"],
                "def"      => $mon["defesa"],
                "spa"      => $mon["ataque_especial"],
                "spd"      => $mon["defesa_especial"],
                "spe"      => $mon["velocidade"],
                "status"   => $mon["status_atual"],
                "is_ativo" => $mon["is_ativo"],
                "golpes"   => array_map(fn(array $g): array => [
                    "nome"       => $g["nome"],
                    "tipo"       => $g["tipo"],
                    "poder"      => $g["poder"],
                    "precisao"   => $g["precisao"],
                    "classe"     => $g["classe"],
                    "prioridade" => $g["prioridade"],
                    "status"     => $g["status"]
                ], $mon["golpes"])
            ];
        };

        return [
            "turno"           => $estado["turno_atual"],
            "cooldownTroca"   => $estado["cooldown_troca_1"],
            "aguardandoTroca" => $estado["aguardando_troca"],
            "is_boss"         => $estado["is_boss"],
            "jogador"         => array_map($formatarMonstro, $estado["monstros_jogador"]),
            "robo"            => array_map($formatarMonstro, $estado["monstros_robo"])
        ];
    }
}
