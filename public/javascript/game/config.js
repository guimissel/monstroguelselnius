// Configurações, elementos do DOM e estado inicial do jogo.
// As constantes padrão abaixo são atualizadas automaticamente pelas configurações
// enviadas pelo servidor (ConfigBatalha) ao iniciar cada batalha.
// ===================== configuracoes =====================

const API = "https://pokeapi.co/api/v2";
let NIVEL_JOGADOR = 10;        // nível usado para calcular os atributos do jogador
let NIVEL_ROBO = 10;           // nível base do oponente (o chefe também envia o próprio nível)
let PODER_MAX_CHEFE = 45;      // limite de poder dos golpes do chefe
let TEMPO_ESCOLHA = 20;        // segundos para o jogador escolher a jogada
let COOLDOWN_TROCA = 3;        // 3 pois o turno da troca já conta (sobram 2 rounds bloqueados)
const MAX_POKEMON_ID = 151;    // 1ª geração
const URL_HOME = "/monstroguelselnius/home";
const URL_LOGIN = "/monstroguelselnius/login";
const URL_LOJA = "/monstroguelselnius/loja";
const URL_EQUIPE = "/monstroguelselnius/equipe";

// Objeto centralizado com as configurações ativas da batalha atual.
// É preenchido com as regras vindas do backend (/api/batalha/iniciar).
const CONFIG_BATALHA = {
    nivelJogador: NIVEL_JOGADOR,
    nivelRobo: NIVEL_ROBO,
    nivelChefe: NIVEL_ROBO,
    poderMaxChefe: PODER_MAX_CHEFE,
    tempoEscolha: TEMPO_ESCOLHA,
    cooldownTroca: COOLDOWN_TROCA,
    batalhasParaChefe: 7,
    recompensaDerrota: 20,
    imuneStatus: {
        burn: ["fire"],
        paralysis: ["electric"],
        poison: ["poison", "steel"],
        freeze: ["ice"],
        sleep: []
    }
};

/**
 * Aplica as configurações recebidas do servidor (ConfigBatalha) no frontend,
 * mantendo temporizador, cooldown de troca e níveis sincronizados com o backend.
 *
 * @param {Object|null} cfg Configurações retornadas por /api/batalha/iniciar
 */
function aplicarConfiguracoesBatalha(cfg) {
    if (!cfg || typeof cfg !== "object") return;

    if (typeof cfg.nivelJogador === "number") {
        CONFIG_BATALHA.nivelJogador = cfg.nivelJogador;
        NIVEL_JOGADOR = cfg.nivelJogador;
    }
    if (typeof cfg.nivelRobo === "number") {
        CONFIG_BATALHA.nivelRobo = cfg.nivelRobo;
        NIVEL_ROBO = cfg.nivelRobo;
    }
    if (typeof cfg.nivelChefe === "number") {
        CONFIG_BATALHA.nivelChefe = cfg.nivelChefe;
    }
    if (typeof cfg.poderMaxChefe === "number") {
        CONFIG_BATALHA.poderMaxChefe = cfg.poderMaxChefe;
        PODER_MAX_CHEFE = cfg.poderMaxChefe;
    }
    if (typeof cfg.tempoEscolha === "number") {
        CONFIG_BATALHA.tempoEscolha = cfg.tempoEscolha;
        TEMPO_ESCOLHA = cfg.tempoEscolha;
    }
    if (typeof cfg.cooldownTroca === "number") {
        CONFIG_BATALHA.cooldownTroca = cfg.cooldownTroca;
        COOLDOWN_TROCA = cfg.cooldownTroca;
    }
    if (typeof cfg.batalhasParaChefe === "number") {
        CONFIG_BATALHA.batalhasParaChefe = cfg.batalhasParaChefe;
    }
    if (typeof cfg.recompensaDerrota === "number") {
        CONFIG_BATALHA.recompensaDerrota = cfg.recompensaDerrota;
    }
    if (cfg.imuneStatus && typeof cfg.imuneStatus === "object") {
        CONFIG_BATALHA.imuneStatus = cfg.imuneStatus;
    }
}

const NOMES_TIPOS = {
    normal: "Normal", fire: "Fogo", water: "Água", electric: "Elétrico",
    grass: "Planta", ice: "Gelo", fighting: "Lutador", poison: "Veneno",
    ground: "Terra", flying: "Voador", psychic: "Psíquico", bug: "Inseto",
    rock: "Pedra", ghost: "Fantasma", dragon: "Dragão", dark: "Sombrio",
    steel: "Aço", fairy: "Fada"
};

const STATUS = {
    paralysis: { sigla: "PAR", frase: "ficou paralisado!" },
    burn:      { sigla: "QUE", frase: "foi queimado!" },
    poison:    { sigla: "ENV", frase: "foi envenenado!" },
    sleep:     { sigla: "SON", frase: "caiu no sono!" },
    freeze:    { sigla: "CON", frase: "foi congelado!" }
};

// Tipos imunes a cada condição de status (mantido para referência visual)
const IMUNE_STATUS = {
    burn: ["fire"], paralysis: ["electric"], poison: ["poison", "steel"],
    freeze: ["ice"], sleep: []
};

// Itens do jogador: preenchido por carregarInventario() (api/inventario)
// chave -> { nome, img, cura ("max" ou numero), cura_status ("todos" ou lista), reviver }
let ITENS = {};

// Golpes que não funcionam no sistema simples (mantido como referência)
const GOLPES_PROIBIDOS = new Set([
    "explosion", "self-destruct", "fly", "dig", "bounce", "dive", "solar-beam", "solar-blade",
    "sky-attack", "skull-bash", "razor-wind", "hyper-beam", "giga-impact", "focus-punch",
    "last-resort", "dream-eater", "counter", "mirror-coat", "bide", "fling", "struggle",
    "sucker-punch", "future-sight", "doom-desire", "bind", "wrap", "fire-spin", "clamp",
    "whirlpool", "sand-tomb", "magma-storm", "thrash", "outrage", "petal-dance", "uproar"
]);

const CATEGORIAS_DANO = ["damage", "damage+ailment", "damage+lower", "damage+raise", "damage+heal"];

const GOLPE_PADRAO = {
    nome: "Tackle", tipo: "normal", poder: 40, precisao: 100,
    classe: "physical", prioridade: 0, status: null, chanceStatus: 0, drenagem: 0
};

// ===================== elementos =====================

const palco = document.getElementById("palco");
const logTexto = document.getElementById("logTexto");
const tempoEl = document.getElementById("tempo");
const voltarEl = document.getElementById("voltar");
const menuEl = document.getElementById("menu");
const painelEl = document.getElementById("painel");
const painelTitulo = document.getElementById("painelTitulo");
const painelAviso = document.getElementById("painelAviso");
const painelLista = document.getElementById("painelLista");

const LADOS = {
    jogador: { sprite: document.getElementById("spriteJogador"), sombra: document.getElementById("sombraJogador"), info: document.getElementById("infoJogador") },
    robo:    { sprite: document.getElementById("spriteRobo"),    sombra: document.getElementById("sombraRobo"),    info: document.getElementById("infoRobo") }
};

// ===================== estado =====================

let estado;
