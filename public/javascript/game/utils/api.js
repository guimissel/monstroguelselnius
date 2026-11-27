// Utilitários, inventário, comunicação com o servidor da batalha e sprites da PokeAPI.
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

// Nome que aparece no log (o inimigo ganha o sufixo " inimigo")
function nomeLog(lado, mon) {
    return lado === "robo" ? `${mon.nome} inimigo` : mon.nome;
}

const caminhoImg = (arquivo) => "public/img/" + encodeURI(arquivo);

// ===================== inventario (banco de dados) =====================

async function carregarInventario() {
    const resposta = await fetch("/monstroguelselnius/api/inventario");

    // Sessão expirou: volta para o login
    if (resposta.status === 401) {
        location.href = URL_LOGIN;
        throw new Error("sessao expirada");
    }
    if (!resposta.ok) throw new Error("Erro ao carregar o inventario");

    const dados = await resposta.json();
    ITENS = {};
    estado.itens = {};

    for (const item of dados.itens) {
        // Pedra Evolutiva e Pokébola não são usadas dentro da batalha
        if (item.categoria === "evolucao" || item.categoria === "pokebola") continue;

        ITENS[item.chave] = {
            nome: item.nome,
            img: item.imagem,
            cura: item.efeito.curaHp === -1 ? "max" : item.efeito.curaHp,   // 0 = não cura HP
            cura_status: item.efeito.curaStatus,
            reviver: item.efeito.reviver
        };
        estado.itens[item.chave] = item.quantidade;
    }
}

// ===================== equipe (banco de dados) =====================

// IDs da equipe salva do jogador + IDs dos pokémons básicos
async function carregarEquipe() {
    const resposta = await fetch("/monstroguelselnius/api/equipe");

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

// ===================== batalha (validacao no servidor) =====================

// Mostra o erro técnico numa faixa vermelha no topo para facilitar diagnóstico
function mostrarErroTecnico(texto) {
    console.error(texto);

    const caixa = document.createElement("pre");
    caixa.textContent = "" + texto;
    caixa.style.cssText = "position:fixed;left:0;right:0;top:0;z-index:9999;margin:0;padding:10px 44px 10px 12px;" +
        "max-height:45vh;overflow:auto;white-space:pre-wrap;word-break:break-word;font:13px/1.4 monospace;" +
        "color:#fff;background:#a02010;user-select:text";

    const fechar = document.createElement("button");
    fechar.textContent = "X";
    fechar.style.cssText = "position:fixed;top:6px;right:8px;z-index:10000;cursor:pointer";
    fechar.addEventListener("click", () => { caixa.remove(); fechar.remove(); });

    document.body.append(caixa, fechar);
}

/**
 * Abre a batalha no servidor.
 * O servidor sorteia o time do robô, registra os monstros e golpes no banco,
 * e devolve as configurações da batalha e o estado inicial completo.
 */
async function abrirBatalhaNoServidor() {
    try {
        const resposta = await fetch("/monstroguelselnius/api/batalha/iniciar", { method: "POST" });

        if (resposta.status === 401) {
            location.href = URL_LOGIN;
            return { sair: true };
        }

        const texto = await resposta.text();
        let corpo;
        try {
            corpo = JSON.parse(texto);
        } catch (erro) {
            mostrarErroTecnico(`Abrir a batalha (HTTP ${resposta.status}). O servidor nao respondeu JSON:\n${texto.slice(0, 600)}`);
            return null;
        }

        // 409: o jogador ainda não escolheu a equipe
        if (resposta.status === 409) return { semEquipe: true };

        if (!resposta.ok) {
            mostrarErroTecnico(`Abrir a batalha (HTTP ${resposta.status}): ${corpo.mensagem}\nMotivo: ${corpo.detalhe || "sem detalhe"}`);
            return null;
        }

        if (!corpo.id || !Array.isArray(corpo.robo) || corpo.robo.length !== (corpo.chefe ? 1 : 3)) {
            mostrarErroTecnico("Abrir a batalha: resposta inesperada do servidor:\n" + texto.slice(0, 600));
            return null;
        }

        return {
            id: corpo.id,
            robo: corpo.robo,
            chefe: !!corpo.chefe,
            nivel: corpo.nivel,
            configuracoes: corpo.configuracoes || null,
            batalha: corpo.batalha || null
        };
    } catch (erro) {
        mostrarErroTecnico("Abrir a batalha: " + erro);
        return null;
    }
}

/**
 * Envia a ação escolhida pelo jogador para o backend processar o turno.
 * Toda a lógica de dano, status, precisão, crítico, IA do robô e vitória/derrota
 * é calculada no servidor e devolvida como uma lista de eventos + estado atualizado.
 *
 * @param {number} idBatalha ID da batalha aberta
 * @param {Object} acao      { tipo: 'golpe'|'trocar'|'trocar_forcado'|'item'|'fugir', ... }
 * @returns {Promise<Object>} { eventos, estado } ou { erro, sair? }
 */
async function enviarAcaoNoServidor(idBatalha, acao) {
    try {
        const resposta = await fetch(`/monstroguelselnius/api/batalha/${idBatalha}/turno`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(acao)
        });

        if (resposta.status === 401) {
            location.href = URL_LOGIN;
            return { sair: true, erro: "Sessão expirada." };
        }

        const texto = await resposta.text();
        let corpo;
        try {
            corpo = JSON.parse(texto);
        } catch (erro) {
            mostrarErroTecnico(`Processar turno (HTTP ${resposta.status}). Resposta não é JSON:\n${texto.slice(0, 600)}`);
            return { erro: "Erro de comunicação com o servidor." };
        }

        if (!resposta.ok) {
            if (corpo.detalhe) {
                mostrarErroTecnico(`Turno (HTTP ${resposta.status}): ${corpo.mensagem}\nMotivo: ${corpo.detalhe}`);
            }
            return { erro: corpo.mensagem || "Jogada recusada pelo servidor.", status: resposta.status };
        }

        return corpo;
    } catch (erro) {
        console.error(erro);
        return { erro: "Falha de rede ao enviar a jogada." };
    }
}

/**
 * Envia o encerramento explícito da batalha (usado em fuga ou fallback).
 */
async function fecharBatalhaNoServidor(resultado, vivos) {
    if (!estado.idBatalha) return { erro: "Não consegui registrar a batalha, então não há recompensa." };

    try {
        const dados = new FormData();
        dados.append("resultado", resultado);
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

// ===================== pokeapi (apenas sprites visuais) =====================

const cacheApi = new Map();

function buscar(url) {
    if (!cacheApi.has(url)) {
        const promessa = fetch(url).then((r) => {
            if (!r.ok) throw new Error(`Erro ${r.status} em ${url}`);
            return r.json();
        });
        promessa.catch(() => cacheApi.delete(url));
        cacheApi.set(url, promessa);
    }
    return cacheApi.get(url);
}

/**
 * Busca os sprites (parados e animados) do pokémon na PokeAPI.
 * Caso a API externa falhe ou esteja lenta, usa os caminhos diretos padrão de sprites
 * para não impedir a batalha que já está validada no servidor.
 */
async function carregarSpritesPokemon(id, chefe = false) {
    const baseRaw = "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon";
    const fallback = {
        frente: chefe ? `${baseRaw}/shiny/${id}.png` : `${baseRaw}/${id}.png`,
        costas: chefe ? `${baseRaw}/back/shiny/${id}.png` : `${baseRaw}/back/${id}.png`,
        frenteAnim: null,
        costasAnim: null
    };

    try {
        const dados = await buscar(`${API}/pokemon/${id}`);
        const animado = dados.sprites.versions?.["generation-v"]?.["black-white"]?.animated;

        return {
            frente: (chefe && dados.sprites.front_shiny) || dados.sprites.front_default || fallback.frente,
            costas: (chefe && dados.sprites.back_shiny) || dados.sprites.back_default || dados.sprites.front_default || fallback.costas,
            frenteAnim: (chefe && animado?.front_shiny) || animado?.front_default || null,
            costasAnim: (chefe && animado?.back_shiny) || animado?.back_default || null
        };
    } catch (erro) {
        console.warn(`PokeAPI indisponível para sprites do Pokémon #${id}, usando fallback.`, erro);
        return fallback;
    }
}

/**
 * Constrói uma instância de Pokemon no frontend usando os dados autoritativos
 * vindos do servidor (HP, atributos, tipos, status e golpes) + sprites visuais.
 */
async function criarPokemonDoServidor(dadosServidor, chefe = false) {
    const sprites = await carregarSpritesPokemon(dadosServidor.id, chefe);

    return new Pokemon({
        id: dadosServidor.id,
        nome: dadosServidor.nome,
        nivel: dadosServidor.nivel,
        tipos: dadosServidor.tipos,
        maxHp: dadosServidor.maxHp,
        hp: dadosServidor.hp,
        atk: dadosServidor.atk,
        def: dadosServidor.def,
        spa: dadosServidor.spa,
        spd: dadosServidor.spd,
        spe: dadosServidor.spe,
        golpes: dadosServidor.golpes,
        status: dadosServidor.status || null,
        sono: 0,
        is_ativo: !!dadosServidor.is_ativo,
        frente: sprites.frente,
        costas: sprites.costas,
        frenteAnim: sprites.frenteAnim,
        costasAnim: sprites.costasAnim
    });
}

// Precarrega uma imagem antes de usá-la para evitar atrasos durante a batalha.
function precarregarImagem(url) {
    if (!url) return Promise.resolve();
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = img.onerror = () => resolve();
        img.src = url;
    });
}
