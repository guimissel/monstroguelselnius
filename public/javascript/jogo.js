// ===================== configuracoes =====================

const API = "https://pokeapi.co/api/v2";
const NIVEL = 50;
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

// ===================== classes =====================

// efetividade entre os tipos (a tabela vem da PokeAPI)
class TabelaTipos {
    static dados = {};

    static async carregar() {
        await Promise.all(Object.keys(NOMES_TIPOS).map(async (tipo) => {
            const resposta = await buscar(`${API}/type/${tipo}`);
            const rel = resposta.damage_relations;
            TabelaTipos.dados[tipo] = {
                forte: rel.double_damage_to.map((t) => t.name),
                fraco: rel.half_damage_to.map((t) => t.name),
                imune: rel.no_damage_to.map((t) => t.name)
            };
        }));
    }

    // 2x se for forte, 0.5x se for fraco. com 2 tipos multiplica (4x, 0.25x)
    static multiplicador(tipoAtaque, tiposDefensor) {
        const rel = TabelaTipos.dados[tipoAtaque];
        let mult = 1;
        for (const t of tiposDefensor) {
            if (rel.imune.includes(t)) return 0;
            if (rel.forte.includes(t)) mult *= 2;
            else if (rel.fraco.includes(t)) mult *= 0.5;
        }
        return mult;
    }
}

// um pokemon em batalha: guarda os atributos e sabe fazer as contas dele
class Pokemon {
    constructor(dados) {
        Object.assign(this, dados);
    }

    estaDesmaiado() {
        return this.hp <= 0;
    }

    // devolve o dano que realmente foi tirado
    receberDano(dano) {
        const antes = this.hp;
        this.hp = Math.max(0, this.hp - dano);
        return antes - this.hp;
    }

    curar(quantidade) {
        this.hp = Math.min(this.maxHp, this.hp + quantidade);
    }

    limparStatus() {
        this.status = null;
        this.sono = 0;
    }

    // volta de um desmaio com metade do HP
    reviver() {
        this.hp = Math.floor(this.maxHp / 2);
        this.limparStatus();
    }

    get velocidadeEfetiva() {
        return this.status === "paralysis" ? this.spe / 2 : this.spe;
    }

    multiplicadorContra(tipoGolpe) {
        return TabelaTipos.multiplicador(tipoGolpe, this.tipos);
    }

    // dano que este pokemon causa em "alvo" com o golpe
    calcularDanoContra(alvo, golpe) {
        const fisico = golpe.classe === "physical";
        let ataque = fisico ? this.atk : this.spa;
        const defesa = fisico ? alvo.def : alvo.spd;
        if (fisico && this.status === "burn") ataque /= 2;

        const stab = this.tipos.includes(golpe.tipo) ? 1.5 : 1;
        const ef = alvo.multiplicadorContra(golpe.tipo);
        const critico = Math.random() < 1 / 16;
        const aleatorio = 0.85 + Math.random() * 0.15;

        const base = Math.floor((Math.floor((2 * NIVEL) / 5) + 2) * golpe.poder * ataque / defesa / 50) + 2;
        const total = Math.floor(base * stab * ef * (critico ? 1.5 : 1) * aleatorio);

        return { dano: ef === 0 ? 0 : Math.max(1, total), ef, critico };
    }
}

// os 3 pokemons de um treinador e qual deles esta em campo
class Time {
    #pokemons;

    constructor(pokemons = []) {
        this.#pokemons = pokemons;
        this.indiceAtivo = 0;
    }

    get pokemons() { return this.#pokemons; }
    get ativo() { return this.#pokemons[this.indiceAtivo]; }

    vivos() { return this.#pokemons.filter((p) => !p.estaDesmaiado()); }
    proximoVivo() { return this.#pokemons.findIndex((p) => !p.estaDesmaiado()); }
}

// quem batalha. Classe abstrata: o Jogador pergunta na tela, o Robo decide sozinho (polimorfismo)
class Treinador {
    constructor(lado) {
        if (new.target === Treinador) throw new Error("Treinador e abstrata");
        this.lado = lado;
        this.equipe = new Time();   // composicao: o time e deste treinador
    }

    // devolve { tipo: "golpe", indice } (o jogador ainda pode devolver "trocar", "item" ou "fugir")
    escolherAcao(batalha) { throw new Error("escolherAcao nao foi implementado"); }

    // indice do pokemon que entra quando o da vez desmaia
    escolherSubstituto() { throw new Error("escolherSubstituto nao foi implementado"); }

    mensagemEntrada(pokemon) { throw new Error("mensagemEntrada nao foi implementada"); }
}

class Jogador extends Treinador {
    constructor() { super("jogador"); }

    escolherAcao() { return perguntarAcao(); }
    escolherSubstituto() { return perguntarTrocaForcada(); }
    mensagemEntrada(pokemon) { return `Vai, ${pokemon.nome}!`; }
}

class Robo extends Treinador {
    constructor() { super("robo"); }

    // 70% das vezes escolhe o golpe que causa mais dano, senao um aleatorio
    escolherAcao(batalha) {
        const atq = this.equipe.ativo;
        const def = batalha.jogador.equipe.ativo;

        const pontos = (g) => g.poder
            ? g.poder * def.multiplicadorContra(g.tipo) * (atq.tipos.includes(g.tipo) ? 1.5 : 1) * ((g.precisao || 100) / 100)
            : (def.status ? 0 : 30);

        const golpe = Math.random() < 0.7
            ? [...atq.golpes].sort((a, b) => pontos(b) - pontos(a))[0]
            : atq.golpes[Math.floor(Math.random() * atq.golpes.length)];

        return { tipo: "golpe", indice: atq.golpes.indexOf(golpe) };
    }

    escolherSubstituto() { return this.equipe.proximoVivo(); }
    mensagemEntrada(pokemon) { return `O robô enviou ${pokemon.nome}!`; }
}

// a batalha inteira: os dois treinadores e o andamento
class Batalha {
    constructor() {
        this.jogador = new Jogador();
        this.robo = new Robo();
        this.rodada = 1;
        this.cooldownTroca = 0;
        this.itens = {};           // chave -> quantidade (preenchido por carregarInventario)
        this.fim = false;
        this.idBatalha = null;     // batalha aberta no servidor (para receber a recompensa)
        this.timer = null;
    }
}

const ativo = (lado) => estado[lado].equipe.ativo;
const outro = (lado) => (lado === "jogador" ? "robo" : "jogador");

// ===================== utilitarios =====================

const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

function embaralhar(lista) {
    for (let i = lista.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [lista[i], lista[j]] = [lista[j], lista[i]];
    }
    return lista;
}

function formatarNome(nome) {
    return nome.split("-").map((p) => p.charAt(0).toUpperCase() + p.slice(1)).join(" ");
}

// nome que aparece no log (o inimigo ganha o sufixo)
function nomeLog(lado, mon) {
    return lado === "robo" ? `${mon.nome} inimigo` : mon.nome;
}

const caminhoImg = (arquivo) => "public/img/" + encodeURI(arquivo);

// ===================== inventario (banco de dados) =====================

async function carregarInventario() {
    const resposta = await fetch("/monstroguelselnius/api/inventario");

    // sessao expirou: volta para o login
    if (resposta.status === 401) {
        location.href = URL_LOGIN;
        throw new Error("sessao expirada");
    }
    if (!resposta.ok) throw new Error("Erro ao carregar o inventario");

    const dados = await resposta.json();
    ITENS = {};
    estado.itens = {};

    for (const item of dados.itens) {
        // a Pedra Evolutiva e para a tela da equipe, nao para a batalha
        if (item.categoria === "evolucao") continue;

        ITENS[item.chave] = {
            nome: item.nome,
            img: item.imagem,
            cura: item.efeito.curaHp === -1 ? "max" : item.efeito.curaHp,   // 0 = nao cura
            cura_status: item.efeito.curaStatus,
            reviver: item.efeito.reviver
        };
        estado.itens[item.chave] = item.quantidade;
    }
}

// gasta 1 unidade no banco. false = nao tem mais o item
async function gastarItemNoBanco(chave) {
    try {
        const resposta = await fetch(`/monstroguelselnius/api/inventario/${encodeURIComponent(chave)}/usar`, { method: "POST" });
        return resposta.ok;
    } catch (erro) {
        return false;
    }
}

// ===================== equipe (banco de dados) =====================

// ids da equipe salva do jogador + ids dos pokemons basicos (so usados se o servidor nao sortear o robo)
async function carregarEquipe() {
    const resposta = await fetch("/monstroguelselnius/api/equipe");

    // sessao expirou: volta para o login
    if (resposta.status === 401) {
        location.href = URL_LOGIN;
        throw new Error("sessao expirada");
    }
    if (!resposta.ok) throw new Error("Erro ao carregar a equipe");

    const dados = await resposta.json();
    return {
        idsJogador: dados.equipe.map((p) => p.id),
        idsBasicos: dados.basicos.map((p) => p.id)
    };
}

// ===================== batalha (recompensa no servidor) =====================

// mostra o erro tecnico numa faixa vermelha no topo (da para copiar o texto ou tirar print)
function mostrarErroTecnico(texto) {
    console.error(texto);

    const caixa = document.createElement("pre");
    caixa.textContent = "Detalhe do erro (mande este texto para quem esta ajudando):\n" + texto;
    caixa.style.cssText = "position:fixed;left:0;right:0;top:0;z-index:9999;margin:0;padding:10px 44px 10px 12px;" +
        "max-height:45vh;overflow:auto;white-space:pre-wrap;word-break:break-word;font:13px/1.4 monospace;" +
        "color:#fff;background:#a02010;user-select:text";

    const fechar = document.createElement("button");
    fechar.textContent = "X";
    fechar.style.cssText = "position:fixed;top:6px;right:8px;z-index:10000;cursor:pointer";
    fechar.addEventListener("click", () => { caixa.remove(); fechar.remove(); });

    document.body.append(caixa, fechar);
}

// abre a batalha no servidor: devolve o id e o time do robo ({ id, robo: [ids] }).
// sem isso o jogo funciona, mas nao paga pokedolares nem entrega pokemons
async function abrirBatalhaNoServidor() {
    try {
        const resposta = await fetch("/monstroguelselnius/api/batalha/iniciar", { method: "POST" });

        // sessao expirou: volta para o login
        if (resposta.status === 401) {
            location.href = URL_LOGIN;
            return { sair: true };
        }

        // le como texto primeiro: se o php soltar algum aviso antes do json, da para mostrar
        const texto = await resposta.text();
        let corpo;
        try {
            corpo = JSON.parse(texto);
        } catch (erro) {
            mostrarErroTecnico(`Abrir a batalha (HTTP ${resposta.status}). O servidor nao respondeu JSON:\n${texto.slice(0, 600)}`);
            return null;
        }

        // 409: o jogador ainda nao escolheu a equipe
        if (resposta.status === 409) return { semEquipe: true };

        if (!resposta.ok) {
            mostrarErroTecnico(`Abrir a batalha (HTTP ${resposta.status}): ${corpo.mensagem}\nMotivo: ${corpo.detalhe || "sem detalhe"}`);
            return null;
        }

        if (!corpo.id || !Array.isArray(corpo.robo) || corpo.robo.length !== 3) {
            mostrarErroTecnico("Abrir a batalha: resposta inesperada do servidor:\n" + texto.slice(0, 600));
            return null;
        }
        return { id: corpo.id, robo: corpo.robo };
    } catch (erro) {
        mostrarErroTecnico("Abrir a batalha: " + erro);
        return null;
    }
}

// avisa como a batalha terminou. quem calcula o dinheiro e o servidor
async function fecharBatalhaNoServidor(resultado, vivos) {
    if (!estado.idBatalha) return { erro: "Não consegui registrar a batalha, então não há recompensa." };

    try {
        const dados = new FormData();
        dados.append("resultado", resultado);   // vitoria, derrota ou fuga
        dados.append("vivos", vivos);

        const resposta = await fetch(`/monstroguelselnius/api/batalha/${estado.idBatalha}/finalizar`, {
            method: "POST",
            body: dados
        });
        const corpo = await resposta.json();

        if (!resposta.ok) {
            if (corpo.detalhe) mostrarErroTecnico(`Finalizar a batalha (HTTP ${resposta.status}): ${corpo.mensagem}\nMotivo: ${corpo.detalhe}`);
            return { erro: corpo.mensagem || "Não consegui registrar a recompensa." };
        }
        return corpo;
    } catch (erro) {
        console.error(erro);
        return { erro: "Não consegui registrar a recompensa." };
    }
}

// ===================== pokeapi =====================

const cacheApi = new Map();

function buscar(url) {
    if (!cacheApi.has(url)) {
        const promessa = fetch(url).then((r) => {
            if (!r.ok) throw new Error(`Erro ${r.status} em ${url}`);
            return r.json();
        });
        // se falhar, deixa tentar de novo depois
        promessa.catch(() => cacheApi.delete(url));
        cacheApi.set(url, promessa);
    }
    return cacheApi.get(url);
}

// tabela de tipos: quem e forte, fraco e imune contra quem


function normalizarGolpe(m) {
    const categoria = m.meta?.category?.name;
    const ailment = m.meta?.ailment?.name;
    const causaStatus = STATUS[ailment] ? ailment : null;
    const causaDano = m.damage_class.name !== "status";

    if (causaDano) {
        if (!m.power || m.power < 40 || !CATEGORIAS_DANO.includes(categoria)) return null;
    } else if (categoria !== "ailment" || !causaStatus) {
        return null;
    }

    return {
        nome: formatarNome(m.name),
        tipo: m.type.name,
        poder: causaDano ? m.power : 0,
        precisao: m.accuracy,                       // null = nunca erra
        classe: m.damage_class.name,                // physical, special ou status
        prioridade: m.priority,
        status: causaStatus,
        chanceStatus: causaDano ? (m.meta.ailment_chance || 10) : 100,
        drenagem: m.meta?.drain || 0                // positivo cura, negativo e recuo
    };
}

async function escolherGolpes(dados, tipos) {
    const candidatos = dados.moves
        .filter((m) => !GOLPES_PROIBIDOS.has(m.move.name) && m.version_group_details.some(
            (v) => v.move_learn_method.name === "level-up" && v.level_learned_at <= NIVEL))
        .map((m) => m.move.url);

    embaralhar(candidatos);

    const respostas = await Promise.all(
        candidatos.slice(0, 16).map((url) => buscar(url).catch(() => null))
    );
    const validos = respostas.filter(Boolean).map(normalizarGolpe).filter(Boolean);

    const pontos = (g) => g.poder * ((g.precisao || 100) / 100) * (tipos.includes(g.tipo) ? 1.5 : 1);
    const danos = validos.filter((g) => g.poder).sort((a, b) => pontos(b) - pontos(a));
    const resto = embaralhar([...danos.slice(3), ...validos.filter((g) => !g.poder)]);

    // 3 melhores de dano + 1 variado (pode ser de status)
    const escolhidos = [...danos.slice(0, 3), ...resto].slice(0, 4);

    // garante pelo menos um golpe de dano (e completa com Tackle se faltar golpe)
    const semDano = !escolhidos.some((g) => g.poder);
    if (escolhidos.length < 4 || semDano) {
        if (escolhidos.length >= 4) escolhidos.pop();
        if (!escolhidos.some((g) => g.nome === GOLPE_PADRAO.nome)) escolhidos.push(GOLPE_PADRAO);
    }
    return escolhidos;
}

// imagens dos pokemons: arquivos do projeto, com o caminho vindo do banco (api/pokemon)
let IMAGENS = {};

async function carregarImagens() {
    try {
        const resposta = await fetch("/monstroguelselnius/api/pokemon");
        if (!resposta.ok) return;
        const dados = await resposta.json();
        IMAGENS = {};
        for (const p of dados.pokemons) IMAGENS[p.id] = p.imagens || {};
    } catch (erro) {
        console.error(erro);   // sem o catalogo o jogo usa as imagens da PokeAPI
    }
}

async function criarPokemon(id) {
    const dados = await buscar(`${API}/pokemon/${id}`);
    const local = IMAGENS[id] || {};

    const base = {};
    dados.stats.forEach((s) => (base[s.stat.name] = s.base_stat));

    const animado = dados.sprites.versions?.["generation-v"]?.["black-white"]?.animated;

    const stat = (b) => Math.floor((2 * b * NIVEL) / 100) + 5;
    const tipos = dados.types.map((t) => t.type.name);
    const maxHp = Math.floor((2 * base.hp * NIVEL) / 100) + NIVEL + 10;

    return new Pokemon({
        id,
        nome: formatarNome(dados.name),
        tipos,
        maxHp,
        hp: maxHp,
        atk: stat(base.attack),
        def: stat(base.defense),
        spa: stat(base["special-attack"]),
        spd: stat(base["special-defense"]),
        spe: stat(base.speed),
        golpes: await escolherGolpes(dados, tipos),
        status: null,
        sono: 0,
        // arquivo local (banco) primeiro; PokeAPI so se o pokemon nao tiver imagem guardada
        frente: local.frente || dados.sprites.front_default,
        costas: local.costas || dados.sprites.back_default || dados.sprites.front_default,
        // sprites animados (GIF). Nem todo pokemon tem; sem eles vale a imagem parada
        frenteAnim: local.frente_animada || animado?.front_default || null,
        costasAnim: local.costas_animada || animado?.back_default || null
    });
}

// sorteia ids sem repetir. "lista" e de onde sortear (sem ela, vale a 1a geracao inteira)
function sortearIds(qtd, proibidos = [], lista = null) {
    const candidatos = lista || Array.from({ length: MAX_POKEMON_ID }, (_, i) => i + 1);
    return embaralhar(candidatos.filter((id) => !proibidos.includes(id))).slice(0, qtd);
}

function precarregarImagem(url) {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = img.onerror = () => resolve();
        img.src = url;
    });
}

// ===================== log =====================

let pularTexto = null;

logTexto.addEventListener("click", () => pularTexto && pularTexto());

// escreve no log letra por letra. rapido = aparece na hora e nao espera
// musica de fundo da batalha: fica em loop enquanto a tela estiver aberta
// (o navegador so deixa tocar som depois que a pessoa clicou em algo no site; se bloquear, tenta de novo no primeiro clique)
const musicaFundo = new Audio("public/audio/fundo.mp3");
musicaFundo.loop = true;
musicaFundo.preload = "auto";
musicaFundo.volume = 0.4;
let musicaLigada = false;       // a batalha pediu musica
let esperandoInteracao = false;

function tocarMusica() {
    const tocando = musicaFundo.play();
    if (!tocando) return;
    tocando.catch(() => {
        if (esperandoInteracao) return;
        esperandoInteracao = true;
        const tentar = () => {
            esperandoInteracao = false;
            document.removeEventListener("pointerdown", tentar);
            document.removeEventListener("keydown", tentar);
            if (musicaLigada && !document.hidden) musicaFundo.play().catch(() => {});
        };
        document.addEventListener("pointerdown", tentar);
        document.addEventListener("keydown", tentar);
    });
}

function iniciarMusica() {
    musicaLigada = true;
    if (musicaFundo.paused) tocarMusica();   // na revanche ela ja esta tocando e continua de onde estava
}

// aba escondida: pausa a musica; ao voltar, continua
document.addEventListener("visibilitychange", () => {
    if (document.hidden) musicaFundo.pause();
    else if (musicaLigada) tocarMusica();
});

// som de texto sendo escrito: toca toda vez que algo aparece na caixa de log
// (o navegador so deixa tocar som depois que a pessoa clicou em algo no site; se bloquear, o jogo segue sem som)
const somLog = new Audio("public/audio/log.mp3");
somLog.preload = "auto";
somLog.volume = 0.6;
let timerSomLog = null;

function pararSomLog() {
    clearTimeout(timerSomLog);
    somLog.pause();
}

// duracaoMax (ms): para mensagens que aparecem de uma vez, o som toca so um pouquinho
function tocarSomLog(duracaoMax = 0) {
    try {
        pararSomLog();
        somLog.currentTime = 0;
        const tocando = somLog.play();
        if (tocando) tocando.catch(() => {});
        if (duracaoMax > 0) timerSomLog = setTimeout(pararSomLog, duracaoMax);
    } catch (erro) {
        // sem som: segue o jogo
    }
}

function dizer(texto, { rapido = false, pausa = 650 } = {}) {
    const p = document.createElement("p");
    logTexto.appendChild(p);
    while (logTexto.children.length > 80) logTexto.firstChild.remove();

    if (rapido) {
        p.textContent = texto;
        logTexto.scrollTop = logTexto.scrollHeight;
        tocarSomLog(350);
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        let i = 0;
        tocarSomLog();
        const terminar = () => {
            clearInterval(intervalo);
            pararSomLog();
            p.textContent = texto;
            logTexto.scrollTop = logTexto.scrollHeight;
            pularTexto = null;
            setTimeout(resolve, pausa);
        };
        const intervalo = setInterval(() => {
            i++;
            p.textContent = texto.slice(0, i);
            logTexto.scrollTop = logTexto.scrollHeight;
            if (i >= texto.length) terminar();
        }, 22);
        pularTexto = () => { pularTexto = null; clearInterval(intervalo); pararSomLog(); p.textContent = texto; setTimeout(resolve, 250); };
    });
}

// ===================== tela =====================

function atualizarInfo(lado) {
    const mon = ativo(lado);
    const caixa = LADOS[lado].info;

    caixa.querySelector(".nome").textContent = mon.nome;
    caixa.querySelector(".nivel").textContent = "Nv" + NIVEL;

    const barra = caixa.querySelector(".barra");
    const pct = (mon.hp / mon.maxHp) * 100;
    barra.style.width = pct + "%";
    barra.classList.toggle("amarelo", pct <= 50 && pct > 20);
    barra.classList.toggle("vermelho", pct <= 20);

    const hpTexto = caixa.querySelector(".hpTexto");
    if (hpTexto) hpTexto.textContent = `${mon.hp}/${mon.maxHp}`;

    const status = caixa.querySelector(".status");
    status.className = "status" + (mon.status ? " " + mon.status : "");
    status.textContent = mon.status ? STATUS[mon.status].sigla : "";
}

// posicao e largura de cada sprite parado (as mesmas do jogo.css): left/top em %, largura em cqw
const SPRITE_BASE = {
    jogador: { left: 15, top: 37, largura: 25 },
    robo:    { left: 56, top: 26, largura: 17 }
};

// O GIF animado vem cortado rente ao desenho (a imagem parada tem 96x96 com sobra em volta).
// A escala dos dois e a mesma, entao a largura segue o tamanho do GIF, centralizado e com o pe no mesmo lugar.
function ajustarSpriteAnimado(sprite, lado) {
    const base = SPRITE_BASE[lado];
    const porPixel = base.largura / 96;
    const largura = sprite.naturalWidth * porPixel;
    const altura = sprite.naturalHeight * porPixel;

    sprite.style.width = `${largura}cqw`;
    sprite.style.left = `calc(${base.left}% + ${(base.largura - largura) / 2}cqw)`;
    sprite.style.top = `calc(${base.top}% + ${base.largura * 0.7 - altura}cqw)`;
}

function limparAjusteSprite(sprite) {
    sprite.style.width = "";
    sprite.style.left = "";
    sprite.style.top = "";
}

// usa o GIF animado; se ele nao existir ou nao carregar, cai na imagem parada
function definirSprite(sprite, lado, mon) {
    const animado = lado === "jogador" ? mon.costasAnim : mon.frenteAnim;
    const parado = lado === "jogador" ? mon.costas : mon.frente;

    limparAjusteSprite(sprite);
    sprite.onload = null;
    sprite.onerror = null;

    if (!animado) {
        sprite.src = parado;
        return;
    }

    sprite.onload = () => {
        sprite.onload = null;
        ajustarSpriteAnimado(sprite, lado);
    };
    sprite.onerror = () => {
        sprite.onload = null;
        sprite.onerror = null;
        limparAjusteSprite(sprite);
        sprite.src = parado;
    };
    sprite.src = animado;

    // imagem ja carregada antes (mesmo GIF de novo): o onload pode nao disparar
    if (sprite.complete && sprite.naturalWidth > 0) {
        sprite.onload = null;
        ajustarSpriteAnimado(sprite, lado);
    }
}

function mostrarPokemon(lado) {
    const mon = ativo(lado);
    const sprite = LADOS[lado].sprite;

    sprite.className = "sprite entrando";
    LADOS[lado].sombra.classList.remove("sumiu");
    definirSprite(sprite, lado, mon);
    sprite.alt = mon.nome;
    atualizarInfo(lado);

    // forca o navegador a aplicar o estado inicial antes de animar
    void sprite.offsetWidth;
    sprite.className = "sprite";
}

function animar(lado, classe, duracao) {
    const sprite = LADOS[lado].sprite;
    sprite.classList.remove(classe);
    void sprite.offsetWidth;
    sprite.classList.add(classe);
    return esperar(duracao).then(() => sprite.classList.remove(classe));
}

// ===================== menu de botoes =====================

// itens: [{ texto, sub, onclick, desabilitado }] (ate 4, as cores seguem a ordem)
function montarBotoes(itens) {
    menuEl.innerHTML = "";
    itens.forEach((item, i) => {
        const b = document.createElement("button");
        b.className = "botao c" + i;
        b.innerHTML = `<span></span>${item.sub ? "<small></small>" : ""}`;
        b.querySelector("span").textContent = item.texto;
        if (item.sub) b.querySelector("small").textContent = item.sub;
        b.disabled = !!item.desabilitado;
        b.addEventListener("click", item.onclick);
        menuEl.appendChild(b);
    });
}

function bloquearMenu(sim) {
    menuEl.classList.toggle("bloqueado", sim);
}

function mostrarVoltar(acao) {
    voltarEl.hidden = !acao;
    voltarEl.onclick = acao || null;
}

// ===================== temporizador =====================

function iniciarTempo(aoAcabar) {
    pararTempo();
    let restante = TEMPO_ESCOLHA;
    const atualizar = () => {
        tempoEl.textContent = restante + "s";
        tempoEl.classList.toggle("urgente", restante <= 5);
    };
    tempoEl.hidden = false;
    atualizar();
    estado.timer = setInterval(() => {
        restante--;
        atualizar();
        if (restante <= 0) {
            pararTempo();
            aoAcabar();
        }
    }, 1000);
}

function pararTempo() {
    if (estado && estado.timer) clearInterval(estado.timer);
    if (estado) estado.timer = null;
    tempoEl.hidden = true;
}

// ===================== paineis =====================

function abrirPainel(titulo, aviso, grade = false) {
    painelTitulo.textContent = titulo;
    painelAviso.textContent = aviso || "";
    painelLista.innerHTML = "";
    painelLista.classList.toggle("grade", grade);
    painelEl.hidden = false;
}

function fecharPainel() {
    painelEl.hidden = true;
}

function criarLinhaPokemon(mon, { desabilitado, onclick, extra }) {
    const b = document.createElement("button");
    b.className = "linhaPokemon";
    b.disabled = !!desabilitado;
    const pct = (mon.hp / mon.maxHp) * 100;
    b.innerHTML = `
        <img alt="">
        <div class="dados">
            <div></div>
            <div class="hp"><div class="barra"></div></div>
            <small></small>
        </div>`;
    b.querySelector("img").src = mon.frente;
    b.querySelector(".dados > div").textContent =
        `${mon.nome}  Nv${NIVEL}` + (mon.status ? `  [${STATUS[mon.status].sigla}]` : "");
    const barra = b.querySelector(".barra");
    barra.style.width = pct + "%";
    barra.classList.toggle("amarelo", pct <= 50 && pct > 20);
    barra.classList.toggle("vermelho", pct <= 20);
    barra.style.transition = "none";
    b.querySelector("small").textContent =
        (mon.hp > 0 ? `HP ${mon.hp}/${mon.maxHp}` : "Desmaiado") + (extra ? `  -  ${extra}` : "");
    b.addEventListener("click", onclick);
    painelLista.appendChild(b);
}

// lista de pokemons do time. usada para trocar e para escolher em quem usar o item
function abrirPainelTime({ titulo, aviso, validar, aoEscolher, aoVoltar }) {
    abrirPainel(titulo, aviso);
    estado.jogador.equipe.pokemons.forEach((mon, i) => {
        const motivo = validar(mon, i);
        criarLinhaPokemon(mon, {
            desabilitado: !!motivo,
            extra: mon.hp > 0 && motivo ? motivo : "",
            onclick: () => aoEscolher(i)
        });
    });
    mostrarVoltar(aoVoltar);
}

function itemServe(chave, mon) {
    const item = ITENS[chave];
    if (item.reviver) return mon.hp <= 0;
    if (mon.hp <= 0) return false;

    const curaHp = item.cura && mon.hp < mon.maxHp;
    const curaStatus = !!mon.status && (item.cura_status === "todos" ||
        (Array.isArray(item.cura_status) && item.cura_status.includes(mon.status)));
    return curaHp || curaStatus;
}

function abrirPainelItens(resolverAcao) {
    abrirPainel("Inventário", "", true);

    const disponiveis = Object.entries(ITENS).filter(([chave]) => estado.itens[chave] > 0);
    if (disponiveis.length === 0) painelAviso.textContent = "Você não tem nenhum item. Compre mais na loja!";

    for (const [chave, item] of disponiveis) {
        const qtd = estado.itens[chave];
        const b = document.createElement("button");
        b.className = "slotItem";
        b.innerHTML = `<img alt=""><span></span><span></span>`;
        b.querySelector("img").src = caminhoImg(item.img);
        b.querySelectorAll("span")[0].textContent = item.nome;
        b.querySelectorAll("span")[1].textContent = "x" + qtd;
        b.disabled = qtd <= 0;
        b.addEventListener("click", () => escolherAlvoItem(chave, resolverAcao));
        painelLista.appendChild(b);
    }
    mostrarVoltar(() => { fecharPainel(); mostrarMenuPrincipal(resolverAcao); });
}

function escolherAlvoItem(chave, resolverAcao) {
    abrirPainelTime({
        titulo: `Usar ${ITENS[chave].nome} em quem?`,
        aviso: "",
        validar: (mon) => (itemServe(chave, mon) ? false : "sem efeito"),
        aoEscolher: (i) => resolverAcao({ tipo: "item", item: chave, alvo: i }),
        aoVoltar: () => abrirPainelItens(resolverAcao)
    });
}

function abrirPainelTroca(resolverAcao) {
    const bloqueado = estado.cooldownTroca > 0;
    abrirPainelTime({
        titulo: "Pokémons",
        aviso: bloqueado
            ? `Troca bloqueada por mais ${estado.cooldownTroca} round${estado.cooldownTroca > 1 ? "s" : ""}.`
            : "Trocar gasta o seu turno e bloqueia novas trocas por 2 rounds.",
        validar: (mon, i) => {
            if (i === estado.jogador.equipe.indiceAtivo && !mon.estaDesmaiado()) return "em batalha";
            if (mon.hp <= 0) return "desmaiado";
            if (bloqueado) return "aguarde";
            return false;
        },
        aoEscolher: (i) => resolverAcao({ tipo: "trocar", indice: i }),
        aoVoltar: () => { fecharPainel(); mostrarMenuPrincipal(resolverAcao); }
    });
}

// ===================== escolha do jogador =====================

function mostrarMenuPrincipal(resolverAcao) {
    mostrarVoltar(null);
    bloquearMenu(false);
    montarBotoes([
        { texto: "Ataque", onclick: () => mostrarGolpes(resolverAcao) },
        { texto: "Inventário", onclick: () => abrirPainelItens(resolverAcao) },
        { texto: "pokemon", sub: estado.cooldownTroca > 0 ? `troca em ${estado.cooldownTroca}` : "", onclick: () => abrirPainelTroca(resolverAcao) },
        { texto: "Fugir", onclick: () => {
            if (confirm("Fugir conta como derrota e você não ganha pokédollars. Quer fugir mesmo?"))
                resolverAcao({ tipo: "fugir" });
        } }
    ]);
}

function mostrarGolpes(resolverAcao) {
    const mon = ativo("jogador");
    montarBotoes(mon.golpes.map((g, i) => ({
        texto: g.nome,
        sub: `${NOMES_TIPOS[g.tipo]} - ${g.poder ? "Pot " + g.poder : "Status"}`,
        onclick: () => resolverAcao({ tipo: "golpe", indice: i })
    })));
    mostrarVoltar(() => mostrarMenuPrincipal(resolverAcao));
}

// espera o jogador escolher (ou o tempo acabar e o jogo escolher um golpe)
function perguntarAcao() {
    return new Promise((resolve) => {
        const resolverAcao = (acao) => {
            pararTempo();
            fecharPainel();
            // volta para os 4 botoes principais (bloqueados) enquanto o round acontece
            mostrarMenuPrincipal(() => {});
            bloquearMenu(true);
            resolve(acao);
        };

        dizer(`O que ${ativo("jogador").nome} vai fazer?`, { rapido: true });
        mostrarMenuPrincipal(resolverAcao);

        iniciarTempo(() => {
            dizer("O tempo acabou!", { rapido: true });
            const golpes = ativo("jogador").golpes;
            resolverAcao({ tipo: "golpe", indice: Math.floor(Math.random() * golpes.length) });
        });
    });
}

// troca obrigatoria quando o pokemon desmaia (nao tem cooldown)
function perguntarTrocaForcada() {
    return new Promise((resolve) => {
        const escolher = (i) => {
            pararTempo();
            fecharPainel();
            mostrarVoltar(null);
            resolve(i);
        };

        abrirPainelTime({
            titulo: "Escolha o próximo pokémon",
            aviso: "",
            validar: (mon) => (mon.hp <= 0 ? "desmaiado" : false),
            aoEscolher: escolher,
            aoVoltar: null
        });

        iniciarTempo(() => escolher(estado.jogador.equipe.proximoVivo()));
    });
}

// ===================== combate =====================

// retorna false se o status impede de atacar neste turno
async function podeAgir(lado, mon) {
    const nome = nomeLog(lado, mon);

    if (mon.status === "sleep") {
        if (mon.sono > 0) {
            mon.sono--;
            await dizer(`${nome} está dormindo...`);
            return false;
        }
        mon.status = null;
        atualizarInfo(lado);
        await dizer(`${nome} acordou!`);
    }

    if (mon.status === "freeze") {
        if (Math.random() < 0.2) {
            mon.status = null;
            atualizarInfo(lado);
            await dizer(`${nome} descongelou!`);
        } else {
            await dizer(`${nome} está congelado e não consegue se mover!`);
            return false;
        }
    }

    if (mon.status === "paralysis" && Math.random() < 0.25) {
        await dizer(`${nome} está paralisado! Não consegue se mover!`);
        return false;
    }

    return true;
}

async function aplicarStatus(lado, alvo, status, avisarFalha) {
    const nome = nomeLog(lado, alvo);

    if (alvo.status || alvo.tipos.some((t) => IMUNE_STATUS[status].includes(t))) {
        if (avisarFalha) await dizer(alvo.status ? "Mas falhou!" : `Não afeta ${nome}...`);
        return;
    }

    alvo.status = status;
    if (status === "sleep") alvo.sono = 1 + Math.floor(Math.random() * 3);
    atualizarInfo(lado);
    await dizer(`${nome} ${STATUS[status].frase}`);
}

async function causarDano(lado, mon, dano) {
    mon.receberDano(dano);
    atualizarInfo(lado);
    animar(lado, "dano", 600);
    await esperar(850);
}

async function atacar(lado, golpe) {
    const alvoLado = outro(lado);
    const atq = ativo(lado);
    const def = ativo(alvoLado);

    if (!(await podeAgir(lado, atq))) return;

    await dizer(`${nomeLog(lado, atq)} usou ${golpe.nome}!`);
    animar(lado, lado === "jogador" ? "ataqueJogador" : "ataqueRobo", 350);
    await esperar(350);

    if (golpe.precisao && Math.random() * 100 >= golpe.precisao) {
        await dizer("Mas errou!");
        return;
    }

    if (!golpe.poder) {
        // golpe que so causa status
        await aplicarStatus(alvoLado, def, golpe.status, true);
        return;
    }

    const r = atq.calcularDanoContra(def, golpe);

    if (r.ef === 0) {
        await dizer(`Não afeta ${nomeLog(alvoLado, def)}...`);
        return;
    }

    await causarDano(alvoLado, def, r.dano);

    if (r.critico) await dizer("Foi um acerto crítico!");
    if (r.ef > 1) await dizer(r.ef >= 4 ? "É supereficaz! (4x)" : "É supereficaz!");
    if (r.ef < 1) await dizer(r.ef <= 0.25 ? "É pouco eficaz... (0.25x)" : "É pouco eficaz...");

    // drenagem (cura) ou recuo
    if (golpe.drenagem && r.dano > 0) {
        const valor = Math.floor(r.dano * Math.abs(golpe.drenagem) / 100);
        if (golpe.drenagem > 0 && valor > 0) {
            atq.curar(valor);
            atualizarInfo(lado);
            await dizer(`${nomeLog(lado, atq)} recuperou HP!`);
        } else if (golpe.drenagem < 0 && valor > 0) {
            await causarDano(lado, atq, valor);
            await dizer(`${nomeLog(lado, atq)} se machucou com o recuo!`);
        }
    }

    if (!def.estaDesmaiado() && golpe.status && Math.random() * 100 < golpe.chanceStatus) {
        await aplicarStatus(alvoLado, def, golpe.status, false);
    }

    if (def.estaDesmaiado()) await processarDesmaio(alvoLado);
    if (!estado.fim && atq.estaDesmaiado()) await processarDesmaio(lado);
}

// pokemon desmaiou: o robo manda o proximo, o jogador escolhe. sem pokemons = fim
async function processarDesmaio(lado) {
    const mon = ativo(lado);
    LADOS[lado].sprite.classList.add("desmaiou");
    LADOS[lado].sombra.classList.add("sumiu");
    await dizer(`${nomeLog(lado, mon)} desmaiou!`);

    const treinador = estado[lado];

    if (treinador.equipe.proximoVivo() < 0) {
        await terminar(lado === "robo");
        return;
    }

    // cada treinador escolhe do seu jeito (o jogador na tela, o robo sozinho)
    treinador.equipe.indiceAtivo = await treinador.escolherSubstituto();
    await dizer(treinador.mensagemEntrada(treinador.equipe.ativo));
    mostrarPokemon(lado);
}

async function trocarJogador(indice) {
    await dizer(`Volte, ${ativo("jogador").nome}!`);
    LADOS.jogador.sprite.classList.add("desmaiou");
    LADOS.jogador.sombra.classList.add("sumiu");
    await esperar(400);
    estado.jogador.equipe.indiceAtivo = indice;
    await dizer(estado.jogador.mensagemEntrada(ativo("jogador")));
    mostrarPokemon("jogador");
    await esperar(300);
}

async function usarItem({ item: chave, alvo }) {
    const item = ITENS[chave];
    const mon = estado.jogador.equipe.pokemons[alvo];

    // so aplica o efeito se o banco confirmar que o jogador ainda tem o item
    if (!(await gastarItemNoBanco(chave))) {
        await dizer("Você não tem mais esse item!");
        estado.itens[chave] = 0;
        return;
    }
    estado.itens[chave]--;

    await dizer(`Você usou ${item.nome} em ${mon.nome}!`);

    if (item.reviver) {
        mon.reviver();
    } else {
        if (item.cura) mon.curar(item.cura === "max" ? mon.maxHp : item.cura);
        if (item.cura_status === "todos" || (item.cura_status && item.cura_status.includes(mon.status))) {
            mon.limparStatus();
        }
    }

    if (alvo === estado.jogador.equipe.indiceAtivo) atualizarInfo("jogador");
    await esperar(800);
    await dizer(item.reviver ? `${mon.nome} voltou à batalha!` : `${mon.nome} está melhor!`);
}

async function executarRodada(acao) {
    if (acao.tipo === "fugir") {
        await dizer("Você fugiu da batalha!");
        await terminar(false, true);
        return;
    }

    const jogador = ativo("jogador");
    const robo = ativo("robo");
    const golpeRobo = robo.golpes[estado.robo.escolherAcao(estado).indice];

    if (acao.tipo === "trocar") {
        await trocarJogador(acao.indice);
        estado.cooldownTroca = COOLDOWN_TROCA;
        await atacar("robo", golpeRobo);
    } else if (acao.tipo === "item") {
        await usarItem(acao);
        await atacar("robo", golpeRobo);
    } else {
        const golpeJogador = jogador.golpes[acao.indice];
        const ordem = [
            { lado: "jogador", mon: jogador, golpe: golpeJogador },
            { lado: "robo", mon: robo, golpe: golpeRobo }
        ];

        // prioridade do golpe, depois velocidade, empate sorteia
        ordem.sort((a, b) =>
            (b.golpe.prioridade - a.golpe.prioridade) ||
            (b.mon.velocidadeEfetiva - a.mon.velocidadeEfetiva) ||
            (Math.random() - 0.5));

        for (const { lado, mon, golpe } of ordem) {
            if (estado.fim) break;
            // se o pokemon desmaiou ou foi trocado antes de agir, perde o turno
            if (ativo(lado) !== mon || mon.estaDesmaiado()) continue;
            await atacar(lado, golpe);
        }
    }

    if (!estado.fim) await fimDoRound();
}

async function fimDoRound() {
    for (const lado of ["jogador", "robo"]) {
        const mon = ativo(lado);
        if (estado.fim || mon.estaDesmaiado()) continue;
        if (mon.status !== "poison" && mon.status !== "burn") continue;

        await dizer(`${nomeLog(lado, mon)} sofre com ${mon.status === "poison" ? "o veneno" : "a queimadura"}!`);
        await causarDano(lado, mon, Math.max(1, Math.floor(mon.maxHp / 8)));
        if (mon.estaDesmaiado()) await processarDesmaio(lado);
    }

    if (estado.fim) return;
    estado.rodada++;
    if (estado.cooldownTroca > 0) estado.cooldownTroca--;
}

// ===================== fim de jogo =====================

// conta quais pokemons do robo entraram na colecao
async function anunciarConquistas(pokemons) {
    const nomes = (lista) => lista.map((p) => p.nome).join(", ").replace(/, ([^,]*)$/, " e $1");
    const novos = pokemons.filter((p) => p.novo);
    const repetidos = pokemons.filter((p) => !p.novo);

    if (novos.length) await dizer(`Você conquistou ${nomes(novos)}! Eles já estão na sua coleção.`);
    if (repetidos.length) await dizer(`Você já tinha ${nomes(repetidos)}.`);
}

async function terminar(vitoria, fugiu = false) {
    estado.fim = true;
    pararTempo();
    fecharPainel();

    const resultado = fugiu ? "fuga" : (vitoria ? "vitoria" : "derrota");
    const vivos = estado.jogador.equipe.vivos().length;

    if (vitoria) await dizer("Você venceu a batalha!");
    else if (!fugiu) await dizer("Todos os seus pokémons desmaiaram...");

    const resposta = await fecharBatalhaNoServidor(resultado, vivos);

    if (resposta.erro) await dizer(resposta.erro);
    else if (resposta.recompensa > 0) await dizer(`Você ganhou ${resposta.recompensa} pokédollars! Agora você tem ${resposta.pokedolares}.`);
    else await dizer("Você não ganhou pokédollars.");

    if (vitoria && !resposta.erro) await anunciarConquistas(resposta.pokemons || []);

    bloquearMenu(false);
    mostrarVoltar(null);
    montarBotoes([
        // revanche sem recarregar a pagina (recarregar acumularia entradas no historico do botao voltar)
        { texto: "Revanche", onclick: () => { fugaEnviada = false; saidaLiberada = false; iniciar(); } },
        { texto: "Equipe", onclick: () => (location.href = URL_EQUIPE) },
        { texto: "Loja", onclick: () => (location.href = URL_LOJA) },
        { texto: "Início", onclick: () => (location.href = URL_HOME) }
    ]);
}

// ===================== sair da batalha =====================

// Sair no meio da batalha (voltar, fechar a aba, recarregar, digitar outro endereco) faz perder:
// conta como fuga e nao paga pokedollars. O navegador pergunta antes, e se a pessoa sair mesmo
// a batalha e fechada no servidor como fuga.
const AVISO_SAIR = "Se você sair da batalha, você perde ela (conta como fuga e não ganha pokédollars). Quer sair mesmo?";
let saidaLiberada = false;   // true quando a saida ja foi confirmada ou o proprio jogo vai recarregar a pagina
let fugaEnviada = false;
let pilhaCriada = false;
let voltarProtegido = false;

function batalhaEmAndamento() {
    return !!estado && !estado.fim && (!!estado.idBatalha || estado.robo.equipe.pokemons.length === 3);
}

// fechar a aba, recarregar ou digitar outro endereco: aviso padrao do navegador (o texto nao e personalizavel)
window.addEventListener("beforeunload", (evento) => {
    if (saidaLiberada || !batalhaEmAndamento()) return;
    evento.preventDefault();
    evento.returnValue = "";
});

// a pagina esta mesmo saindo: fecha a batalha no servidor como fuga (sendBeacon continua depois que a pagina fecha)
window.addEventListener("pagehide", () => {
    if (fugaEnviada || !estado || estado.fim || !estado.idBatalha) return;
    fugaEnviada = true;

    const dados = new FormData();
    dados.append("resultado", "fuga");
    dados.append("vivos", 0);
    navigator.sendBeacon(`/monstroguelselnius/api/batalha/${estado.idBatalha}/finalizar`, dados);
});

// voltou pelo historico e a pagina veio pronta do cache: a batalha antiga ja foi perdida, comeca outra
window.addEventListener("pageshow", (evento) => {
    if (evento.persisted) location.reload();
});

// botao voltar do navegador: coloca uma entrada extra no historico para poder perguntar antes de sair.
// (o navegador so aceita isso depois de um clique ou tecla da pessoa, por isso espera a primeira interacao)
function protegerBotaoVoltar() {
    if (voltarProtegido) return;   // a revanche chama iniciar() de novo: so registra uma vez
    voltarProtegido = true;

    const criar = () => {
        if (pilhaCriada) return;
        pilhaCriada = true;
        history.pushState({ batalha: true }, "");
    };
    document.addEventListener("pointerdown", criar, { once: true });
    document.addEventListener("keydown", criar, { once: true });

    window.addEventListener("popstate", () => {
        if (!pilhaCriada) return;

        // batalha ja acabou: so continua voltando
        if (!batalhaEmAndamento() || saidaLiberada) {
            saidaLiberada = true;
            history.back();
            return;
        }

        if (confirm(AVISO_SAIR)) {
            saidaLiberada = true;
            location.href = URL_HOME;
        } else {
            history.pushState({ batalha: true }, "");
        }
    });
}

// ===================== inicio =====================

async function iniciar() {
    estado = new Batalha();
    logTexto.innerHTML = "";
    bloquearMenu(true);
    montarBotoes(["Ataque", "Inventário", "pokemon", "Fugir"].map((texto) => ({ texto, onclick: () => {} })));

    dizer("Carregando pokémons e inventário...", { rapido: true });

    let inventarioOk = true;

    // equipe do jogador (salva no banco)
    let idsJogador, idsBasicos;
    try {
        ({ idsJogador, idsBasicos } = await carregarEquipe());
    } catch (erro) {
        if (erro.message === "sessao expirada") return;
        console.error(erro);
        logTexto.innerHTML = "";
        dizer("Não consegui carregar a sua equipe. Confira se o docs/banco.sql foi executado no banco.", { rapido: true });
        bloquearMenu(false);
        montarBotoes([{ texto: "Recarregar", onclick: () => { saidaLiberada = true; location.reload(); } }]);
        return;
    }

    // ainda nao escolheu a equipe: vai para a tela de escolha
    if (idsJogador.length !== 3) {
        location.href = URL_EQUIPE;
        return;
    }

    // o servidor abre a batalha e sorteia o time do robo (assim ele sabe quais pokemons voce ganha se vencer).
    // se o servidor falhar a batalha acontece igual, so que sem recompensas
    const batalha = await abrirBatalhaNoServidor();

    // sem login volta para o login; sem equipe vai escolher a equipe
    if (batalha && batalha.sair) return;
    if (batalha && batalha.semEquipe) {
        location.href = URL_EQUIPE;
        return;
    }
    estado.idBatalha = batalha ? batalha.id : null;

    try {
        const idsRobo = batalha ? batalha.robo : sortearIds(3, idsJogador, idsBasicos.length >= 6 ? idsBasicos : null);

        // caminhos das imagens (arquivos do projeto). Precisa estar pronto antes de montar os pokemons
        await carregarImagens();

        // se so o inventario falhar (ex: banco sem as tabelas novas) o jogo continua, sem itens
        const [, , ...pokemons] = await Promise.all([
            carregarInventario().catch((erro) => {
                if (erro.message === "sessao expirada") throw erro;
                console.error(erro);
                inventarioOk = false;
            }),
            TabelaTipos.carregar(),
            ...[...idsJogador, ...idsRobo].map(criarPokemon)
        ]);

        estado.jogador.equipe = new Time(pokemons.slice(0, 3));
        estado.robo.equipe = new Time(pokemons.slice(3));

        await Promise.all(pokemons.flatMap((p) => [
            precarregarImagem(p.frente), precarregarImagem(p.costas),
            p.frenteAnim && precarregarImagem(p.frenteAnim),
            p.costasAnim && precarregarImagem(p.costasAnim)
        ]));
    } catch (erro) {
        console.error(erro);
        logTexto.innerHTML = "";
        dizer("Não consegui carregar os pokémons da PokeAPI. Verifique a internet.", { rapido: true });
        bloquearMenu(false);
        montarBotoes([{ texto: "Recarregar", onclick: () => { saidaLiberada = true; location.reload(); } }]);
        return;
    }

    logTexto.innerHTML = "";
    protegerBotaoVoltar();
    iniciarMusica();
    mostrarPokemon("robo");
    if (!inventarioOk) await dizer("Não consegui carregar o seu inventário, então você vai sem itens nesta batalha.");
    if (!estado.idBatalha) await dizer("Não consegui registrar a batalha. Rode o docs/banco.sql no banco.");
    await dizer("Um treinador robô quer batalhar!");
    await dizer(`Ele enviou ${ativo("robo").nome}!`);
    mostrarPokemon("jogador");
    await dizer(`Vai, ${ativo("jogador").nome}!`);
    await dizer("Atenção: se você sair da tela de batalha, você perde a batalha!");

    while (!estado.fim) {
        const acao = await estado.jogador.escolherAcao(estado);
        await executarRodada(acao);
    }
}

iniciar();
