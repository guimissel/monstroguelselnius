<?php

/**
 * MotorDeBatalha — Motor autoritativo que executa TODA a lógica de combate no servidor.
 *
 * Por segurança contra trapaças (como alterar o JS pelo DevTools para dar dano infinito
 * ou forjar vitória), nenhuma decisão crítica da batalha é confiada ao navegador:
 *   - O navegador envia APENAS a intenção do jogador (ex.: usar golpe 0, trocar para índice 2).
 *   - Este motor carrega os atributos reais do banco, decide a jogada do robô, calcula a
 *     ordem dos ataques, precisão, crítico, STAB, efetividade de tipos, efeitos de status,
 *     drenagem/recuo, dano contínuo de fim de turno e desmaios.
 *   - Ao final, devolve uma lista ordenada de "eventos" para o JavaScript apenas reproduzir
 *     visualmente (mensagens, animações, barras de HP e trocas).
 *
 * @author Miguel Granville / Vinicius Grub
 */
class MotorDeBatalha
{
    // ===================== TABELA DE EFETIVIDADE DE TIPOS =====================
    // Espelha exatamente as traits em app/model/tipos/ (padrão oficial com 18 tipos).

    /**
     * Tipos contra os quais cada tipo de golpe causa dano dobrado (2x).
     *
     * @var array<string, string[]>
     */
    private const TIPO_FORTE = [
        'normal'   => [],
        'fire'     => ['grass', 'ice', 'bug', 'steel'],
        'water'    => ['fire', 'ground', 'rock'],
        'electric' => ['water', 'flying'],
        'grass'    => ['water', 'ground', 'rock'],
        'ice'      => ['grass', 'ground', 'flying', 'dragon'],
        'fighting' => ['normal', 'ice', 'rock', 'dark', 'steel'],
        'poison'   => ['grass', 'fairy'],
        'ground'   => ['fire', 'electric', 'poison', 'rock', 'steel'],
        'flying'   => ['grass', 'fighting', 'bug'],
        'psychic'  => ['fighting', 'poison'],
        'bug'      => ['grass', 'psychic', 'dark'],
        'rock'     => ['fire', 'ice', 'flying', 'bug'],
        'ghost'    => ['psychic', 'ghost'],
        'dragon'   => ['dragon'],
        'dark'     => ['psychic', 'ghost'],
        'steel'    => ['ice', 'rock', 'fairy'],
        'fairy'    => ['fighting', 'dragon', 'dark']
    ];

    /**
     * Tipos contra os quais cada tipo de golpe causa metade do dano (0.5x).
     *
     * @var array<string, string[]>
     */
    private const TIPO_FRACO = [
        'normal'   => ['rock', 'steel'],
        'fire'     => ['fire', 'water', 'rock', 'dragon'],
        'water'    => ['water', 'grass', 'dragon'],
        'electric' => ['electric', 'grass', 'dragon'],
        'grass'    => ['fire', 'grass', 'poison', 'flying', 'bug', 'dragon', 'steel'],
        'ice'      => ['fire', 'water', 'ice', 'steel'],
        'fighting' => ['poison', 'flying', 'psychic', 'bug', 'fairy'],
        'poison'   => ['poison', 'ground', 'rock', 'ghost'],
        'ground'   => ['grass', 'bug'],
        'flying'   => ['electric', 'rock', 'steel'],
        'psychic'  => ['psychic', 'steel'],
        'bug'      => ['fire', 'fighting', 'poison', 'flying', 'ghost', 'steel', 'fairy'],
        'rock'     => ['fighting', 'ground', 'steel'],
        'ghost'    => ['dark'],
        'dragon'   => ['steel'],
        'dark'     => ['fighting', 'dark', 'fairy'],
        'steel'    => ['fire', 'water', 'electric', 'steel'],
        'fairy'    => ['fire', 'poison', 'steel']
    ];

    /**
     * Tipos completamente imunes a cada tipo de golpe (0x de dano).
     *
     * @var array<string, string[]>
     */
    private const TIPO_IMUNE = [
        'normal'   => ['ghost'],
        'fire'     => [],
        'water'    => [],
        'electric' => ['ground'],
        'grass'    => [],
        'ice'      => [],
        'fighting' => ['ghost'],
        'poison'   => ['steel'],
        'ground'   => ['flying'],
        'flying'   => [],
        'psychic'  => ['dark'],
        'bug'      => [],
        'rock'     => [],
        'ghost'    => ['normal'],
        'dragon'   => ['fairy'],
        'dark'     => [],
        'steel'    => [],
        'fairy'    => []
    ];

    /** @var callable|null Callback opcional para consumir item no banco dentro da transação */
    private $consumirItemCallback;

    /**
     * Construtor do motor de batalha.
     *
     * @param callable|null $consumirItemCallback Função fn(string $chave): array|null que valida e consome 1 item no banco
     */
    public function __construct(?callable $consumirItemCallback = null)
    {
        $this->consumirItemCallback = $consumirItemCallback;
    }

    // ===================== PROCESSAMENTO PRINCIPAL DO TURNO =====================

    /**
     * Executa um turno (ou troca forçada) da batalha a partir do estado salvo no banco.
     *
     * Estrutura esperada de $estado:
     *   - turno_atual, cooldown_troca_1, is_boss, status
     *   - aguardando_troca (bool): true quando o pokémon do jogador desmaiou e ele deve escolher o substituto
     *   - monstros_jogador / monstros_robo: listas ordenadas de monstros com stats, golpes e status
     *
     * @param array $estado Estado completo da batalha (modificado por referência)
     * @param array $acao   Ação enviada pelo jogador: ['tipo' => 'golpe'|'trocar'|'trocar_forcado'|'item'|'fugir', ...]
     * @return array        ['eventos' => [...], 'estado' => [...]]
     * @throws RegraDeJogoException Se a ação não for permitida nas regras atuais da batalha
     */
    public function processarTurno(array &$estado, array $acao): array
    {
        // Só permite processar ações em batalhas que ainda estejam em andamento
        if (($estado['status'] ?? '') !== 'em_andamento') {
            throw new RegraDeJogoException("Essa batalha já foi finalizada", 409);
        }

        $tipoAcao = (string) ($acao['tipo'] ?? '');
        $eventos  = [];

        // Se o pokémon ativo do jogador desmaiou no turno anterior e ainda há vivos,
        // a única ação permitida agora é escolher o substituto (ou fugir).
        if (!empty($estado['aguardando_troca']) && !in_array($tipoAcao, ['trocar_forcado', 'fugir'], true)) {
            throw new RegraDeJogoException("Escolha o próximo pokémon antes de continuar", 409);
        }

        // ---- 1. Executa a ação solicitada pelo jogador ----
        switch ($tipoAcao) {
            case 'fugir':
                // Fuga encerra imediatamente a batalha sem recompensa
                $eventos[] = $this->evento('mensagem', ['texto' => 'Você fugiu da batalha!']);
                $estado['fim']      = true;
                $estado['vencedor'] = 'fuga';
                $estado['aguardando_troca'] = false;

                return [
                    'eventos' => $eventos,
                    'estado'  => $this->montarEstadoCliente($estado)
                ];

            case 'trocar_forcado':
                // Troca obrigatória após desmaio: NÃO consome turno e o robô NÃO ataca de novo
                if (empty($estado['aguardando_troca'])) {
                    throw new RegraDeJogoException("Não há troca forçada pendente", 409);
                }

                $indice = filter_var($acao['indice'] ?? null, FILTER_VALIDATE_INT);
                if ($indice === false) {
                    throw new RegraDeJogoException("Índice de pokémon inválido", 400);
                }

                $eventos = array_merge($eventos, $this->executarTrocaForcada($estado, (int) $indice));

                return [
                    'eventos' => $eventos,
                    'estado'  => $this->montarEstadoCliente($estado)
                ];

            case 'trocar':
                // Troca voluntária: gasta o turno do jogador, aplica cooldown e o robô ataca
                $indice = filter_var($acao['indice'] ?? null, FILTER_VALIDATE_INT);
                if ($indice === false) {
                    throw new RegraDeJogoException("Índice de pokémon inválido", 400);
                }

                $this->validarTrocaVoluntaria($estado, (int) $indice);
                $eventos = array_merge($eventos, $this->executarTrocaVoluntaria($estado, (int) $indice));
                $estado['cooldown_troca_1'] = ConfigBatalha::COOLDOWN_TROCA;

                // Enquanto o jogador troca, o robô aproveita para atacar
                $eventos = array_merge($eventos, $this->executarAtaqueRobo($estado));
                break;

            case 'item':
                // Uso de item: consome do banco, aplica efeito no alvo e o robô ataca
                $chave = trim((string) ($acao['item'] ?? ''));
                $alvo  = filter_var($acao['alvo'] ?? null, FILTER_VALIDATE_INT);
                if ($chave === '' || $alvo === false) {
                    throw new RegraDeJogoException("Dados do item inválidos", 400);
                }

                $eventos = array_merge($eventos, $this->executarUsoDeItem($estado, $chave, (int) $alvo));

                // Após usar item, o robô executa seu ataque se a batalha não acabou
                if (empty($estado['fim'])) {
                    $eventos = array_merge($eventos, $this->executarAtaqueRobo($estado));
                }
                break;

            case 'golpe':
                // Combate direto: ambos escolhem golpes e atacam na ordem de prioridade/velocidade
                $indiceGolpe = filter_var($acao['indice'] ?? null, FILTER_VALIDATE_INT);
                if ($indiceGolpe === false) {
                    throw new RegraDeJogoException("Índice de golpe inválido", 400);
                }

                $this->validarGolpeJogador($estado, (int) $indiceGolpe);
                $eventos = array_merge($eventos, $this->executarRodadaDeGolpes($estado, (int) $indiceGolpe));
                break;

            default:
                throw new RegraDeJogoException("Tipo de ação inválido", 400);
        }

        // ---- 2. Efeitos de fim de turno (veneno e queimadura) ----
        if (empty($estado['fim'])) {
            $eventos = array_merge($eventos, $this->aplicarDanoDeStatusFimDoTurno($estado));
        }

        // ---- 3. Se o ativo do jogador desmaiou durante a rodada e ainda há vivos, solicita troca ----
        if (empty($estado['fim'])) {
            $idxAtivoJogador = $this->indiceAtivo($estado['monstros_jogador']);
            if ($idxAtivoJogador === null || $estado['monstros_jogador'][$idxAtivoJogador]['vida_atual'] <= 0) {
                $proximoVivo = $this->indiceProximoVivo($estado['monstros_jogador']);
                if ($proximoVivo === null) {
                    // Todos do jogador desmaiaram -> derrota
                    $estado['fim']      = true;
                    $estado['vencedor'] = 'robo';
                    $eventos[] = $this->evento('mensagem', ['texto' => 'Todos os seus pokémons desmaiaram...']);
                } else {
                    // Desativa o desmaiado e aguarda o jogador escolher o próximo na tela
                    if ($idxAtivoJogador !== null) {
                        $estado['monstros_jogador'][$idxAtivoJogador]['is_ativo'] = false;
                    }
                    $estado['aguardando_troca'] = true;
                    $eventos[] = $this->evento('troca_forcada', ['lado' => 'jogador']);
                }
            }
        }

        // ---- 4. Avança contador de turno e reduz cooldown de troca ----
        if (empty($estado['fim'])) {
            $estado['turno_atual']++;
            if ($estado['cooldown_troca_1'] > 0) {
                $estado['cooldown_troca_1']--;
            }
        }

        return [
            'eventos' => $eventos,
            'estado'  => $this->montarEstadoCliente($estado)
        ];
    }

    // ===================== RESOLUÇÃO DA RODADA DE GOLPES =====================

    /**
     * Resolve uma rodada onde jogador e robô usam golpes.
     * Ordena por: prioridade do golpe > velocidade efetiva > desempate aleatório.
     */
    private function executarRodadaDeGolpes(array &$estado, int $indiceGolpeJogador): array
    {
        $eventos = [];

        $idxJogador = $this->indiceAtivo($estado['monstros_jogador']);
        $idxRobo    = $this->indiceAtivo($estado['monstros_robo']);

        $monJogador = $estado['monstros_jogador'][$idxJogador];
        $monRobo    = $estado['monstros_robo'][$idxRobo];

        $golpeJogador = $monJogador['golpes'][$indiceGolpeJogador];
        $indiceRobo   = $this->iaEscolherGolpe($estado);
        $golpeRobo    = $monRobo['golpes'][$indiceRobo];

        // Define a ordem de execução dos ataques
        $ordem = [
            [
                'lado'       => 'jogador',
                'id_monstro' => $monJogador['id_monstro'],
                'golpe'      => $golpeJogador,
                'prioridade' => (int) ($golpeJogador['prioridade'] ?? 0),
                'velocidade' => $this->velocidadeEfetiva($monJogador)
            ],
            [
                'lado'       => 'robo',
                'id_monstro' => $monRobo['id_monstro'],
                'golpe'      => $golpeRobo,
                'prioridade' => (int) ($golpeRobo['prioridade'] ?? 0),
                'velocidade' => $this->velocidadeEfetiva($monRobo)
            ]
        ];

        usort($ordem, function (array $a, array $b): int {
            if ($b['prioridade'] !== $a['prioridade']) {
                return $b['prioridade'] <=> $a['prioridade'];
            }
            if ($b['velocidade'] !== $a['velocidade']) {
                return $b['velocidade'] <=> $a['velocidade'];
            }
            // Empate exato de prioridade e velocidade: sorteio justo
            return random_int(0, 1) === 0 ? -1 : 1;
        });

        // Executa cada ataque respeitando se o atacante ainda está vivo e ativo
        foreach ($ordem as $passo) {
            if (!empty($estado['fim'])) {
                break;
            }

            $lado    = $passo['lado'];
            $chave   = $lado === 'jogador' ? 'monstros_jogador' : 'monstros_robo';
            $idxAtk  = $this->indiceAtivo($estado[$chave]);

            // Se o pokémon desmaiou antes de agir (ou foi substituído), ele perde o turno
            if ($idxAtk === null) {
                continue;
            }
            $monAtual = $estado[$chave][$idxAtk];
            if ($monAtual['id_monstro'] !== $passo['id_monstro'] || $monAtual['vida_atual'] <= 0) {
                continue;
            }

            $eventos = array_merge($eventos, $this->executarAtaque($estado, $lado, $passo['golpe']));
        }

        return $eventos;
    }

    /**
     * Executa o ataque do robô sozinho (chamado após troca ou uso de item do jogador).
     */
    private function executarAtaqueRobo(array &$estado): array
    {
        if (!empty($estado['fim'])) {
            return [];
        }

        $idxRobo = $this->indiceAtivo($estado['monstros_robo']);
        if ($idxRobo === null || $estado['monstros_robo'][$idxRobo]['vida_atual'] <= 0) {
            return [];
        }

        $indiceGolpe = $this->iaEscolherGolpe($estado);
        $golpe       = $estado['monstros_robo'][$idxRobo]['golpes'][$indiceGolpe];

        return $this->executarAtaque($estado, 'robo', $golpe);
    }

    /**
     * Executa um ataque individual de $lado contra o oponente.
     */
    private function executarAtaque(array &$estado, string $lado, array $golpe): array
    {
        $eventos  = [];
        $ladoAlvo = $lado === 'jogador' ? 'robo' : 'jogador';

        $chaveAtk = $lado === 'jogador' ? 'monstros_jogador' : 'monstros_robo';
        $chaveDef = $ladoAlvo === 'jogador' ? 'monstros_jogador' : 'monstros_robo';

        $idxAtk = $this->indiceAtivo($estado[$chaveAtk]);
        $idxDef = $this->indiceAtivo($estado[$chaveDef]);

        if ($idxAtk === null || $idxDef === null) {
            return $eventos;
        }

        $atacante = &$estado[$chaveAtk][$idxAtk];
        $defensor = &$estado[$chaveDef][$idxDef];

        // Se o defensor já está desmaiado, não há alvo válido neste momento
        if ($defensor['vida_atual'] <= 0) {
            return $eventos;
        }

        // 1. Verifica condições impeditivas (sono, congelamento, paralisia)
        $checagem = $this->verificarPodeAgir($lado, $atacante);
        $eventos  = array_merge($eventos, $checagem['eventos']);
        if (!$checagem['pode_agir']) {
            return $eventos;
        }

        // 2. Anuncia o golpe e dispara a animação no frontend
        $nomeAtk = $this->nomeLog($lado, $atacante);
        $eventos[] = $this->evento('mensagem', ['texto' => "{$nomeAtk} usou {$golpe['nome']}!"]);
        $eventos[] = $this->evento('animacao_ataque', ['lado' => $lado]);

        // 3. Verifica precisão do golpe (precisao === null significa golpe que nunca erra, ex.: Swift)
        if ($golpe['precisao'] !== null && mt_rand(1, 100) > (int) $golpe['precisao']) {
            $eventos[] = $this->evento('mensagem', ['texto' => 'Mas errou!']);
            return $eventos;
        }

        // 4. Golpe sem poder base: aplica apenas condição de status
        if (empty($golpe['poder'])) {
            if (!empty($golpe['status'])) {
                $eventos = array_merge(
                    $eventos,
                    $this->tentarAplicarStatus($ladoAlvo, $defensor, $golpe['status'], true)
                );
            }
            return $eventos;
        }

        // 5. Calcula o dano usando atributos reais, STAB, efetividade e crítico
        $calc = $this->calcularDano($atacante, $defensor, $golpe);

        // Imunidade total de tipo -> não causa dano
        if ($calc['efetividade'] === 0.0) {
            $nomeDef = $this->nomeLog($ladoAlvo, $defensor);
            $eventos[] = $this->evento('mensagem', ['texto' => "Não afeta {$nomeDef}..."]);
            return $eventos;
        }

        // 6. Aplica o dano no HP do defensor
        $danoReal = $this->aplicarDano($defensor, $calc['dano']);
        $eventos[] = $this->evento('dano', [
            'lado'  => $ladoAlvo,
            'dano'  => $danoReal,
            'hp'    => $defensor['vida_atual'],
            'maxHp' => $defensor['hp_maximo']
        ]);

        // 7. Mensagens de crítico e multiplicador elemental
        if ($calc['critico']) {
            $eventos[] = $this->evento('mensagem', ['texto' => 'Foi um acerto crítico!']);
        }
        if ($calc['efetividade'] >= 4.0) {
            $eventos[] = $this->evento('mensagem', ['texto' => 'É supereficaz! (4x)']);
        } elseif ($calc['efetividade'] > 1.0) {
            $eventos[] = $this->evento('mensagem', ['texto' => 'É supereficaz!']);
        } elseif ($calc['efetividade'] <= 0.25) {
            $eventos[] = $this->evento('mensagem', ['texto' => 'É pouco eficaz... (0.25x)']);
        } elseif ($calc['efetividade'] < 1.0) {
            $eventos[] = $this->evento('mensagem', ['texto' => 'É pouco eficaz...']);
        }

        // 8. Drenagem (cura o atacante) ou recuo (fere o atacante)
        $drenagem = (int) ($golpe['drenagem'] ?? 0);
        if ($drenagem !== 0 && $danoReal > 0) {
            $valor = (int) floor($danoReal * abs($drenagem) / 100);
            if ($drenagem > 0 && $valor > 0) {
                $curado = $this->aplicarCura($atacante, $valor);
                if ($curado > 0) {
                    $eventos[] = $this->evento('atualizar_info', [
                        'lado'   => $lado,
                        'hp'     => $atacante['vida_atual'],
                        'maxHp'  => $atacante['hp_maximo'],
                        'status' => $atacante['status_atual']
                    ]);
                    $eventos[] = $this->evento('mensagem', ['texto' => "{$nomeAtk} recuperou HP!"]);
                }
            } elseif ($drenagem < 0 && $valor > 0) {
                $recuo = $this->aplicarDano($atacante, $valor);
                $eventos[] = $this->evento('dano', [
                    'lado'  => $lado,
                    'dano'  => $recuo,
                    'hp'    => $atacante['vida_atual'],
                    'maxHp' => $atacante['hp_maximo']
                ]);
                $eventos[] = $this->evento('mensagem', ['texto' => "{$nomeAtk} se machucou com o recuo!"]);
            }
        }

        // 9. Chance secundária de aplicar status (ex.: Ember tem 10% de queimar)
        if ($defensor['vida_atual'] > 0 && !empty($golpe['status'])) {
            $chance = (int) ($golpe['chance_status'] ?? 10);
            if ($chance > 0 && mt_rand(1, 100) <= $chance) {
                $eventos = array_merge(
                    $eventos,
                    $this->tentarAplicarStatus($ladoAlvo, $defensor, $golpe['status'], false)
                );
            }
        }

        // 10. Verifica desmaio do defensor e (por recuo) do atacante
        if ($defensor['vida_atual'] <= 0) {
            $eventos = array_merge($eventos, $this->processarDesmaio($estado, $ladoAlvo));
        }

        if (empty($estado['fim']) && $atacante['vida_atual'] <= 0) {
            $eventos = array_merge($eventos, $this->processarDesmaio($estado, $lado));
        }

        return $eventos;
    }

    // ===================== CÁLCULO DE DANO E EFETIVIDADE =====================

    /**
     * Calcula o dano de um golpe seguindo exatamente a fórmula clássica do jogo.
     *
     * Fórmula:
     *   base  = floor((floor(2 * nivel / 5) + 2) * poder * ataque / defesa / 50) + 2
     *   total = floor(base * STAB * efetividade * critico * aleatorio[0.85..1.00])
     *
     * @param array $atacante Dados do pokémon atacante
     * @param array $defensor Dados do pokémon defensor
     * @param array $golpe    Dados do golpe
     * @return array          ['dano' => int, 'efetividade' => float, 'critico' => bool]
     */
    public function calcularDano(array $atacante, array $defensor, array $golpe): array
    {
        $fisico = ($golpe['classe'] ?? 'physical') === 'physical';
        $ataque = $fisico ? (int) $atacante['ataque'] : (int) $atacante['ataque_especial'];
        $defesa = $fisico ? (int) $defensor['defesa'] : (int) $defensor['defesa_especial'];

        // Queimadura reduz o ataque físico efetivo pela metade
        if ($fisico && ($atacante['status_atual'] ?? null) === 'burn') {
            $ataque = max(1, (int) floor($ataque / 2));
        }

        $nivel       = (int) ($atacante['nivel'] ?? ConfigBatalha::NIVEL_JOGADOR);
        $stab        = in_array($golpe['tipo'], $atacante['tipos'] ?? [], true) ? ConfigBatalha::MULT_STAB : 1.0;
        $efetividade = $this->calcularMultiplicador($golpe['tipo'], $defensor['tipos'] ?? []);
        $critico     = $this->chance(ConfigBatalha::CHANCE_CRITICO);
        $aleatorio   = 0.85 + (mt_rand(0, 1500) / 10000.0);

        $base = (int) floor(
            ((int) floor((2 * $nivel) / 5) + 2) * (int) $golpe['poder'] * $ataque / max(1, $defesa) / 50
        ) + 2;

        $total = (int) floor(
            $base * $stab * $efetividade * ($critico ? ConfigBatalha::MULT_CRITICO : 1.0) * $aleatorio
        );

        return [
            'dano'        => $efetividade === 0.0 ? 0 : max(1, $total),
            'efetividade' => $efetividade,
            'critico'     => $critico
        ];
    }

    /**
     * Calcula o multiplicador de efetividade elemental de um tipo de golpe contra
     * os tipos de um pokémon defensor (0x, 0.25x, 0.5x, 1x, 2x ou 4x).
     *
     * @param string   $tipoGolpe     Chave do tipo do golpe (ex.: 'water')
     * @param string[] $tiposDefensor Lista de tipos do defensor (ex.: ['fire', 'flying'])
     * @return float                  Multiplicador final
     */
    public function calcularMultiplicador(string $tipoGolpe, array $tiposDefensor): float
    {
        $mult  = 1.0;
        $forte = self::TIPO_FORTE[$tipoGolpe] ?? [];
        $fraco = self::TIPO_FRACO[$tipoGolpe] ?? [];
        $imune = self::TIPO_IMUNE[$tipoGolpe] ?? [];

        foreach ($tiposDefensor as $tipo) {
            if (in_array($tipo, $imune, true)) {
                return 0.0;
            }
            if (in_array($tipo, $forte, true)) {
                $mult *= 2.0;
            } elseif (in_array($tipo, $fraco, true)) {
                $mult *= 0.5;
            }
        }

        return $mult;
    }

    // ===================== CONDIÇÕES DE STATUS =====================

    /**
     * Verifica se condições impeditivas (sono, congelamento, paralisia) impedem o ataque.
     */
    private function verificarPodeAgir(string $lado, array &$monstro): array
    {
        $eventos = [];
        $nome    = $this->nomeLog($lado, $monstro);

        // Sono: reduz o contador; quando zera, o pokémon acorda e pode agir
        if (($monstro['status_atual'] ?? null) === 'sleep') {
            $restante = (int) ($monstro['turnos_restantes_efeito'] ?? 0);
            if ($restante > 0) {
                $monstro['turnos_restantes_efeito'] = $restante - 1;
                $eventos[] = $this->evento('mensagem', ['texto' => "{$nome} está dormindo..."]);
                return ['pode_agir' => false, 'eventos' => $eventos];
            }

            $monstro['status_atual']            = null;
            $monstro['turnos_restantes_efeito'] = null;
            $eventos[] = $this->evento('atualizar_info', [
                'lado'   => $lado,
                'hp'     => $monstro['vida_atual'],
                'maxHp'  => $monstro['hp_maximo'],
                'status' => null
            ]);
            $eventos[] = $this->evento('mensagem', ['texto' => "{$nome} acordou!"]);
        }

        // Congelamento: 20% de chance de descongelar no turno
        if (($monstro['status_atual'] ?? null) === 'freeze') {
            if ($this->chance(ConfigBatalha::CHANCE_DESCONGELAR)) {
                $monstro['status_atual']            = null;
                $monstro['turnos_restantes_efeito'] = null;
                $eventos[] = $this->evento('atualizar_info', [
                    'lado'   => $lado,
                    'hp'     => $monstro['vida_atual'],
                    'maxHp'  => $monstro['hp_maximo'],
                    'status' => null
                ]);
                $eventos[] = $this->evento('mensagem', ['texto' => "{$nome} descongelou!"]);
            } else {
                $eventos[] = $this->evento('mensagem', ['texto' => "{$nome} está congelado e não consegue se mover!"]);
                return ['pode_agir' => false, 'eventos' => $eventos];
            }
        }

        // Paralisia: 25% de chance de falhar o movimento
        if (($monstro['status_atual'] ?? null) === 'paralysis' && $this->chance(ConfigBatalha::CHANCE_PARALISIA)) {
            $eventos[] = $this->evento('mensagem', ['texto' => "{$nome} está paralisado! Não consegue se mover!"]);
            return ['pode_agir' => false, 'eventos' => $eventos];
        }

        return ['pode_agir' => true, 'eventos' => $eventos];
    }

    /**
     * Tenta aplicar um status no alvo, respeitando status já existente e imunidades de tipo.
     */
    private function tentarAplicarStatus(string $lado, array &$alvo, string $status, bool $avisarFalha): array
    {
        $eventos = [];
        if (!in_array($status, ConfigBatalha::STATUS_VALIDOS, true)) {
            return $eventos;
        }

        $nome   = $this->nomeLog($lado, $alvo);
        $imunes = ConfigBatalha::IMUNE_STATUS[$status] ?? [];
        $ehImune = false;

        foreach ($alvo['tipos'] ?? [] as $tipo) {
            if (in_array($tipo, $imunes, true)) {
                $ehImune = true;
                break;
            }
        }

        // Já possui algum status ou é imune pelo tipo
        if (!empty($alvo['status_atual']) || $ehImune) {
            if ($avisarFalha) {
                $msg = !empty($alvo['status_atual']) ? 'Mas falhou!' : "Não afeta {$nome}...";
                $eventos[] = $this->evento('mensagem', ['texto' => $msg]);
            }
            return $eventos;
        }

        $alvo['status_atual'] = $status;
        if ($status === 'sleep') {
            $alvo['turnos_restantes_efeito'] = random_int(1, 3);
        } else {
            $alvo['turnos_restantes_efeito'] = null;
        }

        $frases = [
            'paralysis' => 'ficou paralisado!',
            'burn'      => 'foi queimado!',
            'poison'    => 'foi envenenado!',
            'sleep'     => 'caiu no sono!',
            'freeze'    => 'foi congelado!'
        ];

        $eventos[] = $this->evento('atualizar_info', [
            'lado'   => $lado,
            'hp'     => $alvo['vida_atual'],
            'maxHp'  => $alvo['hp_maximo'],
            'status' => $status
        ]);
        $eventos[] = $this->evento('mensagem', ['texto' => "{$nome} {$frases[$status]}"]);

        return $eventos;
    }

    /**
     * Aplica o dano contínuo de veneno e queimadura ao final da rodada.
     */
    private function aplicarDanoDeStatusFimDoTurno(array &$estado): array
    {
        $eventos = [];

        foreach (['jogador', 'robo'] as $lado) {
            if (!empty($estado['fim'])) {
                break;
            }

            $chave = $lado === 'jogador' ? 'monstros_jogador' : 'monstros_robo';
            $idx   = $this->indiceAtivo($estado[$chave]);
            if ($idx === null) {
                continue;
            }

            $mon = &$estado[$chave][$idx];
            if ($mon['vida_atual'] <= 0) {
                continue;
            }

            $status = $mon['status_atual'] ?? null;
            if ($status !== 'poison' && $status !== 'burn') {
                continue;
            }

            $nome = $this->nomeLog($lado, $mon);
            $desc = $status === 'poison' ? 'o veneno' : 'a queimadura';
            $dano = max(1, (int) floor($mon['hp_maximo'] / ConfigBatalha::DIVISOR_DANO_DOT));
            $real = $this->aplicarDano($mon, $dano);

            $eventos[] = $this->evento('mensagem', ['texto' => "{$nome} sofre com {$desc}!"]);
            $eventos[] = $this->evento('dano', [
                'lado'  => $lado,
                'dano'  => $real,
                'hp'    => $mon['vida_atual'],
                'maxHp' => $mon['hp_maximo']
            ]);

            if ($mon['vida_atual'] <= 0) {
                $eventos = array_merge($eventos, $this->processarDesmaio($estado, $lado));
            }
        }

        return $eventos;
    }

    // ===================== DESMAIOS, TROCAS E ITENS =====================

    /**
     * Processa o desmaio do pokémon ativo de $lado.
     * Se for o robô e houver reserva viva, entra automaticamente.
     * Se for o jogador e houver reserva viva, a decisão fica para a troca forçada no fim do turno.
     */
    private function processarDesmaio(array &$estado, string $lado): array
    {
        $eventos = [];
        $chave   = $lado === 'jogador' ? 'monstros_jogador' : 'monstros_robo';
        $idx     = $this->indiceAtivo($estado[$chave]);

        if ($idx === null) {
            return $eventos;
        }

        $mon = &$estado[$chave][$idx];
        $nome = $this->nomeLog($lado, $mon);

        $eventos[] = $this->evento('desmaio', [
            'lado'  => $lado,
            'texto' => "{$nome} desmaiou!"
        ]);

        // Procura se ainda existe algum outro pokémon vivo neste time
        $proximo = $this->indiceProximoVivo($estado[$chave]);

        if ($proximo === null) {
            $estado['fim']      = true;
            $estado['vencedor'] = $lado === 'jogador' ? 'robo' : 'jogador';

            if ($lado === 'jogador') {
                $eventos[] = $this->evento('mensagem', ['texto' => 'Todos os seus pokémons desmaiaram...']);
            } else {
                $chefe = !empty($estado['is_boss']);
                $eventos[] = $this->evento('mensagem', [
                    'texto' => $chefe ? 'Você derrotou o chefe!' : 'Você venceu a batalha!'
                ]);
            }
            return $eventos;
        }

        if ($lado === 'robo') {
            // O robô troca automaticamente para o próximo vivo
            $mon['is_ativo'] = false;
            $estado[$chave][$proximo]['is_ativo'] = true;
            $novo = $estado[$chave][$proximo];

            $eventos[] = $this->evento('troca', [
                'lado'   => 'robo',
                'indice' => $proximo,
                'texto'  => "O robô enviou {$novo['nome']}!"
            ]);
        }

        return $eventos;
    }

    /**
     * Valida a troca voluntária solicitada pelo jogador.
     */
    private function validarTrocaVoluntaria(array $estado, int $indice): void
    {
        if ($estado['cooldown_troca_1'] > 0) {
            throw new RegraDeJogoException(
                "Troca bloqueada por mais {$estado['cooldown_troca_1']} round(s).",
                409
            );
        }

        $equipe = $estado['monstros_jogador'];
        if ($indice < 0 || $indice >= count($equipe)) {
            throw new RegraDeJogoException("Índice de pokémon inválido", 400);
        }
        if ($equipe[$indice]['is_ativo']) {
            throw new RegraDeJogoException("Esse pokémon já está em batalha", 409);
        }
        if ($equipe[$indice]['vida_atual'] <= 0) {
            throw new RegraDeJogoException("Não é possível trocar por um pokémon desmaiado", 409);
        }
    }

    /**
     * Executa a troca voluntária do jogador.
     */
    private function executarTrocaVoluntaria(array &$estado, int $indice): array
    {
        $eventos  = [];
        $idxAtual = $this->indiceAtivo($estado['monstros_jogador']);

        if ($idxAtual !== null) {
            $atual = &$estado['monstros_jogador'][$idxAtual];
            $eventos[] = $this->evento('mensagem', ['texto' => "Volte, {$atual['nome']}!"]);
            $atual['is_ativo'] = false;
        }

        $estado['monstros_jogador'][$indice]['is_ativo'] = true;
        $novo = $estado['monstros_jogador'][$indice];

        $eventos[] = $this->evento('troca', [
            'lado'   => 'jogador',
            'indice' => $indice,
            'texto'  => "Vai, {$novo['nome']}!"
        ]);

        return $eventos;
    }

    /**
     * Executa a troca obrigatória após o pokémon ativo do jogador desmaiar.
     */
    private function executarTrocaForcada(array &$estado, int $indice): array
    {
        $equipe = $estado['monstros_jogador'];
        if ($indice < 0 || $indice >= count($equipe)) {
            throw new RegraDeJogoException("Índice de pokémon inválido", 400);
        }
        if ($equipe[$indice]['vida_atual'] <= 0) {
            throw new RegraDeJogoException("Escolha um pokémon que ainda esteja vivo", 409);
        }

        // Garante que apenas o escolhido fique ativo
        foreach ($estado['monstros_jogador'] as $i => &$mon) {
            $mon['is_ativo'] = ($i === $indice);
        }
        unset($mon);

        $estado['aguardando_troca'] = false;
        $novo = $estado['monstros_jogador'][$indice];

        return [
            $this->evento('troca', [
                'lado'   => 'jogador',
                'indice' => $indice,
                'texto'  => "Vai, {$novo['nome']}!"
            ])
        ];
    }

    /**
     * Valida e aplica o efeito de um item do inventário em um pokémon do jogador.
     */
    private function executarUsoDeItem(array &$estado, string $chave, int $alvo): array
    {
        $equipe = &$estado['monstros_jogador'];
        if ($alvo < 0 || $alvo >= count($equipe)) {
            throw new RegraDeJogoException("Alvo do item inválido", 400);
        }

        if ($this->consumirItemCallback === null) {
            throw new RuntimeException("Callback de consumo de item não configurado");
        }

        $monstro = &$equipe[$alvo];
        $item    = ($this->consumirItemCallback)($chave, $monstro);

        if (!$item) {
            return [
                $this->evento('mensagem', ['texto' => 'Você não tem mais esse item!']),
                $this->evento('item_esgotado', ['chave' => $chave])
            ];
        }

        $eventos = [
            $this->evento('item_usado', ['chave' => $chave]),
            $this->evento('mensagem', ['texto' => "Você usou {$item['nome']} em {$monstro['nome']}!"])
        ];

        // Item de reviver: restaura metade do HP máximo e limpa condições
        if (!empty($item['reviver'])) {
            $monstro['vida_atual']              = max(1, (int) floor($monstro['hp_maximo'] / 2));
            $monstro['status_atual']            = null;
            $monstro['turnos_restantes_efeito'] = null;

            $eventos[] = $this->evento('cura', [
                'lado'    => 'jogador',
                'alvo'    => $alvo,
                'hp'      => $monstro['vida_atual'],
                'maxHp'   => $monstro['hp_maximo'],
                'status'  => null,
                'reviver' => true,
                'texto'   => "{$monstro['nome']} voltou à batalha!"
            ]);
            return $eventos;
        }

        // Cura de HP (-1 indica cura máxima)
        if (!empty($item['cura_hp'])) {
            $quantidade = (int) $item['cura_hp'] === -1 ? $monstro['hp_maximo'] : (int) $item['cura_hp'];
            $this->aplicarCura($monstro, $quantidade);
        }

        // Cura de status ('todos' ou lista separada por vírgula)
        if (!empty($item['cura_status']) && !empty($monstro['status_atual'])) {
            $curaTodos = $item['cura_status'] === 'todos';
            $lista     = $curaTodos ? [] : array_map('trim', explode(',', (string) $item['cura_status']));
            if ($curaTodos || in_array($monstro['status_atual'], $lista, true)) {
                $monstro['status_atual']            = null;
                $monstro['turnos_restantes_efeito'] = null;
            }
        }

        $eventos[] = $this->evento('cura', [
            'lado'    => 'jogador',
            'alvo'    => $alvo,
            'hp'      => $monstro['vida_atual'],
            'maxHp'   => $monstro['hp_maximo'],
            'status'  => $monstro['status_atual'],
            'reviver' => false,
            'texto'   => "{$monstro['nome']} está melhor!"
        ]);

        return $eventos;
    }

    // ===================== INTELIGÊNCIA ARTIFICIAL DO ROBÔ =====================

    /**
     * Escolhe qual golpe o robô vai usar:
     *   - 70% das vezes escolhe o golpe com maior pontuação estimada (poder * efetividade * STAB * precisão).
     *   - 30% das vezes escolhe um golpe aleatório para variar as jogadas.
     */
    private function iaEscolherGolpe(array $estado): int
    {
        $idxRobo    = $this->indiceAtivo($estado['monstros_robo']);
        $idxJogador = $this->indiceAtivo($estado['monstros_jogador']);

        $monRobo    = $estado['monstros_robo'][$idxRobo];
        $monJogador = $estado['monstros_jogador'][$idxJogador];
        $golpes     = $monRobo['golpes'] ?? [];

        if (count($golpes) <= 1) {
            return 0;
        }

        $melhorIndice = 0;
        $melhorNota   = -1.0;

        foreach ($golpes as $i => $g) {
            if (!empty($g['poder'])) {
                $ef       = $this->calcularMultiplicador($g['tipo'], $monJogador['tipos'] ?? []);
                $stab     = in_array($g['tipo'], $monRobo['tipos'] ?? [], true) ? ConfigBatalha::MULT_STAB : 1.0;
                $precisao = ($g['precisao'] ?? 100) / 100.0;
                $nota     = (float) $g['poder'] * $ef * $stab * $precisao;
            } else {
                // Golpes apenas de status só têm utilidade se o alvo ainda estiver sem status
                $nota = empty($monJogador['status_atual']) ? 30.0 : 0.0;
            }

            if ($nota > $melhorNota) {
                $melhorNota   = $nota;
                $melhorIndice = $i;
            }
        }

        if ($this->chance(ConfigBatalha::CHANCE_IA_MELHOR_GOLPE)) {
            return $melhorIndice;
        }

        return random_int(0, count($golpes) - 1);
    }

    // ===================== VALIDAÇÕES E UTILITÁRIOS =====================

    /**
     * Valida que o índice do golpe escolhido pertence ao pokémon ativo do jogador.
     */
    private function validarGolpeJogador(array $estado, int $indiceGolpe): void
    {
        $idx = $this->indiceAtivo($estado['monstros_jogador']);
        if ($idx === null) {
            throw new RegraDeJogoException("Nenhum pokémon ativo em campo", 409);
        }

        $mon = $estado['monstros_jogador'][$idx];
        if ($mon['vida_atual'] <= 0) {
            throw new RegraDeJogoException("O pokémon ativo está desmaiado", 409);
        }

        if ($indiceGolpe < 0 || $indiceGolpe >= count($mon['golpes'] ?? [])) {
            throw new RegraDeJogoException("Golpe inválido", 400);
        }
    }

    /** Retorna o índice do pokémon marcado como ativo, ou null se nenhum estiver ativo. */
    private function indiceAtivo(array $monstros): ?int
    {
        foreach ($monstros as $i => $mon) {
            if (!empty($mon['is_ativo'])) {
                return $i;
            }
        }
        return null;
    }

    /** Retorna o índice do primeiro pokémon ainda vivo (HP > 0), ou null se todos desmaiaram. */
    private function indiceProximoVivo(array $monstros): ?int
    {
        foreach ($monstros as $i => $mon) {
            if ((int) $mon['vida_atual'] > 0) {
                return $i;
            }
        }
        return null;
    }

    /** Velocidade efetiva considerando penalidade de 50% por paralisia. */
    private function velocidadeEfetiva(array $monstro): int
    {
        $vel = (int) $monstro['velocidade'];
        if (($monstro['status_atual'] ?? null) === 'paralysis') {
            return (int) floor($vel / 2);
        }
        return $vel;
    }

    /** Aplica dano limitado ao HP atual e devolve o dano real removido. */
    private function aplicarDano(array &$monstro, int $dano): int
    {
        $tirado = min(max(0, $dano), (int) $monstro['vida_atual']);
        $monstro['vida_atual'] -= $tirado;
        return $tirado;
    }

    /** Cura HP sem ultrapassar o máximo e devolve quanto foi recuperado. */
    private function aplicarCura(array &$monstro, int $quantidade): int
    {
        $antes = (int) $monstro['vida_atual'];
        $monstro['vida_atual'] = min((int) $monstro['hp_maximo'], $antes + max(0, $quantidade));
        return $monstro['vida_atual'] - $antes;
    }

    /** Conta quantos pokémons de um time ainda têm HP > 0. */
    public function contarVivos(array $monstros): int
    {
        $vivos = 0;
        foreach ($monstros as $mon) {
            if ((int) $mon['vida_atual'] > 0) {
                $vivos++;
            }
        }
        return $vivos;
    }

    /** Formata o nome exibido no log (o pokémon do robô recebe sufixo " inimigo"). */
    private function nomeLog(string $lado, array $monstro): string
    {
        $nome = $monstro['nome'] ?? 'Pokémon';
        return $lado === 'robo' ? "{$nome} inimigo" : $nome;
    }

    /** Retorna true com a probabilidade dada (0.0 a 1.0). */
    private function chance(float $probabilidade): bool
    {
        return (mt_rand(0, 10000) / 10000.0) < $probabilidade;
    }

    /** Cria um objeto de evento padronizado para o frontend reproduzir. */
    private function evento(string $tipo, array $dados = []): array
    {
        return ['tipo' => $tipo, 'dados' => $dados];
    }

    /**
     * Monta o resumo sincronizado do estado atual da batalha para envio ao frontend.
     *
     * @param array $estado Estado completo interno
     * @return array        Estado seguro e enxuto para o cliente
     */
    public function montarEstadoCliente(array $estado): array
    {
        $serializar = fn(array $lista): array => array_map(fn(array $m): array => [
            'id'       => $m['id_monstro'],
            'nome'     => $m['nome'],
            'nivel'    => $m['nivel'],
            'tipos'    => $m['tipos'],
            'hp'       => $m['vida_atual'],
            'maxHp'    => $m['hp_maximo'],
            'status'   => $m['status_atual'],
            'is_ativo' => $m['is_ativo']
        ], $lista);

        return [
            'turno'           => (int) $estado['turno_atual'],
            'cooldownTroca'   => (int) $estado['cooldown_troca_1'],
            'aguardandoTroca' => !empty($estado['aguardando_troca']),
            'fim'             => !empty($estado['fim']),
            'vencedor'        => $estado['vencedor'] ?? null,
            'jogador'         => $serializar($estado['monstros_jogador']),
            'robo'            => $serializar($estado['monstros_robo'])
        ];
    }
}
