const abasEl = document.getElementById("abas");
const vitrineEl = document.getElementById("vitrine");
const mensagemEl = document.getElementById("mensagem");
const pokedolaresEl = document.getElementById("pokedolares");

// nomes das categorias que vem do banco (item_loja.categoria)
const NOMES_CATEGORIAS = {
    pocao_hp: "Poções de HP",
    pocao_condicao: "Poções de condição"
};

const MAXIMO_POR_COMPRA = 99;   // o servidor tambem confere

let itens = [];
let saldo = 0;
let filtro = "todos";
let comprando = false;            // evita duas compras ao mesmo tempo
const quantidades = {};           // chave do item -> quantidade escolhida

const caminhoImg = (arquivo) => "public/img/" + encodeURI(arquivo);

function mostrarMensagem(texto, tipo = "") {
    mensagemEl.textContent = texto;
    mensagemEl.className = "mensagem" + (tipo ? " " + tipo : "");
}

function atualizarSaldo() {
    pokedolaresEl.textContent = saldo;
}

async function carregar() {
    mostrarMensagem("Carregando a loja...");

    try {
        const resposta = await fetch("/monstroguelselnius/api/loja");

        // sessao expirou
        if (resposta.status === 401) {
            window.location.href = "/monstroguelselnius/login";
            return;
        }

        const dados = await resposta.json();
        if (!resposta.ok) throw new Error(dados.mensagem);

        itens = dados.itens;
        saldo = dados.pokedolares;
        atualizarSaldo();
        mostrarMensagem("");
        desenhar();
    } catch (erro) {
        console.error(erro);
        mostrarMensagem("Não consegui carregar a loja. Confira se o docs/banco.sql foi executado no banco e recarregue a página.", "erro");
    }
}

function desenharAbas() {
    abasEl.innerHTML = "";

    const categorias = ["todos", ...new Set(itens.map((i) => i.categoria))];
    if (categorias.length <= 2) return;

    for (const categoria of categorias) {
        const b = document.createElement("button");
        b.className = "botao claro" + (categoria === filtro ? " ativo" : "");
        b.textContent = categoria === "todos" ? "Todos" : (NOMES_CATEGORIAS[categoria] || categoria);
        b.addEventListener("click", () => {
            filtro = categoria;
            desenhar();
        });
        abasEl.appendChild(b);
    }
}

function criarProduto(item) {
    const quantidade = quantidades[item.chave] || 1;
    const total = item.preco * quantidade;
    const semDinheiro = total > saldo;

    const caixa = document.createElement("div");
    caixa.className = "produto";

    const img = document.createElement("img");
    img.src = caminhoImg(item.imagem);
    img.alt = item.nome;

    const nome = document.createElement("h2");
    nome.textContent = item.nome;

    const descricao = document.createElement("p");
    descricao.className = "descricao";
    descricao.textContent = item.descricao;

    const tem = document.createElement("p");
    tem.className = "tem";
    tem.textContent = `Você tem: ${item.quantidade}`;

    // preco total (fica vermelho quando o dinheiro nao da)
    const preco = document.createElement("div");
    preco.className = "preco" + (semDinheiro ? " caro" : "");
    const moeda = document.createElement("img");
    moeda.src = "public/img/pokedolar.gif";
    moeda.alt = "";
    const valor = document.createElement("span");
    valor.textContent = total;
    preco.append(moeda, valor);

    // escolher quantidade
    const seletor = document.createElement("div");
    seletor.className = "quantidade";
    const menos = document.createElement("button");
    menos.textContent = "-";
    menos.disabled = quantidade <= 1;
    menos.setAttribute("aria-label", "Diminuir quantidade");
    const numero = document.createElement("span");
    numero.textContent = quantidade;
    const mais = document.createElement("button");
    mais.textContent = "+";
    mais.disabled = quantidade >= MAXIMO_POR_COMPRA;
    mais.setAttribute("aria-label", "Aumentar quantidade");
    seletor.append(menos, numero, mais);

    menos.addEventListener("click", () => { quantidades[item.chave] = quantidade - 1; desenhar(); });
    mais.addEventListener("click", () => { quantidades[item.chave] = quantidade + 1; desenhar(); });

    const comprar = document.createElement("button");
    comprar.className = "botao";
    comprar.textContent = semDinheiro ? "Sem dinheiro" : "Comprar";
    comprar.disabled = semDinheiro || comprando;
    comprar.addEventListener("click", () => comprarItem(item, quantidade));

    caixa.append(img, nome, descricao, tem, preco, seletor, comprar);
    return caixa;
}

function desenharVitrine() {
    vitrineEl.innerHTML = "";
    itens
        .filter((i) => filtro === "todos" || i.categoria === filtro)
        .forEach((item) => vitrineEl.appendChild(criarProduto(item)));
}

function desenhar() {
    desenharAbas();
    desenharVitrine();
}

async function comprarItem(item, quantidade) {
    if (comprando) return;
    comprando = true;
    desenhar();

    try {
        const dados = new FormData();
        dados.append("quantidade", quantidade);

        const resposta = await fetch(`/monstroguelselnius/api/loja/${encodeURIComponent(item.chave)}/comprar`, {
            method: "POST",
            body: dados
        });

        if (resposta.status === 401) {
            window.location.href = "/monstroguelselnius/login";
            return;
        }

        const resultado = await resposta.json();

        if (!resposta.ok) {
            mostrarMensagem(resultado.mensagem || "Não foi possível comprar.", "erro");
        } else {
            // o servidor devolve o saldo e a quantidade certos
            saldo = resultado.pokedolares;
            item.quantidade = resultado.quantidade;
            quantidades[item.chave] = 1;
            atualizarSaldo();
            mostrarMensagem(`Você comprou ${quantidade}x ${item.nome}! Já está no seu inventário.`, "ok");
        }
    } catch (erro) {
        console.error(erro);
        mostrarMensagem("Não consegui falar com o servidor. Tente de novo.", "erro");
    } finally {
        comprando = false;
        desenhar();
    }
}

carregar();
