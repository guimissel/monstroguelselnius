// Regras, status, rodada e encerramento da batalha.
async function podeAgir(lado, mon) {
    const nome = nomeLog(lado, mon);

    if (mon.status === "sleep") {
        if (mon.sono > 0) {
            mon.sono--;
            await dizer(`${nome} está dormindo...`);
            return false;
        }
        mon.status = null;
        atualizarInfo(lado);
        await dizer(`${nome} acordou!`);
    }

    if (mon.status === "freeze") {
        if (Math.random() < 0.2) {
            mon.status = null;
            atualizarInfo(lado);
            await dizer(`${nome} descongelou!`);
        } else {
            await dizer(`${nome} está congelado e não consegue se mover!`);
            return false;
        }
    }

    if (mon.status === "paralysis" && Math.random() < 0.25) {
        await dizer(`${nome} está paralisado! Não consegue se mover!`);
        return false;
    }

    return true;
}

async function aplicarStatus(lado, alvo, status, avisarFalha) {
    const nome = nomeLog(lado, alvo);

    if (alvo.status || alvo.tipos.some((t) => IMUNE_STATUS[status].includes(t))) {
        if (avisarFalha) await dizer(alvo.status ? "Mas falhou!" : `Não afeta ${nome}...`);
        return;
    }

    alvo.status = status;
    if (status === "sleep") alvo.sono = 1 + Math.floor(Math.random() * 3);
    atualizarInfo(lado);
    await dizer(`${nome} ${STATUS[status].frase}`);
}

async function causarDano(lado, mon, dano) {
    mon.receberDano(dano);
    atualizarInfo(lado);
    animar(lado, "dano", 600);
    await esperar(850);
}

async function atacar(lado, golpe) {
    const alvoLado = outro(lado);
    const atq = ativo(lado);
    const def = ativo(alvoLado);

    if (!(await podeAgir(lado, atq))) return;

    await dizer(`${nomeLog(lado, atq)} usou ${golpe.nome}!`);
    animar(lado, lado === "jogador" ? "ataqueJogador" : "ataqueRobo", 350);
    await esperar(350);

    if (golpe.precisao && Math.random() * 100 >= golpe.precisao) {
        await dizer("Mas errou!");
        return;
    }

    if (!golpe.poder) {
        // golpe que so causa status
        await aplicarStatus(alvoLado, def, golpe.status, true);
        return;
    }

    const r = atq.calcularDanoContra(def, golpe);

    if (r.ef === 0) {
        await dizer(`Não afeta ${nomeLog(alvoLado, def)}...`);
        return;
    }

    await causarDano(alvoLado, def, r.dano);

    if (r.critico) await dizer("Foi um acerto crítico!");
    if (r.ef > 1) await dizer(r.ef >= 4 ? "É supereficaz! (4x)" : "É supereficaz!");
    if (r.ef < 1) await dizer(r.ef <= 0.25 ? "É pouco eficaz... (0.25x)" : "É pouco eficaz...");

    // drenagem (cura) ou recuo
    if (golpe.drenagem && r.dano > 0) {
        const valor = Math.floor(r.dano * Math.abs(golpe.drenagem) / 100);
        if (golpe.drenagem > 0 && valor > 0) {
            atq.curar(valor);
            atualizarInfo(lado);
            await dizer(`${nomeLog(lado, atq)} recuperou HP!`);
        } else if (golpe.drenagem < 0 && valor > 0) {
            await causarDano(lado, atq, valor);
            await dizer(`${nomeLog(lado, atq)} se machucou com o recuo!`);
        }
    }

    if (!def.estaDesmaiado() && golpe.status && Math.random() * 100 < golpe.chanceStatus) {
        await aplicarStatus(alvoLado, def, golpe.status, false);
    }

    if (def.estaDesmaiado()) await processarDesmaio(alvoLado);
    if (!estado.fim && atq.estaDesmaiado()) await processarDesmaio(lado);
}

// pokemon desmaiou: o robo manda o proximo, o jogador escolhe. sem pokemons = fim
async function processarDesmaio(lado) {
    const mon = ativo(lado);
    LADOS[lado].sprite.classList.add("desmaiou");
    LADOS[lado].sombra.classList.add("sumiu");
    await dizer(`${nomeLog(lado, mon)} desmaiou!`);

    const treinador = estado[lado];

    if (treinador.equipe.proximoVivo() < 0) {
        await terminar(lado === "robo");
        return;
    }

    // cada treinador escolhe do seu jeito (o jogador na tela, o robo sozinho)
    treinador.equipe.indiceAtivo = await treinador.escolherSubstituto();
    await dizer(treinador.mensagemEntrada(treinador.equipe.ativo));
    mostrarPokemon(lado);
}

async function trocarJogador(indice) {
    await dizer(`Volte, ${ativo("jogador").nome}!`);
    LADOS.jogador.sprite.classList.add("desmaiou");
    LADOS.jogador.sombra.classList.add("sumiu");
    await esperar(400);
    estado.jogador.equipe.indiceAtivo = indice;
    await dizer(estado.jogador.mensagemEntrada(ativo("jogador")));
    mostrarPokemon("jogador");
    await esperar(300);
}

async function usarItem({ item: chave, alvo }) {
    const item = ITENS[chave];
    const mon = estado.jogador.equipe.pokemons[alvo];

    // so aplica o efeito se o banco confirmar que o jogador ainda tem o item
    if (!(await gastarItemNoBanco(chave))) {
        await dizer("Você não tem mais esse item!");
        estado.itens[chave] = 0;
        return;
    }
    estado.itens[chave]--;

    await dizer(`Você usou ${item.nome} em ${mon.nome}!`);

    if (item.reviver) {
        mon.reviver();
    } else {
        if (item.cura) mon.curar(item.cura === "max" ? mon.maxHp : item.cura);
        if (item.cura_status === "todos" || (item.cura_status && item.cura_status.includes(mon.status))) {
            mon.limparStatus();
        }
    }

    if (alvo === estado.jogador.equipe.indiceAtivo) atualizarInfo("jogador");
    await esperar(800);
    await dizer(item.reviver ? `${mon.nome} voltou à batalha!` : `${mon.nome} está melhor!`);
}

async function executarRodada(acao) {
    if (acao.tipo === "fugir") {
        await dizer("Você fugiu da batalha!");
        await terminar(false, true);
        return;
    }

    const jogador = ativo("jogador");
    const robo = ativo("robo");
    const golpeRobo = robo.golpes[estado.robo.escolherAcao(estado).indice];

    if (acao.tipo === "trocar") {
        await trocarJogador(acao.indice);
        estado.cooldownTroca = COOLDOWN_TROCA;
        await atacar("robo", golpeRobo);
    } else if (acao.tipo === "item") {
        await usarItem(acao);
        await atacar("robo", golpeRobo);
    } else {
        const golpeJogador = jogador.golpes[acao.indice];
        const ordem = [
            { lado: "jogador", mon: jogador, golpe: golpeJogador },
            { lado: "robo", mon: robo, golpe: golpeRobo }
        ];

        // prioridade do golpe, depois velocidade, empate sorteia
        ordem.sort((a, b) =>
            (b.golpe.prioridade - a.golpe.prioridade) ||
            (b.mon.velocidadeEfetiva - a.mon.velocidadeEfetiva) ||
            (Math.random() - 0.5));

        for (const { lado, mon, golpe } of ordem) {
            if (estado.fim) break;
            // se o pokemon desmaiou ou foi trocado antes de agir, perde o turno
            if (ativo(lado) !== mon || mon.estaDesmaiado()) continue;
            await atacar(lado, golpe);
        }
    }

    if (!estado.fim) await fimDoRound();
}

async function fimDoRound() {
    for (const lado of ["jogador", "robo"]) {
        const mon = ativo(lado);
        if (estado.fim || mon.estaDesmaiado()) continue;
        if (mon.status !== "poison" && mon.status !== "burn") continue;

        await dizer(`${nomeLog(lado, mon)} sofre com ${mon.status === "poison" ? "o veneno" : "a queimadura"}!`);
        await causarDano(lado, mon, Math.max(1, Math.floor(mon.maxHp / 8)));
        if (mon.estaDesmaiado()) await processarDesmaio(lado);
    }

    if (estado.fim) return;
    estado.rodada++;
    if (estado.cooldownTroca > 0) estado.cooldownTroca--;
}

// ===================== fim de jogo =====================

// conta quais pokemons do robo entraram na colecao
async function anunciarConquistas(pokemons) {
    const nomes = (lista) => lista.map((p) => p.nome).join(", ").replace(/, ([^,]*)$/, " e $1");
    const novos = pokemons.filter((p) => p.novo);
    const repetidos = pokemons.filter((p) => !p.novo);

    if (novos.length) await dizer(`Você conquistou ${nomes(novos)}! Eles já estão na sua coleção.`);
    if (repetidos.length) await dizer(`Você já tinha ${nomes(repetidos)}.`);
}

async function terminar(vitoria, fugiu = false) {
    estado.fim = true;
    pararTempo();
    fecharPainel();

    const resultado = fugiu ? "fuga" : (vitoria ? "vitoria" : "derrota");
    const vivos = estado.jogador.equipe.vivos().length;

    // acabou o suspense: some o trovao e volta a musica de sempre
    if (estado.robo.chefe) iniciarMusica(false);

    if (vitoria) await dizer(estado.robo.chefe ? "Você derrotou o chefe!" : "Você venceu a batalha!");
    else if (!fugiu) await dizer("Todos os seus pokémons desmaiaram...");

    const resposta = await fecharBatalhaNoServidor(resultado, vivos);

    if (resposta.erro) await dizer(resposta.erro);
    else if (resposta.recompensa > 0) await dizer(`Você ganhou ${resposta.recompensa} pokédollars! Agora você tem ${resposta.pokedolares}.`);
    else await dizer("Você não ganhou pokédollars.");

    if (vitoria && !resposta.erro) await anunciarConquistas(resposta.pokemons || []);

    bloquearMenu(false);
    mostrarVoltar(null);
    montarBotoes([
        // revanche sem recarregar a pagina (recarregar acumularia entradas no historico do botao voltar)
        { texto: "Revanche", onclick: () => { fugaEnviada = false; saidaLiberada = false; iniciar(); } },
        { texto: "Equipe", onclick: () => (location.href = URL_EQUIPE) },
        { texto: "Loja", onclick: () => (location.href = URL_LOJA) },
        { texto: "Início", onclick: () => (location.href = URL_HOME) }
    ]);
}

// ===================== sair da batalha =====================

// Sair no meio da batalha (voltar, fechar a aba, recarregar, digitar outro endereco) faz perder:
// conta como fuga e nao paga pokedollars. O navegador pergunta antes, e se a pessoa sair mesmo
// a batalha e fechada no servidor como fuga.
