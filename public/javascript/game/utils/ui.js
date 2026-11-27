// Interface, sprites, menus e escolhas do jogador.
function atualizarInfo(lado) {
    const mon = ativo(lado);
    const caixa = LADOS[lado].info;

    caixa.querySelector(".nome").textContent = mon.nome;
    caixa.querySelector(".nivel").textContent = "Nv" + mon.nivel;

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

// Remove ajustes temporários aplicados ao sprite antes de reutilizá-lo.
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

// Revela o sprite do combatente correspondente ao lado informado.
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

// Aplica uma animação de batalha e aguarda sua duração configurada.
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

// Evita novas escolhas enquanto uma ação de batalha está em andamento.
function bloquearMenu(sim) {
    menuEl.classList.toggle("bloqueado", sim);
}

// Exibe uma opção de retorno e associa a ação fornecida pelo fluxo atual.
function mostrarVoltar(acao) {
    voltarEl.hidden = !acao;
    voltarEl.onclick = acao || null;
}

// ===================== temporizador =====================

function iniciarTempo(aoAcabar) {
    pararTempo();
    let restante = CONFIG_BATALHA.tempoEscolha || TEMPO_ESCOLHA;
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

// Cancela o temporizador de decisão ativo, se houver.
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

// Monta uma opção de Pokémon com estado, ação e informação complementar.
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
        `${mon.nome}  Nv${mon.nivel}` + (mon.status ? `  [${STATUS[mon.status].sigla}]` : "");
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

// Verifica se os efeitos do item são compatíveis com o estado do alvo.
function itemServe(chave, mon) {
    const item = ITENS[chave];
    if (item.reviver) return mon.hp <= 0;
    if (mon.hp <= 0) return false;

    const curaHp = item.cura && mon.hp < mon.maxHp;
    const curaStatus = !!mon.status && (item.cura_status === "todos" ||
        (Array.isArray(item.cura_status) && item.cura_status.includes(mon.status)));
    return curaHp || curaStatus;
}

// Apresenta os itens utilizáveis e encaminha a escolha para a batalha.
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

// Permite selecionar o combatente que receberá o efeito do item.
function escolherAlvoItem(chave, resolverAcao) {
    abrirPainelTime({
        titulo: `Usar ${ITENS[chave].nome} em quem?`,
        aviso: "",
        validar: (mon) => (itemServe(chave, mon) ? false : "sem efeito"),
        aoEscolher: (i) => resolverAcao({ tipo: "item", item: chave, alvo: i }),
        aoVoltar: () => abrirPainelItens(resolverAcao)
    });
}

// Lista os substitutos possíveis e conduz a troca de Pokémon.
function abrirPainelTroca(resolverAcao) {
    const bloqueado = estado.cooldownTroca > 0;
    const roundsBloqueados = Math.max(1, (CONFIG_BATALHA.cooldownTroca || COOLDOWN_TROCA) - 1);
    abrirPainelTime({
        titulo: "Pokémons",
        aviso: bloqueado
            ? `Troca bloqueada por mais ${estado.cooldownTroca} round${estado.cooldownTroca > 1 ? "s" : ""}.`
            : `Trocar gasta o seu turno e bloqueia novas trocas por ${roundsBloqueados} round${roundsBloqueados > 1 ? "s" : ""}.`,
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

// Exibe os golpes disponíveis e encaminha o movimento escolhido.
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
