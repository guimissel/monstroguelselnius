const URL_LOGIN = "/monstroguelselnius/login";

const NOMES_TIPOS = {
    normal: "Normal", fire: "Fogo", water: "Água", electric: "Elétrico",
    grass: "Planta", ice: "Gelo", fighting: "Lutador", poison: "Veneno",
    ground: "Terra", flying: "Voador", psychic: "Psíquico", bug: "Inseto",
    rock: "Pedra", ghost: "Fantasma", dragon: "Dragão", dark: "Sombrio",
    steel: "Aço", fairy: "Fada"
};

const mensagemEl = document.getElementById("mensagem");
const mensagemFormEl = document.getElementById("mensagemForm");
const mensagemProgressoEl = document.getElementById("mensagemProgresso");

let catalogo = {};
let meuId = null;

function mostrar(el, texto, tipo = "") {
    el.textContent = texto;
    el.className = "mensagem" + (tipo ? " " + tipo : "");
}

function criar(tag, classe, texto) {
    const el = document.createElement(tag);
    if (classe) el.className = classe;
    if (texto !== undefined) el.textContent = texto;
    return el;
}

function carta(pokemon, extra) {
    const c = criar("div", "carta");
    const img = criar("img");
    img.src = `https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/${pokemon.id}.png`;
    img.alt = "";
    c.append(img, criar("span", "", pokemon.nome));

    const tipos = criar("span", "tipos");
    for (const t of catalogo[pokemon.id]?.tipos || []) tipos.appendChild(criar("span", "tipo", NOMES_TIPOS[t] || t));
    c.appendChild(tipos);

    if (extra) c.appendChild(criar("span", "na-equipe", extra));
    return c;
}

function tabela(colunas, linhas) {
    const t = criar("table");
    const cab = criar("tr");
    colunas.forEach((nome) => cab.appendChild(criar("th", "", nome)));
    t.appendChild(cab);
    for (const linha of linhas) {
        const tr = criar("tr");
        linha.forEach((celula) => {
            const td = criar("td", celula.classe || "", celula.texto ?? celula);
            tr.appendChild(td);
        });
        t.appendChild(tr);
    }
    return t;
}

const ROTULOS = { vitoria: "Vitória", derrota: "Derrota", fuga: "Fuga", em_andamento: "Em andamento" };

function desenhar(d) {
    meuId = d.treinador.id;
    document.getElementById("nome").textContent = d.treinador.nome;
    document.getElementById("dinheiro").textContent = d.treinador.pokedolares;
    document.getElementById("posicao").textContent = d.placar.batalhas > 0 ? `${d.posicao}º lugar` : "ainda sem batalhas";
    document.title = `Perfil - ${d.treinador.nome}`;
    document.getElementById("chefe").textContent = d.paraChefe === 0
        ? "O próximo adversário é o chefe!"
        : `Chefe em ${d.paraChefe} ${d.paraChefe === 1 ? "batalha" : "batalhas"}`;

    const placar = document.getElementById("placar");
    placar.innerHTML = "";
    [["Batalhas", d.placar.batalhas], ["Vitórias", d.placar.vitorias], ["Derrotas", d.placar.derrotas],
     ["Fugas", d.placar.fugas], ["Pokédollars ganhos", d.placar.totalGanho]].forEach(([nome, valor]) => {
        const caixa = criar("div");
        caixa.append(criar("span", "", valor), criar("small", "", nome));
        placar.appendChild(caixa);
    });

    const equipe = document.getElementById("equipe");
    equipe.innerHTML = "";
    for (const p of d.treinador.equipe.pokemons) equipe.appendChild(carta(p));

    const nota = [];
    if (d.treinador.equipe.pokemons.length) {
        nota.push(`Poder total: ${d.treinador.equipe.poderTotal}`);
        if (d.treinador.equipe.maisRapido) nota.push(`mais rápido: ${d.treinador.equipe.maisRapido}`);
        const fracos = d.treinador.equipe.fraquezasEmComum.map((t) => NOMES_TIPOS[t] || t);
        nota.push(fracos.length ? `golpes que ameaçam mais de um da equipe: ${fracos.join(", ")}` : "nenhuma fraqueza em comum");
    } else {
        nota.push("Você ainda não escolheu a equipe.");
    }
    document.getElementById("notaEquipe").textContent = nota.join(" | ");

    const colecao = document.getElementById("colecao");
    colecao.innerHTML = "";
    for (const p of d.colecao) colecao.appendChild(carta(p, p.naEquipe ? "na equipe" : ""));

    const historico = document.getElementById("historico");
    historico.innerHTML = "";
    if (d.historico.length === 0) {
        historico.appendChild(criar("p", "nota", "Nenhuma batalha ainda."));
    } else {
        historico.appendChild(tabela(["#", "Contra", "Resultado", "Ganhou", "Quando"], d.historico.map((b) => [
            b.id, b.adversario,
            { texto: ROTULOS[b.status] || b.status, classe: b.status === "vitoria" ? "vitoria" : b.status === "derrota" ? "derrota" : "" },
            b.recompensa, b.iniciadaEm
        ])));
    }

    const ranking = document.getElementById("ranking");
    ranking.innerHTML = "";
    if (d.ranking.length === 0) {
        ranking.appendChild(criar("p", "nota", "Ninguém batalhou ainda."));
    } else {
        ranking.appendChild(tabela(["Treinador", "Vitórias", "Batalhas", "Ganhou"], d.ranking.map((r) => [r.nome, r.vitorias, r.batalhas, r.totalGanho])));
    }
}

async function carregar() {
    try {
        const respostaCatalogo = await fetch("/monstroguelselnius/api/pokemon");
        if (respostaCatalogo.ok) {
            catalogo = {};
            for (const p of (await respostaCatalogo.json()).pokemons) catalogo[p.id] = p;
        }

        const resposta = await fetch("/monstroguelselnius/api/perfil");
        if (resposta.status === 401) {
            window.location.href = URL_LOGIN;
            return;
        }
        const dados = await resposta.json();
        if (!resposta.ok) throw new Error(dados.mensagem);
        desenhar(dados);
    } catch (erro) {
        console.error(erro);
        mostrar(mensagemEl, erro.message || "Não consegui carregar o perfil.", "erro");
    }
}

// trocar de perfil: sai da conta e vai para o login para entrar com outra
document.getElementById("trocar").addEventListener("click", async () => {
    await fetch("/monstroguelselnius/api/usuario/sair", { method: "POST" });
    window.location.href = URL_LOGIN;
});

document.getElementById("excluir").addEventListener("click", async () => {
    if (!confirm("Excluir a sua conta? Isso apaga seus pokémons, itens e batalhas, e não tem volta.")) return;

    const resposta = await fetch(`/monstroguelselnius/api/usuario/${meuId}/excluir`, { method: "DELETE" });
    if (resposta.ok) {
        window.location.href = URL_LOGIN;
    } else {
        const dados = await resposta.json().catch(() => ({}));
        mostrar(mensagemEl, dados.mensagem || "Não consegui excluir a conta.", "erro");
    }
});

// carregar progresso: troca o dinheiro, a equipe, a colecao e os itens da conta pelos do arquivo
const arquivoEl = document.getElementById("arquivo");
document.getElementById("escolherArquivo").addEventListener("click", () => arquivoEl.click());

arquivoEl.addEventListener("change", async () => {
    const arquivo = arquivoEl.files[0];
    arquivoEl.value = "";
    if (!arquivo) return;

    if (!confirm("Carregar esse progresso? O dinheiro, a equipe, a coleção e os itens desta conta serão trocados pelos do arquivo.")) return;

    const dados = new FormData();
    dados.append("arquivo", arquivo);

    try {
        const resposta = await fetch("/monstroguelselnius/api/progresso/importar", { method: "POST", body: dados });
        const resultado = await resposta.json();

        if (!resposta.ok) {
            mostrar(mensagemProgressoEl, resultado.mensagem || "Não foi possível carregar o arquivo.", "erro");
            return;
        }

        const r = resultado.resumo;
        mostrar(mensagemProgressoEl, `Progresso carregado: ${r.pokemons} pokémons, ${r.itens} itens e ${r.pokedolares} pokédollars.`, "ok");
        carregar();
    } catch (erro) {
        console.error(erro);
        mostrar(mensagemProgressoEl, "Não consegui falar com o servidor.", "erro");
    }
});

document.getElementById("formulario").addEventListener("submit", async (evento) => {
    evento.preventDefault();

    const dados = new FormData();
    dados.append("nome", document.getElementById("novoNome").value);
    dados.append("senha_nova", document.getElementById("novaSenha").value);
    dados.append("senha_atual", document.getElementById("senhaAtual").value);

    try {
        const resposta = await fetch("/monstroguelselnius/api/usuario/atualizar", { method: "POST", body: dados });
        const resultado = await resposta.json();

        if (!resposta.ok) {
            mostrar(mensagemFormEl, resultado.mensagem || "Não foi possível salvar.", "erro");
            return;
        }

        mostrar(mensagemFormEl, "Perfil atualizado!", "ok");
        document.getElementById("formulario").reset();
        carregar();
    } catch (erro) {
        console.error(erro);
        mostrar(mensagemFormEl, "Não consegui falar com o servidor.", "erro");
    }
});

carregar();
