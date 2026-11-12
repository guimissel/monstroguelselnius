const abasEl = document.getElementById("abas");
const gradeEl = document.getElementById("inventario");
const detalhesEl = document.getElementById("detalhes");
const mensagemEl = document.getElementById("mensagem");
const pokedolaresEl = document.getElementById("pokedolares");

// nomes das categorias que vem do banco (item_loja.categoria)
const NOMES_CATEGORIAS = {
    pocao_hp: "Poções de HP",
    pocao_condicao: "Poções de condição",
    evolucao: "Evolução"
};

let itens = [];
let filtro = "todos";
let selecionado = null;   // chave do item aberto nos detalhes

const caminhoImg = (arquivo) => "public/img/" + encodeURI(arquivo);

function mostrarMensagem(texto, erro = false) {
    mensagemEl.textContent = texto;
    mensagemEl.classList.toggle("erro", erro);
    mensagemEl.hidden = !texto;
}

async function carregar() {
    mostrarMensagem("Carregando inventário...");

    try {
        const resposta = await fetch("/monstroguelselnius/api/inventario");

        // sessao expirou
        if (resposta.status === 401) {
            window.location.href = "/monstroguelselnius/login";
            return;
        }

        const dados = await resposta.json();
        if (!resposta.ok) throw new Error(dados.mensagem);

        itens = dados.itens;
        pokedolaresEl.textContent = dados.pokedolares;
        mostrarMensagem("");
        desenhar();
    } catch (erro) {
        console.error(erro);
        mostrarMensagem("Não consegui carregar o inventário. Confira se o app/database/schema.sql foi executado no banco e recarregue a página.", true);
    }
}

function desenharAbas() {
    abasEl.innerHTML = "";

    // so mostra as categorias que o jogador realmente tem
    const categorias = ["todos", ...new Set(itens.map((i) => i.categoria))];
    if (categorias.length <= 2) return;

    for (const categoria of categorias) {
        const b = document.createElement("button");
        b.className = "botao claro" + (categoria === filtro ? " ativo" : "");
        b.textContent = categoria === "todos" ? "Todos" : (NOMES_CATEGORIAS[categoria] || categoria);
        b.addEventListener("click", () => {
            filtro = categoria;
            selecionado = null;
            desenhar();
        });
        abasEl.appendChild(b);
    }
}

function desenharGrade() {
    gradeEl.innerHTML = "";

    const visiveis = itens.filter((i) => filtro === "todos" || i.categoria === filtro);

    for (const item of visiveis) {
        const b = document.createElement("button");
        b.className = "slot" + (item.chave === selecionado ? " selecionado" : "");
        b.title = item.nome;

        const img = document.createElement("img");
        img.src = caminhoImg(item.imagem);
        img.alt = item.nome;

        const nome = document.createElement("span");
        nome.className = "nome";
        nome.textContent = item.nome;

        const qtd = document.createElement("span");
        qtd.className = "qtd";
        qtd.textContent = "x" + item.quantidade;

        b.append(img, nome, qtd);
        b.addEventListener("click", () => {
            selecionado = item.chave;
            desenhar();
        });
        gradeEl.appendChild(b);
    }

    // completa com slots vazios (minimo 10, sempre fechando a linha de 5)
    const total = Math.max(10, Math.ceil(visiveis.length / 5) * 5);
    for (let i = visiveis.length; i < total; i++) {
        const vazio = document.createElement("div");
        vazio.className = "slot";
        gradeEl.appendChild(vazio);
    }

    if (itens.length === 0) {
        mostrarMensagem("Sua mochila está vazia. Ganhe pokédollars nas batalhas para comprar itens na loja!");
    }
}

function desenharDetalhes() {
    detalhesEl.innerHTML = "";
    const item = itens.find((i) => i.chave === selecionado);

    if (!item) {
        const p = document.createElement("p");
        p.className = "vazio";
        p.textContent = "Clique em um item para ver os detalhes.";
        detalhesEl.appendChild(p);
        return;
    }

    const img = document.createElement("img");
    img.src = caminhoImg(item.imagem);
    img.alt = item.nome;

    const titulo = document.createElement("h2");
    titulo.textContent = item.nome;

    const descricao = document.createElement("p");
    descricao.textContent = item.descricao;

    const quantidade = document.createElement("p");
    quantidade.textContent = `Você tem: ${item.quantidade}`;

    const preco = document.createElement("p");
    preco.textContent = `Preço na loja: ${item.preco} pokédollars`;

    detalhesEl.append(img, titulo, descricao, quantidade, preco);
}

function desenhar() {
    // se o filtro ficou sem itens volta para "todos"
    if (filtro !== "todos" && !itens.some((i) => i.categoria === filtro)) filtro = "todos";

    desenharAbas();
    desenharGrade();
    desenharDetalhes();
}

carregar();
