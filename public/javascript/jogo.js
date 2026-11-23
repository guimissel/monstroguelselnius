// Inicializacao e protecao contra saida da batalha.
const AVISO_SAIR = "Se você sair da batalha, você perde ela (conta como fuga e não ganha pokédollars). Quer sair mesmo?";
let saidaLiberada = false;   // true quando a saida ja foi confirmada ou o proprio jogo vai recarregar a pagina
let fugaEnviada = false;
let pilhaCriada = false;
let voltarProtegido = false;

// Informa se há uma partida cujo estado ainda precisa ser concluído.
function batalhaEmAndamento() {
    return !!estado && !estado.fim && (!!estado.idBatalha || estado.robo.equipe.pokemons.length === 3);
}

// fechar a aba, recarregar ou digitar outro endereco: aviso padrao do navegador (o texto nao e personalizavel)
window.addEventListener("beforeunload", (evento) => {
    if (saidaLiberada || !batalhaEmAndamento()) return;
    evento.preventDefault();
    evento.returnValue = "";
});

// a pagina esta mesmo saindo: fecha a batalha no servidor como fuga (sendBeacon continua depois que a pagina fecha)
window.addEventListener("pagehide", () => {
    if (fugaEnviada || !estado || estado.fim || !estado.idBatalha) return;
    fugaEnviada = true;

    const dados = new FormData();
    dados.append("resultado", "fuga");
    dados.append("vivos", 0);
    navigator.sendBeacon(`/monstroguelselnius/api/batalha/${estado.idBatalha}/finalizar`, dados);
});

// voltou pelo historico e a pagina veio pronta do cache: a batalha antiga ja foi perdida, comeca outra
window.addEventListener("pageshow", (evento) => {
    if (evento.persisted) location.reload();
});

// botao voltar do navegador: coloca uma entrada extra no historico para poder perguntar antes de sair.
// (o navegador so aceita isso depois de um clique ou tecla da pessoa, por isso espera a primeira interacao)
function protegerBotaoVoltar() {
    if (voltarProtegido) return;   // a revanche chama iniciar() de novo: so registra uma vez
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

        // batalha ja acabou: so continua voltando
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

    dizer("Carregando pokémons e inventário...", { rapido: true });

    let inventarioOk = true;

    // equipe do jogador (salva no banco)
    let idsJogador, idsBasicos;
    try {
        ({ idsJogador, idsBasicos } = await carregarEquipe());
    } catch (erro) {
        if (erro.message === "sessao expirada") return;
        console.error(erro);
        logTexto.innerHTML = "";
        dizer("Não consegui carregar a sua equipe. Confira se o app/database/schema.sql foi executado no banco.", { rapido: true });
        bloquearMenu(false);
        montarBotoes([{ texto: "Recarregar", onclick: () => { saidaLiberada = true; location.reload(); } }]);
        return;
    }

    // ainda nao escolheu a equipe: vai para a tela de escolha
    if (idsJogador.length !== 3) {
        location.href = URL_EQUIPE;
        return;
    }

    // o servidor abre a batalha e sorteia o time do robo (assim ele sabe quais pokemons voce ganha se vencer).
    // se o servidor falhar a batalha acontece igual, so que sem recompensas
    const batalha = await abrirBatalhaNoServidor();

    // sem login volta para o login; sem equipe vai escolher a equipe
    if (batalha && batalha.sair) return;
    if (batalha && batalha.semEquipe) {
        location.href = URL_EQUIPE;
        return;
    }
    estado.idBatalha = batalha ? batalha.id : null;

    try {
        const idsRobo = batalha ? batalha.robo : sortearIds(3, idsJogador, idsBasicos.length >= 6 ? idsBasicos : null);
        const nivelRobo = batalha ? batalha.nivel : NIVEL_ROBO;
        estado.robo.chefe = !!(batalha && batalha.chefe);
        palco.classList.toggle("chefe", estado.robo.chefe);   // cenario escuro do chefe

        // se so o inventario falhar (ex: banco sem as tabelas novas) o jogo continua, sem itens
        const [, , ...pokemons] = await Promise.all([
            carregarInventario().catch((erro) => {
                if (erro.message === "sessao expirada") throw erro;
                console.error(erro);
                inventarioOk = false;
            }),
            TabelaTipos.carregar(),
            ...idsJogador.map((id) => criarPokemon(id, NIVEL_JOGADOR)),
            ...idsRobo.map((id) => criarPokemon(id, nivelRobo, estado.robo.chefe))
        ]);

        estado.jogador.equipe = new Time(pokemons.slice(0, 3));
        estado.robo.equipe = new Time(pokemons.slice(3));

        await Promise.all(pokemons.flatMap((p) => [
            precarregarImagem(p.frente), precarregarImagem(p.costas),
            p.frenteAnim && precarregarImagem(p.frenteAnim),
            p.costasAnim && precarregarImagem(p.costasAnim)
        ]));
    } catch (erro) {
        console.error(erro);
        logTexto.innerHTML = "";
        dizer("Não consegui carregar os pokémons da PokeAPI. Verifique a internet.", { rapido: true });
        bloquearMenu(false);
        montarBotoes([{ texto: "Recarregar", onclick: () => { saidaLiberada = true; location.reload(); } }]);
        return;
    }

    logTexto.innerHTML = "";
    protegerBotaoVoltar();
    iniciarMusica(estado.robo.chefe);
    mostrarPokemon("robo");
    if (!inventarioOk) await dizer("Não consegui carregar o seu inventário, então você vai sem itens nesta batalha.");
    if (!estado.idBatalha) await dizer("Não consegui registrar a batalha. Rode o app/database/schema.sql no banco.");
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

    //  Repete o bloco enquanto (!estado.fim for verdadeiro.
    while (!estado.fim) {
        const acao = await estado.jogador.escolherAcao(estado);
        await executarRodada(acao);
    }
}

iniciar();
