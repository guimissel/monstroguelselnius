// Utilitarios, inventario, servidor e integracao com PokeAPI.
const ativo = (lado) => estado[lado].equipe.ativo;
const outro = (lado) => (lado === "jogador" ? "robo" : "jogador");

// ===================== utilitarios =====================

const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

// Randomiza a ordem dos elementos sem criar uma lista paralela.
function embaralhar(lista) {
    for (let i = lista.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [lista[i], lista[j]] = [lista[j], lista[i]];
    }
    return lista;
}

// Converte nomes técnicos da API para a apresentação usada no jogo.
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
        if (item.categoria === "evolucao" || item.categoria === "pokebola") continue;

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

        // robo normal: 3 pokemons. chefe: 1 so
        if (!corpo.id || !Array.isArray(corpo.robo) || corpo.robo.length !== (corpo.chefe ? 1 : 3)) {
            mostrarErroTecnico("Abrir a batalha: resposta inesperada do servidor:\n" + texto.slice(0, 600));
            return null;
        }
        return {
            id: corpo.id,
            robo: corpo.robo,
            chefe: !!corpo.chefe,
            nivel: corpo.nivel
        };
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

// Centraliza chamadas HTTP e o tratamento comum das respostas da API.
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

async function escolherGolpes(dados, tipos, nivel, poderMax = Infinity) {
    const candidatos = dados.moves
        .filter((m) => !GOLPES_PROIBIDOS.has(m.move.name) && m.version_group_details.some(
            (v) => v.move_learn_method.name === "level-up" && v.level_learned_at <= nivel))
        .map((m) => m.move.url);

    embaralhar(candidatos);

    const respostas = await Promise.all(
        candidatos.slice(0, 16).map((url) => buscar(url).catch(() => null))
    );
    const validos = respostas.filter(Boolean).map(normalizarGolpe).filter(Boolean)
        .filter((g) => !g.poder || g.poder <= poderMax);

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

// chefe = versao brilhante ("shiny", cores diferentes) e com golpes limitados em PODER_MAX_CHEFE
async function criarPokemon(id, nivel, chefe = false) {
    const dados = await buscar(`${API}/pokemon/${id}`);

    const base = {};
    dados.stats.forEach((s) => (base[s.stat.name] = s.base_stat));

    const animado = dados.sprites.versions?.["generation-v"]?.["black-white"]?.animated;

    const stat = (b) => Math.floor((2 * b * nivel) / 100) + 5;
    const tipos = dados.types.map((t) => t.type.name);
    const maxHp = Math.floor((2 * base.hp * nivel) / 100) + nivel + 10;

    return new Pokemon({
        id,
        nome: formatarNome(dados.name),
        nivel,
        tipos,
        maxHp,
        hp: maxHp,
        atk: stat(base.attack),
        def: stat(base.defense),
        spa: stat(base["special-attack"]),
        spd: stat(base["special-defense"]),
        spe: stat(base.speed),
        golpes: await escolherGolpes(dados, tipos, nivel, chefe ? PODER_MAX_CHEFE : Infinity),
        status: null,
        sono: 0,
        frente: (chefe && dados.sprites.front_shiny) || dados.sprites.front_default,
        costas: (chefe && dados.sprites.back_shiny) || dados.sprites.back_default || dados.sprites.front_default,
        // sprites animados (GIF). Nem todo pokemon tem; sem eles vale a imagem parada
        frenteAnim: (chefe && animado?.front_shiny) || animado?.front_default || null,
        costasAnim: (chefe && animado?.back_shiny) || animado?.back_default || null
    });
}

// sorteia ids sem repetir. "lista" e de onde sortear (sem ela, vale a 1a geracao inteira)
function sortearIds(qtd, proibidos = [], lista = null) {
    const candidatos = lista || Array.from({ length: MAX_POKEMON_ID }, (_, i) => i + 1);
    return embaralhar(candidatos.filter((id) => !proibidos.includes(id))).slice(0, qtd);
}

// Carrega uma imagem antes de usá-la para evitar atrasos durante a batalha.
function precarregarImagem(url) {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = img.onerror = () => resolve();
        img.src = url;
    });
}

// ===================== log =====================
