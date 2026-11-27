<?php

/**
 * ConfigBatalha — Centraliza todas as configurações e constantes de batalha no servidor.
 *
 * Manter essas regras em um único arquivo facilita o ajuste do balanceamento
 * (nível, poder máximo do chefe, cooldown de troca, tempo de escolha, recompensas)
 * e permite enviar essas configurações diretamente para o JavaScript ao iniciar uma partida,
 * garantindo que cliente e servidor falem sempre a mesma língua.
 *
 * @author Miguel Granville / Vinicius Grub
 */
class ConfigBatalha
{
    // ===================== NÍVEIS E BALANCEAMENTO =====================

    /** @var int Nível padrão dos pokémons do jogador na batalha */
    public const NIVEL_JOGADOR = 10;

    /** @var int Nível padrão dos pokémons inimigos comuns (robô) */
    public const NIVEL_ROBO = 10;

    /** @var int Nível padrão do pokémon chefe */
    public const NIVEL_CHEFE = 10;

    /** @var int Poder máximo permitido para os golpes do chefe (evita derrotar o time em 2 golpes) */
    public const PODER_MAX_CHEFE = 45;

    /** @var int Poder mínimo dos golpes de dano selecionáveis na batalha */
    public const PODER_MIN_GOLPE = 40;

    // ===================== REGRAS DE TURNO E TEMPO =====================

    /** @var int Tempo em segundos que o jogador tem na tela para escolher sua jogada */
    public const TEMPO_ESCOLHA = 20;

    /** @var int Cooldown de troca (3 pois o turno atual já conta; restam 2 rounds bloqueados) */
    public const COOLDOWN_TROCA = 3;

    /** @var int Quantidade de batalhas normais concluídas necessárias antes de enfrentar o chefe */
    public const BATALHAS_PARA_CHEFE = 7;

    /** @var int Tempo mínimo em segundos para finalização externa (proteção anti-bot legado) */
    public const SEGUNDOS_MINIMOS = 10;

    // ===================== RECOMPENSAS =====================

    /** @var int Valor base em pokédollars por vitória (multiplicado conforme pokémons vivos) */
    public const RECOMPENSA_BASE_VITORIA = 50;

    /** @var int Valor em pokédollars concedido em caso de derrota (prêmio de consolação) */
    public const RECOMPENSA_DERROTA = 20;

    // ===================== PROBABILIDADES DE COMBATE =====================

    /** @var float Chance de acerto crítico (1 em 16, padrão clássico) */
    public const CHANCE_CRITICO = 1.0 / 16.0;

    /** @var float Multiplicador de dano em acerto crítico */
    public const MULT_CRITICO = 1.5;

    /** @var float Multiplicador STAB (golpe do mesmo tipo do atacante) */
    public const MULT_STAB = 1.5;

    /** @var float Chance de descongelar automaticamente no início do turno (20%) */
    public const CHANCE_DESCONGELAR = 0.20;

    /** @var float Chance de paralisia impedir o ataque no turno (25%) */
    public const CHANCE_PARALISIA = 0.25;

    /** @var int Divisor do HP máximo para dano contínuo de veneno/queimadura (1/8) */
    public const DIVISOR_DANO_DOT = 8;

    /** @var float Chance de o robô escolher o melhor golpe pela heurística (70%) */
    public const CHANCE_IA_MELHOR_GOLPE = 0.70;

    // ===================== STATUS E IMUNIDADES =====================

    /**
     * Status suportados no sistema de batalha e seus nomes no banco (tabela efeito).
     *
     * @var string[]
     */
    public const STATUS_VALIDOS = ['paralysis', 'burn', 'poison', 'sleep', 'freeze'];

    /**
     * Tipos de pokémon que são imunes a cada condição de status.
     * Ex.: pokémons de fogo ('fire') não podem sofrer queimadura ('burn').
     *
     * @var array<string, string[]>
     */
    public const IMUNE_STATUS = [
        'burn'      => ['fire'],
        'paralysis' => ['electric'],
        'poison'    => ['poison', 'steel'],
        'freeze'    => ['ice'],
        'sleep'     => []
    ];

    // ===================== GOLPES PROIBIDOS E ESPECIAIS =====================

    /**
     * Golpes da 1ª geração que exigem mecânicas não suportadas (2 turnos, recarga, auto-KO, etc.)
     * e por isso não devem ser sorteados para a batalha.
     *
     * @var string[]
     */
    public const GOLPES_PROIBIDOS = [
        'explosion', 'self-destruct', 'fly', 'dig', 'bounce', 'dive', 'solar-beam', 'solar-blade',
        'sky-attack', 'skull-bash', 'razor-wind', 'hyper-beam', 'giga-impact', 'focus-punch',
        'last-resort', 'dream-eater', 'counter', 'mirror-coat', 'bide', 'fling', 'struggle',
        'sucker-punch', 'future-sight', 'doom-desire', 'bind', 'wrap', 'fire-spin', 'clamp',
        'whirlpool', 'sand-tomb', 'magma-storm', 'thrash', 'outrage', 'petal-dance', 'uproar'
    ];

    /**
     * Golpes com prioridade diferente de 0 na 1ª geração.
     *
     * @var array<string, int>
     */
    public const PRIORIDADE_GOLPES = [
        'quick-attack' => 1
    ];

    /**
     * Percentual de drenagem (positivo = cura ao atacante, negativo = dano de recuo).
     *
     * @var array<string, int>
     */
    public const DRENAGEM_GOLPES = [
        'absorb'      => 50,
        'mega-drain'  => 50,
        'leech-life'  => 50,
        'take-down'   => -25,
        'double-edge' => -33,
        'submission'  => -25
    ];

    // ===================== FÓRMULAS DE ATRIBUTOS =====================

    /**
     * Calcula o HP máximo de um pokémon no nível informado.
     * Usa a mesma fórmula clássica exibida no jogo.
     *
     * @param int $vidaBase Atributo base de HP vindo da tabela monstro
     * @param int $nivel    Nível do pokémon na batalha
     * @return int          HP máximo calculado
     */
    public static function calcularHpMaximo(int $vidaBase, int $nivel = self::NIVEL_JOGADOR): int
    {
        return (int) floor((2 * $vidaBase * $nivel) / 100) + $nivel + 10;
    }

    /**
     * Calcula um atributo de combate (Ataque, Defesa, Atq. Esp., Def. Esp. ou Velocidade).
     *
     * @param int $valorBase Atributo base vindo da tabela monstro
     * @param int $nivel     Nível do pokémon na batalha
     * @return int           Atributo efetivo no nível informado
     */
    public static function calcularAtributo(int $valorBase, int $nivel = self::NIVEL_JOGADOR): int
    {
        return (int) floor((2 * $valorBase * $nivel) / 100) + 5;
    }

    /**
     * Formata nomes técnicos (ex.: "vine-whip" -> "Vine Whip") para exibição ao jogador.
     *
     * @param string $nomeTecnico Nome em kebab-case salvo no banco
     * @return string             Nome formatado com iniciais maiúsculas
     */
    public static function formatarNome(string $nomeTecnico): string
    {
        return ucwords(str_replace('-', ' ', trim($nomeTecnico)));
    }

    /**
     * Retorna as configurações públicas da batalha para envio ao frontend.
     * Assim o JavaScript usa exatamente os mesmos limites e regras do servidor.
     *
     * @return array Configurações serializáveis em JSON
     */
    public static function paraCliente(): array
    {
        return [
            'nivelJogador'       => self::NIVEL_JOGADOR,
            'nivelRobo'          => self::NIVEL_ROBO,
            'nivelChefe'         => self::NIVEL_CHEFE,
            'poderMaxChefe'      => self::PODER_MAX_CHEFE,
            'tempoEscolha'       => self::TEMPO_ESCOLHA,
            'cooldownTroca'      => self::COOLDOWN_TROCA,
            'batalhasParaChefe'  => self::BATALHAS_PARA_CHEFE,
            'recompensaDerrota'  => self::RECOMPENSA_DERROTA,
            'imuneStatus'        => self::IMUNE_STATUS
        ];
    }
}
