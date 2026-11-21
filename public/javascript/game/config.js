// Configuracoes, elementos do DOM e estado inicial do jogo.
// ===================== configuracoes =====================

const API = "https://pokeapi.co/api/v2";
const NIVEL_JOGADOR = 10;      // nivel baixo: os pokemons ficam fracos e a batalha mais equilibrada
const NIVEL_ROBO = 10;         // (o chefe vem do servidor com o seu proprio nivel)
const PODER_MAX_CHEFE = 45;    // o chefe so usa golpes ate esse poder (senao ele derruba o time em 2 ou 3 golpes)
const TEMPO_ESCOLHA = 20;      // segundos para escolher a jogada
const COOLDOWN_TROCA = 3;      // 3 porque o round da troca tambem conta (sobram 2 rounds bloqueados)
const MAX_POKEMON_ID = 151;    // sorteia pokemons da 1a geracao
const URL_HOME = "/monstroguelselnius/home";
const URL_LOGIN = "/monstroguelselnius/login";
const URL_LOJA = "/monstroguelselnius/loja";
const URL_EQUIPE = "/monstroguelselnius/equipe";

// o seu time vem do banco (tela /equipe). o time do robo e sorteado pelo servidor entre os pokemons basicos
// (se voce vencer, os pokemons dele vao para a sua colecao)

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

// tipos que nao podem receber cada status
const IMUNE_STATUS = {
    burn: ["fire"], paralysis: ["electric"], poison: ["poison", "steel"],
    freeze: ["ice"], sleep: []
};

// itens do jogador: vem do banco (api/inventario) quando o jogo carrega
// chave -> { nome, img, cura ("max" ou numero), cura_status ("todos" ou lista), reviver }
let ITENS = {};

// golpes que nao funcionam num sistema simples (2 turnos, recarga, auto-ko...)
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
