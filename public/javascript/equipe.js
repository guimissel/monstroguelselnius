const equipeEl = document.getElementById("equipe");
const listaEl = document.getElementById("lista");
const mensagemEl = document.getElementById("mensagem");
const prontoEl = document.getElementById("pronto");
const explicacaoEl = document.getElementById("explicacao");
const contagemEl = document.getElementById("contagem");
const pedrasEl = document.getElementById("pedras");
const fraquezasTimeEl = document.getElementById("fraquezas-time");
const fraquezasVazioEl = document.getElementById("fraquezas-vazio");
const eficazTimeEl = document.getElementById("eficaz-time");
const eficazVazioEl = document.getElementById("eficaz-vazio");

const TAMANHO_EQUIPE = 3;
const URL_JOGO = "/monstroguelselnius/jogo";
const URL_LOGIN = "/monstroguelselnius/login";

const NOMES_TIPOS = {
    normal: "Normal", fire: "Fogo", water: "Água", electric: "Elétrico",
    grass: "Planta", ice: "Gelo", fighting: "Lutador", poison: "Veneno",
    ground: "Terra", flying: "Voador", psychic: "Psíquico", bug: "Inseto",
    rock: "Pedra", ghost: "Fantasma", dragon: "Dragão", dark: "Sombrio",
    steel: "Aço", fairy: "Fada"
};

const IDS_TIPOS = {
    normal: 1, fighting: 2, flying: 3, poison: 4, ground: 5, rock: 6,
    bug: 7, ghost: 8, steel: 9, fire: 10, water: 11, grass: 12,
    electric: 13, psychic: 14, ice: 15, dragon: 16, dark: 17, fairy: 18
};

let inicial = false;       // true na primeira escolha (ainda sem colecao)
let disponiveis = [];      // [{id, nome}] de onde escolher: a sua colecao (na 1a vez, os basicos)
let escolhidos = [];       // ids na ordem em que o jogador escolheu
let catalogo = {};         // id -> pokemon completo (tipos, fraquezas, evolucao) vindo de api/pokemon
let pedras = 0;            // Pedras Evolutivas que o jogador tem
let salvando = false;

// a imagem vem direto do repositorio de sprites da PokeAPI
const imagem = (id) => `https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/${id}.png`;
const imagemTipo = (tipo) => {
    const id = IDS_TIPOS[tipo];
    return id
        ? `https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/types/generation-ix/scarlet-violet/${id}.png`
        : "";
};

const tiposDe = (id) => catalogo[id]?.tipos || [];

function mostrarMensagem(texto, tipo = "") {
    mensagemEl.textContent = texto;
    mensagemEl.className = "mensagem" + (tipo ? " " + tipo : "");
}

function nomeDoPokemon(id) {
    const p = disponiveis.find((b) => b.id === id);
    return p ? p.nome : `#${id}`;
}

// pokemons do banco: tipos, fraquezas e evolucao
async function carregarCatalogo() {
    try {
        const resposta = await fetch("/monstroguelselnius/api/pokemon");
        if (!resposta.ok) return;
        const dados = await resposta.json();
        catalogo = {};
        for (const p of dados.pokemons) catalogo[p.id] = p;
    } catch (erro) {
        console.error(erro);   // sem catalogo as cartas aparecem sem tipos
    }
}

async function carregar(manterEscolha = false) {
    const escolhaAnterior = escolhidos.slice();
    mostrarMensagem("Carregando...");

    try {
        await carregarCatalogo();
        const resposta = await fetch("/monstroguelselnius/api/equipe");

        // sessao expirou
        if (resposta.status === 401) {
            window.location.href = URL_LOGIN;
            return;
        }

        const dados = await resposta.json();
        if (!resposta.ok) throw new Error(dados.mensagem);

        disponiveis = dados.disponiveis;
        inicial = dados.inicial;
        pedras = dados.pedras || 0;
        escolhidos = (manterEscolha ? escolhaAnterior : dados.equipe.map((p) => p.id))
            .filter((id) => disponiveis.some((b) => b.id === id));

        explicacaoEl.textContent = dados.inicial
            ? "Escolha os seus 3 primeiros pokémons. Eles formam o começo da sua coleção. Todos começam no nível básico e podem evoluir com a Pedra Evolutiva (compre na loja)."
            : `Você tem ${disponiveis.length} pokémons. Escolha 3 para batalhar. Vença batalhas para conquistar os pokémons do robô!`;

        if (!manterEscolha) {
            mostrarMensagem(dados.equipe.length === TAMANHO_EQUIPE
                ? "Esta é a sua equipe atual. Você pode trocar quando quiser."
                : "Você ainda não tem equipe. Clique nos pokémons para escolher 3.");
        } else {
            mostrarMensagem("");
        }
        desenhar();
    } catch (erro) {
        console.error(erro);
        mostrarMensagem("Não consegui carregar a equipe. Confira se o app/database/schema.sql foi executado no banco e recarregue a página.", "erro");
    }
}

// gasta 1 Pedra Evolutiva para evoluir o pokemon
async function evoluir(id) {
    if (salvando) return;
    salvando = true;
    desenhar();

    try {
        const dados = new FormData();
        dados.append("pokemon", id);
        const resposta = await fetch("/monstroguelselnius/api/equipe/evoluir", { method: "POST", body: dados });

        if (resposta.status === 401) {
            window.location.href = URL_LOGIN;
            return;
        }

        const resultado = await resposta.json();
        if (!resposta.ok) {
            mostrarMensagem(resultado.mensagem || "Não foi possível evoluir.", "erro");
        } else {
            // a escolha que ainda nao foi salva continua, com a evolucao no lugar do pokemon antigo
            escolhidos = escolhidos.map((e) => (e === resultado.antigo.id ? resultado.novo.id : e));
            salvando = false;
            await carregar(true);
            mostrarMensagem(resultado.mensagem, "ok");
            return;
        }
    } catch (erro) {
        console.error(erro);
        mostrarMensagem("Não consegui falar com o servidor. Tente de novo.", "erro");
    }

    salvando = false;
    desenhar();
}

function alternar(id) {
    if (salvando) return;

    if (escolhidos.includes(id)) {
        escolhidos = escolhidos.filter((e) => e !== id);
    } else if (escolhidos.length < TAMANHO_EQUIPE) {
        escolhidos.push(id);
    } else {
        mostrarMensagem("Você já escolheu 3. Clique em um pokémon da equipe para tirar.", "erro");
        return;
    }

    mostrarMensagem("");
    desenhar();
}

function desenharEquipe() {
    equipeEl.innerHTML = "";

    for (let i = 0; i < TAMANHO_EQUIPE; i++) {
        const id = escolhidos[i];
        const vaga = document.createElement("button");
        vaga.type = "button";
        vaga.className = "vaga" + (id ? " cheia" : "");

        if (id) {
            const img = document.createElement("img");
            img.src = imagem(id);
            img.alt = nomeDoPokemon(id);
            const nome = document.createElement("span");
            nome.textContent = nomeDoPokemon(id);
            const tirar = document.createElement("span");
            tirar.className = "tirar";
            tirar.textContent = "clique para tirar";
            vaga.append(img, nome, tirar);
            vaga.addEventListener("click", () => alternar(id));
        } else {
            vaga.disabled = true;
            const numero = document.createElement("span");
            numero.className = "numero";
            numero.textContent = `Vaga ${i + 1}`;
            vaga.append(numero);
        }

        equipeEl.appendChild(vaga);
    }
}

function criarBadgeTipo(tipo, quantidade = null) {
    const item = document.createElement("span");
    const nomeTipo = NOMES_TIPOS[tipo] || tipo;
    const icone = document.createElement("img");
    icone.className = "analise-icone-tipo";
    icone.src = imagemTipo(tipo);
    icone.alt = "";
    icone.setAttribute("aria-hidden", "true");
    item.className = "analise-badge";
    item.title = nomeTipo;
    item.setAttribute("role", "img");
    item.setAttribute("aria-label", `${nomeTipo}${quantidade === null ? "" : `: ${quantidade} pokémon(s)`}`);
    item.appendChild(icone);

    if (quantidade !== null) {
        const contador = document.createElement("span");
        contador.className = "analise-contador";
        contador.textContent = quantidade;
        item.appendChild(contador);
    }
    return item;
}

function adicionarResumo(lista, tipo, quantidade = null) {
    const item = document.createElement("li");
    item.appendChild(criarBadgeTipo(tipo, quantidade));
    lista.appendChild(item);
}

function desenharAnaliseEquipe() {
    fraquezasTimeEl.innerHTML = "";
    eficazTimeEl.innerHTML = "";
    fraquezasVazioEl.textContent = "Escolha pokémons para ver as fraquezas.";
    eficazVazioEl.textContent = "Escolha pokémons para ver contra quem o time é eficaz.";

    const time = escolhidos.map((id) => catalogo[id]).filter(Boolean);
    if (!time.length) {
        if (escolhidos.length) {
            fraquezasVazioEl.textContent = "Não foi possível carregar os dados de tipos dos pokémons.";
            eficazVazioEl.textContent = "Não foi possível carregar os dados de tipos dos pokémons.";
        }
        fraquezasVazioEl.hidden = false;
        eficazVazioEl.hidden = false;
        return;
    }
    if (time.length !== escolhidos.length) {
        fraquezasVazioEl.textContent = "Não foi possível carregar os dados de tipos de toda a equipe.";
        eficazVazioEl.textContent = "Não foi possível carregar os dados de tipos de toda a equipe.";
        fraquezasVazioEl.hidden = false;
        eficazVazioEl.hidden = false;
        return;
    }

    const fraquezas = new Map();
    for (const pokemon of time) {
        for (const tipo of pokemon.fraquezas || []) {
            if (!fraquezas.has(tipo)) fraquezas.set(tipo, []);
            fraquezas.get(tipo).push(pokemon.nome);
        }
    }

    fraquezasVazioEl.hidden = fraquezas.size > 0;
    if (fraquezas.size === 0) {
        fraquezasVazioEl.textContent = "Nenhuma fraqueza encontrada para os pokémons escolhidos.";
    } else {
        fraquezasVazioEl.textContent = "";
        for (const [tipo, nomes] of fraquezas) {
            adicionarResumo(fraquezasTimeEl, tipo, nomes.length);
        }
    }

    const tiposDeAtaque = new Set(time.flatMap((pokemon) => pokemon.tipos || []));
    const idsDoTime = new Set(escolhidos);
    const eficacia = new Map();
    for (const pokemon of Object.values(catalogo)) {
        if (idsDoTime.has(pokemon.id)) continue;
        const vulneravel = (pokemon.fraquezas || []).some((tipo) => tiposDeAtaque.has(tipo));
        if (!vulneravel) continue;

        for (const tipo of pokemon.tipos || []) {
            if (!eficacia.has(tipo)) eficacia.set(tipo, new Set());
            eficacia.get(tipo).add(pokemon.id);
        }
    }

    eficazVazioEl.hidden = eficacia.size > 0;
    if (eficacia.size === 0) {
        eficazVazioEl.textContent = "Nenhum alvo super efetivo encontrado no catálogo.";
    } else {
        eficazVazioEl.textContent = "";
        for (const [tipo, alvos] of eficacia) {
            adicionarResumo(eficazTimeEl, tipo, alvos.size);
        }
    }
}

function desenharLista() {
    listaEl.innerHTML = "";
    const cheio = escolhidos.length >= TAMANHO_EQUIPE;

    for (const pokemon of disponiveis) {
        const posicao = escolhidos.indexOf(pokemon.id) + 1;

        const carta = document.createElement("div");
        carta.setAttribute("role", "button");
        carta.tabIndex = 0;
        carta.className = "carta" + (posicao ? " escolhida" : "") + (cheio ? " cheio" : "");
        if (posicao) carta.dataset.posicao = posicao;

        const img = document.createElement("img");
        img.src = imagem(pokemon.id);
        img.alt = "";
        img.loading = "lazy";

        const nome = document.createElement("span");
        nome.className = "nome";
        nome.textContent = pokemon.nome;

        const caixaTipos = document.createElement("span");
        caixaTipos.className = "tipos";
        const tipos = tiposDe(pokemon.id);
        caixaTipos.setAttribute("aria-label", tipos.length
            ? `Tipos: ${tipos.map((tipo) => NOMES_TIPOS[tipo] || tipo).join(", ")}`
            : "Tipos não disponíveis");
        for (const tipo of tipos) {
            caixaTipos.appendChild(criarBadgeTipo(tipo));
        }

        carta.append(img, nome, caixaTipos);

        // Fraquezas do Pokemon, calculadas pelas traits de tipo no PHP.
        const fraquezas = catalogo[pokemon.id]?.fraquezas || [];
        const caixaFraquezas = document.createElement("span");
        caixaFraquezas.className = "carta-fraquezas";
        caixaFraquezas.setAttribute("aria-label", fraquezas.length
            ? `Fraquezas: ${fraquezas.map((tipo) => NOMES_TIPOS[tipo] || tipo).join(", ")}`
            : "Sem fraquezas");
        if (fraquezas.length) {
            const rotuloFraquezas = document.createElement("span");
            rotuloFraquezas.className = "rotulo-fraquezas";
            rotuloFraquezas.textContent = "Fraquezas";
            caixaFraquezas.appendChild(rotuloFraquezas);
            for (const tipo of fraquezas) caixaFraquezas.appendChild(criarBadgeTipo(tipo));
        } else {
            caixaFraquezas.classList.add("sem-fraquezas");
            caixaFraquezas.textContent = "Sem fraquezas";
        }
        carta.appendChild(caixaFraquezas);

        // evolucao: so para quem ja tem pokemons (na primeira escolha ainda nao existe colecao)
        const evolucao = catalogo[pokemon.id]?.evolucao;
        if (evolucao && !inicial) {
            const botao = document.createElement("button");
            botao.type = "button";
            botao.className = "evoluir";
            botao.textContent = `Evoluir (${evolucao.nome})`;
            botao.disabled = salvando || pedras < 1;
            botao.title = pedras < 1 ? "Você precisa de uma Pedra Evolutiva. Compre na loja." : "Gasta 1 Pedra Evolutiva";
            botao.addEventListener("click", (evento) => {
                evento.stopPropagation();
                evoluir(pokemon.id);
            });
            carta.appendChild(botao);
        }

        carta.addEventListener("click", () => alternar(pokemon.id));
        carta.addEventListener("keydown", (evento) => {
            if (evento.key === "Enter" || evento.key === " ") {
                evento.preventDefault();
                alternar(pokemon.id);
            }
        });
        listaEl.appendChild(carta);
    }
}

function desenhar() {
    desenharEquipe();
    desenharLista();
    desenharAnaliseEquipe();

    if (inicial) {
        pedrasEl.textContent = "";
    } else {
        pedrasEl.textContent = `Pedras Evolutivas: ${pedras}. Com uma pedra você evolui um pokémon (compre na loja).`;
    }

    prontoEl.disabled = salvando || escolhidos.length !== TAMANHO_EQUIPE;
    contagemEl.textContent = `${escolhidos.length} de ${TAMANHO_EQUIPE} escolhidos`;
}

async function salvar() {
    if (salvando || escolhidos.length !== TAMANHO_EQUIPE) return;
    salvando = true;
    desenhar();

    try {
        const dados = new FormData();
        dados.append("pokemons", escolhidos.join(","));

        const resposta = await fetch("/monstroguelselnius/api/equipe", { method: "POST", body: dados });

        if (resposta.status === 401) {
            window.location.href = URL_LOGIN;
            return;
        }

        const resultado = await resposta.json();

        if (!resposta.ok) {
            mostrarMensagem(resultado.mensagem || "Não foi possível salvar a equipe.", "erro");
        } else {
            // equipe salva: vai direto para a batalha
            mostrarMensagem("Equipe salva! Indo para a batalha...", "ok");
            window.location.href = URL_JOGO;
            return;
        }
    } catch (erro) {
        console.error(erro);
        mostrarMensagem("Não consegui falar com o servidor. Tente de novo.", "erro");
    }

    salvando = false;
    desenhar();
}

prontoEl.addEventListener("click", salvar);
carregar();
