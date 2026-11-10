const equipeEl = document.getElementById("equipe");
const listaEl = document.getElementById("lista");
const mensagemEl = document.getElementById("mensagem");
const prontoEl = document.getElementById("pronto");
const explicacaoEl = document.getElementById("explicacao");
const contagemEl = document.getElementById("contagem");

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

let disponiveis = [];      // [{id, nome}] de onde escolher: a sua colecao (na 1a vez, os basicos)
let escolhidos = [];       // ids na ordem em que o jogador escolheu
const tipos = {};          // id -> ["fire", ...] (vem da PokeAPI, so enfeite)
let salvando = false;

// a imagem vem direto do repositorio de sprites da PokeAPI
const imagem = (id) => `https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/${id}.png`;

function mostrarMensagem(texto, tipo = "") {
    mensagemEl.textContent = texto;
    mensagemEl.className = "mensagem" + (tipo ? " " + tipo : "");
}

function nomeDoPokemon(id) {
    const p = disponiveis.find((b) => b.id === id);
    return p ? p.nome : `#${id}`;
}

async function carregar() {
    mostrarMensagem("Carregando...");

    try {
        const resposta = await fetch("/monstroguelselnius/api/equipe");

        // sessao expirou
        if (resposta.status === 401) {
            window.location.href = URL_LOGIN;
            return;
        }

        const dados = await resposta.json();
        if (!resposta.ok) throw new Error(dados.mensagem);

        disponiveis = dados.disponiveis;
        escolhidos = dados.equipe.map((p) => p.id).filter((id) => disponiveis.some((b) => b.id === id));

        explicacaoEl.textContent = dados.inicial
            ? "Escolha os seus 3 primeiros pokémons. Eles formam o começo da sua coleção. Todos começam no nível básico e vão poder evoluir depois."
            : `Você tem ${disponiveis.length} pokémons. Escolha 3 para batalhar. Vença batalhas para conquistar os pokémons do robô!`;

        mostrarMensagem(dados.equipe.length === TAMANHO_EQUIPE
            ? "Esta é a sua equipe atual. Você pode trocar quando quiser."
            : "Você ainda não tem equipe. Clique nos pokémons para escolher 3.");
        desenhar();
        carregarTipos();
    } catch (erro) {
        console.error(erro);
        mostrarMensagem("Não consegui carregar a equipe. Confira se o docs/banco.sql foi executado no banco e recarregue a página.", "erro");
    }
}

// os tipos aparecem nas cartas quando a PokeAPI responde. se falhar, as cartas continuam normais
async function carregarTipos() {
    await Promise.allSettled(disponiveis.map(async (b) => {
        const resposta = await fetch(`https://pokeapi.co/api/v2/pokemon/${b.id}`);
        if (!resposta.ok) return;
        const dados = await resposta.json();
        tipos[b.id] = dados.types.map((t) => t.type.name);
    }));
    desenharLista();
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

function desenharLista() {
    listaEl.innerHTML = "";
    const cheio = escolhidos.length >= TAMANHO_EQUIPE;

    for (const pokemon of disponiveis) {
        const posicao = escolhidos.indexOf(pokemon.id) + 1;

        const carta = document.createElement("button");
        carta.type = "button";
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
        for (const tipo of tipos[pokemon.id] || []) {
            const etiqueta = document.createElement("span");
            etiqueta.className = "tipo";
            etiqueta.textContent = NOMES_TIPOS[tipo] || tipo;
            caixaTipos.appendChild(etiqueta);
        }

        carta.append(img, nome, caixaTipos);
        carta.addEventListener("click", () => alternar(pokemon.id));
        listaEl.appendChild(carta);
    }
}

function desenhar() {
    desenharEquipe();
    desenharLista();

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
