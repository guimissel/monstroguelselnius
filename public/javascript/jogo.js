// Inicialização da tela de batalha e proteção contra saída acidental.
const AVISO_SAIR = "Se você sair da batalha, você perde ela (conta como fuga e não ganha pokédollars). Quer sair mesmo?";
let saidaLiberada = false;   // true quando a saída já foi confirmada ou o próprio jogo vai recarregar
let fugaEnviada = false;
let pilhaCriada = false;
let voltarProtegido = false;

// Informa se há uma partida em aberto que precisa ser concluída.
function batalhaEmAndamento() {
    return !!estado && !estado.fim && !!estado.idBatalha;
}

// Fechar a aba, recarregar ou digitar outro endereço: exibe confirmação padrão do navegador
window.addEventListener("beforeunload", (evento) => {
    if (saidaLiberada || !batalhaEmAndamento()) return;
    evento.preventDefault();
    evento.returnValue = "";
});

// Página fechando de fato: registra fuga no servidor via sendBeacon
window.addEventListener("pagehide", () => {
    if (fugaEnviada || !estado || estado.fim || !estado.idBatalha) return;
    fugaEnviada = true;

    const dados = new FormData();
    dados.append("resultado", "fuga");
    dados.append("vivos", 0);
    navigator.sendBeacon(`/monstroguelselnius/api/batalha/${estado.idBatalha}/finalizar`, dados);
});

// Voltou pelo histórico em cache (bfcache): recarrega para iniciar nova partida limpa
window.addEventListener("pageshow", (evento) => {
    if (evento.persisted) location.reload();
});

// Protege o botão Voltar do navegador inserindo entrada extra no histórico após interação
function protegerBotaoVoltar() {
    if (voltarProtegido) return;
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

    dizer("Carregando pokémons, regras e inventário...", { rapido: true });

    let inventarioOk = true;

    // 1. Confere se o jogador tem equipe salva de 3 pokémons
    let idsJogador;
    try {
        ({ idsJogador } = await carregarEquipe());
    } catch (erro) {
        if (erro.message === "sessao expirada") return;
        console.error(erro);
        logTexto.innerHTML = "";
        dizer("Não consegui carregar a sua equipe. Confira se o app/database/schema.sql foi executado no banco.", { rapido: true });
        bloquearMenu(false);
        montarBotoes([{ texto: "Recarregar", onclick: () => { saidaLiberada = true; location.reload(); } }]);
        return;
    }

    if (idsJogador.length !== 3) {
        location.href = URL_EQUIPE;
        return;
    }

    // 2. O servidor inicia a batalha, sorteia o robô, define golpes/atributos e envia as configurações
    const batalha = await abrirBatalhaNoServidor();

    if (batalha && batalha.sair) return;
    if (batalha && batalha.semEquipe) {
        location.href = URL_EQUIPE;
        return;
    }

    if (!batalha || !batalha.id || !batalha.batalha) {
        logTexto.innerHTML = "";
        dizer("Não consegui iniciar a batalha validada no servidor. Verifique o banco de dados e tente novamente.", { rapido: true });
        bloquearMenu(false);
        montarBotoes([
            { texto: "Recarregar", onclick: () => { saidaLiberada = true; location.reload(); } },
            { texto: "Início", onclick: () => { saidaLiberada = true; location.href = URL_HOME; } }
        ]);
        return;
    }

    // 3. Aplica as configurações da batalha vindas do backend (níveis, tempo de escolha, cooldown)
    aplicarConfiguracoesBatalha(batalha.configuracoes);
    estado.config = CONFIG_BATALHA;
    estado.idBatalha = batalha.id;
    estado.robo.chefe = !!batalha.chefe;
    estado.rodada = batalha.batalha.turno || 1;
    estado.cooldownTroca = batalha.batalha.cooldownTroca || 0;
    palco.classList.toggle("chefe", estado.robo.chefe);

    // 4. Carrega inventário e cria os objetos Pokemon usando os dados exatos do servidor + sprites
    try {
        const dadosServidor = batalha.batalha;
        const [, ...pokemons] = await Promise.all([
            carregarInventario().catch((erro) => {
                if (erro.message === "sessao expirada") throw erro;
                console.error(erro);
                inventarioOk = false;
            }),
            ...dadosServidor.jogador.map((p) => criarPokemonDoServidor(p, false)),
            ...dadosServidor.robo.map((p) => criarPokemonDoServidor(p, estado.robo.chefe))
        ]);

        const qtdJogador = dadosServidor.jogador.length;
        estado.jogador.equipe = new Time(pokemons.slice(0, qtdJogador));
        estado.robo.equipe = new Time(pokemons.slice(qtdJogador));

        // Define índice ativo inicial conforme retornado pelo servidor
        const idxJogAtivo = dadosServidor.jogador.findIndex((p) => p.is_ativo);
        const idxRoboAtivo = dadosServidor.robo.findIndex((p) => p.is_ativo);
        if (idxJogAtivo >= 0) estado.jogador.equipe.indiceAtivo = idxJogAtivo;
        if (idxRoboAtivo >= 0) estado.robo.equipe.indiceAtivo = idxRoboAtivo;

        await Promise.all(pokemons.flatMap((p) => [
            precarregarImagem(p.frente), precarregarImagem(p.costas),
            precarregarImagem(p.frenteAnim), precarregarImagem(p.costasAnim)
        ]));
    } catch (erro) {
        console.error(erro);
        logTexto.innerHTML = "";
        dizer("Não consegui preparar os recursos visuais da batalha.", { rapido: true });
        bloquearMenu(false);
        montarBotoes([{ texto: "Recarregar", onclick: () => { saidaLiberada = true; location.reload(); } }]);
        return;
    }

    // 5. Inicia apresentação da arena e entra no loop de turnos validados no servidor
    logTexto.innerHTML = "";
    protegerBotaoVoltar();
    iniciarMusica(estado.robo.chefe);
    mostrarPokemon("robo");
    if (!inventarioOk) await dizer("Não consegui carregar o seu inventário, então você vai sem itens nesta batalha.");
    if (estado.robo.chefe) {
        await dizer("Atenção! O chefe da Equipe Robô apareceu para te desafiar!");
        await dizer(`Ele enviou ${ativo("robo").nome}, um pokémon brilhante e muito forte!`);
    } else {
        await dizer("Um treinador robô quer batalhar!");
        await dizer(`Ele enviou ${ativo("robo").nome}!`);
    }
    mostrarPokemon("jogador");
    await dizer(`Vai, ${ativo("jogador").nome}!`);
    await dizer("Atenção: se você sair da tela de batalha, você perde a batalha!");

    while (!estado.fim) {
        const acao = await estado.jogador.escolherAcao(estado);
        await executarRodada(acao);
    }
}

iniciar();
