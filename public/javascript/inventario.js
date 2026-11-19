const abasEl = document.getElementById("abas");
const gradeEl = document.getElementById("inventario");
const detalhesEl = document.getElementById("detalhes");
const mensagemEl = document.getElementById("mensagem");
const pokedolaresEl = document.getElementById("pokedolares");
const abrirTodasPokebolasEl = document.getElementById("abrirTodasPokebolas");
const aberturaEl = document.getElementById("aberturaPokebola");
const aberturaAnimacaoEl = document.getElementById("aberturaAnimacao");
const resultadoCapturaEl = document.getElementById("resultadoCaptura");
const resultadoImagemEl = document.getElementById("resultadoImagem");
const resultadoNomeEl = document.getElementById("resultadoNome");
const resultadoTipoEl = document.getElementById("resultadoTipo");
const fecharAberturaEl = document.getElementById("fecharAbertura");

// nomes das categorias que vem do banco (item_loja.categoria)
const NOMES_CATEGORIAS = {
    pocao_hp: "Poções de HP",
    pocao_condicao: "Poções de condição",
    evolucao: "Evolução",
    pokebola: "Pokébolas"
};

let itens = [];
let filtro = "todos";
let selecionado = null;   // chave do item aberto nos detalhes
let abrindoPokebola = false;
let aberturaEmLote = false;
let cancelarAberturaEmLote = false;

const caminhoImg = (arquivo) => "public/img/" + encodeURI(arquivo);

// Apresenta o resultado de uma ação mantendo o contexto do inventário.
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

// Cria as opções de categoria que filtram os itens exibidos.
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

// Renderiza os itens disponíveis na categoria selecionada.
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

// Mostra informações e ações do item atualmente selecionado.
function desenharDetalhes() {
    detalhesEl.innerHTML = "";
    const item = itens.find((i) => i.chave === selecionado);

    if (!item) {
        abrirTodasPokebolasEl.hidden = true;
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

    if (item.chave === "pokebola") {
        const abrir = document.createElement("button");
        abrir.type = "button";
        abrir.className = "botao abrir-pokebola";
        abrir.textContent = abrindoPokebola ? "Abrindo..." : "Abrir Pokébola";
        abrir.disabled = abrindoPokebola || item.quantidade < 1;
        abrir.addEventListener("click", abrirPokebola);
        detalhesEl.appendChild(abrir);

        abrirTodasPokebolasEl.hidden = false;
        abrirTodasPokebolasEl.textContent = abrindoPokebola
            ? "Abrindo todas..."
            : `Abrir todas (${item.quantidade})`;
        abrirTodasPokebolasEl.disabled = abrindoPokebola || item.quantidade < 1;
    } else {
        abrirTodasPokebolasEl.hidden = true;
    }
}

// Atualiza abas, grade e detalhes de acordo com o estado atual da tela.
function desenhar() {
    // se o filtro ficou sem itens volta para "todos"
    if (filtro !== "todos" && !itens.some((i) => i.categoria === filtro)) filtro = "todos";

    desenharAbas();
    desenharGrade();
    desenharDetalhes();
}

// Cria uma pausa assíncrona usada para sincronizar etapas visuais.
function esperar(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

async function abrirPokebola() {
    if (abrindoPokebola) return;
    await abrirUmaPokebola(false);
}

// Aguarda o término do áudio ou o limite de tempo para continuar a animação.
function esperarSom(som) {
    return new Promise((resolve) => {
        let terminou = false;
        const finalizar = () => {
            if (terminou) return;
            terminou = true;
            som.removeEventListener("ended", finalizar);
            som.removeEventListener("error", finalizar);
            resolve();
        };
        som.addEventListener("ended", finalizar, { once: true });
        som.addEventListener("error", finalizar, { once: true });
    });
}

async function contarAteAProximaAbertura() {
    const textoInicial = resultadoTipoEl.textContent;
    for (let segundos = 3; segundos > 0 && !cancelarAberturaEmLote; segundos--) {
        resultadoTipoEl.textContent = `Próxima abertura em ${segundos}s...`;
        await esperar(1000);
    }
    if (!cancelarAberturaEmLote) resultadoTipoEl.textContent = textoInicial;
}

async function abrirUmaPokebola(emLote) {
    abrindoPokebola = true;
    aberturaAnimacaoEl.hidden = false;
    resultadoCapturaEl.hidden = true;
    aberturaEl.showModal();
    if (emLote) {
        fecharAberturaEl.textContent = "Cancelar abertura em lote";
    } else {
        fecharAberturaEl.textContent = "Continuar";
    }
    desenharDetalhes();

    const inicioAbertura = performance.now();
    const somAbertura = new Audio("public/audio/abrindo.wav");
    somAbertura.currentTime = 0;
    somAbertura.play().catch((erro) => {
        if (erro.name !== "AbortError") console.warn("Não foi possível tocar o som de abertura:", erro);
    });

    try {
        const resposta = await fetch("/monstroguelselnius/api/inventario/pokebola/usar", { method: "POST" });
        const resultado = await resposta.json();
        if (resposta.status === 401) {
            window.location.href = "/monstroguelselnius/login";
            return;
        }
        if (!resposta.ok) throw new Error(resultado.mensagem || "Não foi possível abrir a Pokébola.");

        await esperar(Math.max(0, 6400 - (performance.now() - inicioAbertura)));
        somAbertura.pause();
        aberturaAnimacaoEl.hidden = true;
        resultadoCapturaEl.hidden = false;
        resultadoCapturaEl.className = "resultado-captura" + (resultado.pokemon.lendario ? " lendario" : "");
        resultadoImagemEl.src = `https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/${resultado.pokemon.id}.png`;
        resultadoImagemEl.alt = resultado.pokemon.nome;
        resultadoNomeEl.textContent = resultado.pokemon.nome;
        resultadoTipoEl.textContent = resultado.pokemon.jaTinha
            ? "Você já tinha este Pokémon."
            : resultado.pokemon.lendario ? "Pokémon lendário!" : "Pokémon encontrado!";
        const somPego = new Audio("public/audio/pego.mp3");
        const somPegoTerminou = esperarSom(somPego);
        
        if (resultado.pokemon.lendario) {
            const trovao = new Audio(
                Math.random() < 0.5
                    ? "public/audio/trovao1.mp3"
                    : "public/audio/trovao2.mp3"
            );
            trovao.play().catch((erro) => {
                console.warn("Não foi possível tocar o som de trovão:", erro);
            });
        }

        else {
            somPego.play().catch((erro) => {
                console.warn("Não foi possível tocar o som de captura:", erro);
                somPego.dispatchEvent(new Event("error"));
            });
        }

        selecionado = "pokebola";
        await carregar();
        if (resultado.pokemon.jaTinha) {
            mostrarMensagem(`Você encontrou ${resultado.pokemon.nome}, mas já tinha esse Pokémon na coleção.`);
        }

        await somPegoTerminou;
        return resultado.quantidade;
    } catch (erro) {
        console.error(erro);
        somAbertura.pause();
        aberturaEl.close();
        mostrarMensagem(erro.message || "Não foi possível abrir a Pokébola.", true);
        return 0;
    } finally {
        if (!emLote) abrindoPokebola = false;
        desenharDetalhes();
    }
}

async function abrirTodasPokebolas() {
    if (abrindoPokebola) return;

    const item = itens.find((i) => i.chave === "pokebola");
    if (!item || item.quantidade < 1) return;

    aberturaEmLote = true;
    cancelarAberturaEmLote = false;
    let quantidade = item.quantidade;

    while (quantidade > 0 && !cancelarAberturaEmLote) {
        const restante = await abrirUmaPokebola(true);
        if (cancelarAberturaEmLote) break;
        quantidade = typeof restante === "number" ? restante : quantidade - 1;

        if (quantidade > 0 && !cancelarAberturaEmLote) {
            await contarAteAProximaAbertura();
        }
    }

    aberturaEmLote = false;
    cancelarAberturaEmLote = false;
    fecharAberturaEl.textContent = "Continuar";
    abrindoPokebola = false;
    desenharDetalhes();
    if (aberturaEl.open) aberturaEl.close();
}

fecharAberturaEl.addEventListener("click", () => {
    if (aberturaEmLote) {
        cancelarAberturaEmLote = true;
        aberturaEmLote = false;
        abrindoPokebola = false;
    }
    aberturaEl.close();
});
abrirTodasPokebolasEl.addEventListener("click", abrirTodasPokebolas);
aberturaEl.addEventListener("click", (evento) => {
    if (evento.target === aberturaEl && !abrindoPokebola) aberturaEl.close();
});
aberturaEl.addEventListener("cancel", (evento) => {
    if (abrindoPokebola) evento.preventDefault();
});

carregar();
