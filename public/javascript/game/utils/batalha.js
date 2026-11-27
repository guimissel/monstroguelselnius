// Execução dos turnos da batalha integrados com a validação no backend e as configurações da partida.
//
// IMPORTANTE:
//   O frontend NÃO calcula mais dano, chance de acerto, crítico, efetividade,
//   status, inteligência do robô ou resultado da batalha.
//   Toda escolha do jogador é enviada para POST /api/batalha/{id}/turno,
//   e este arquivo apenas reproduz visualmente os eventos retornados pelo servidor
//   usando as configurações recebidas em CONFIG_BATALHA.

/**
 * Atualiza a barra de vida e status na tela quando há cura ou alteração de status.
 *
 * @param {string} lado       "jogador" ou "robo"
 * @param {number} novoHp     HP atual vindo do servidor
 * @param {number} dano       Quantidade de dano (opcional, para animar impacto)
 */
async function aplicarDanoNaTela(lado, novoHp) {
    const mon = ativo(lado);
    mon.hp = novoHp;
    atualizarInfo(lado);
    animar(lado, "dano", 600);
    await esperar(850);
}

/**
 * Sincroniza todos os dados do estado local (HP, status, ativo, rodada e cooldown)
 * com o snapshot autoritativo enviado pelo servidor ao final da requisição.
 *
 * @param {Object} estadoServidor Snapshot retornado em resposta.estado
 */
function sincronizarEstadoComServidor(estadoServidor) {
    if (!estadoServidor) return;

    if (typeof estadoServidor.turno === "number") {
        estado.rodada = estadoServidor.turno;
    }
    if (typeof estadoServidor.cooldownTroca === "number") {
        estado.cooldownTroca = estadoServidor.cooldownTroca;
    }
    if (typeof estadoServidor.fim === "boolean") {
        estado.fim = estadoServidor.fim;
    }

    // Sincroniza HP, status e índice ativo dos dois times
    for (const lado of ["jogador", "robo"]) {
        const listaServidor = estadoServidor[lado];
        if (!Array.isArray(listaServidor)) continue;

        const equipe = estado[lado].equipe;
        listaServidor.forEach((dadosMon, idx) => {
            const monLocal = equipe.pokemons[idx];
            if (!monLocal) return;

            monLocal.hp = dadosMon.hp;
            monLocal.maxHp = dadosMon.maxHp;
            monLocal.status = dadosMon.status || null;
            monLocal.is_ativo = !!dadosMon.is_ativo;

            if (dadosMon.is_ativo) {
                equipe.indiceAtivo = idx;
            }
        });
    }
}

/**
 * Reproduz sequencialmente a lista de eventos gerada pelo MotorDeBatalha no servidor.
 * Cada evento representa um acontecimento do turno (mensagem, ataque, dano, troca, etc.).
 *
 * @param {Array} eventos Lista de eventos [{ tipo, dados }, ...]
 * @returns {Promise<boolean>} true se o evento de fim de batalha já foi tratado
 */
async function reproduzirEventos(eventos) {
    let tratouFim = false;
    let jaSincronizou = false;

    for (const evento of eventos || []) {
        const dados = evento.dados || {};

        switch (evento.tipo) {
            case "mensagem":
                // Escreve a mensagem no log com efeito de digitação e aguarda leitura
                await dizer(dados.texto);
                break;

            case "animacao_ataque":
                // Executa a animação de investida do atacante
                animar(dados.lado, dados.lado === "jogador" ? "ataqueJogador" : "ataqueRobo", 350);
                await esperar(350);
                break;

            case "dano":
                // Atualiza HP do alvo atingido e anima o sprite piscando
                await aplicarDanoNaTela(dados.lado, dados.hp);
                break;

            case "cura": {
                // Atualiza HP e status do pokémon curado (ativo ou no banco)
                const alvoIdx = typeof dados.alvo === "number" ? dados.alvo : estado.jogador.equipe.indiceAtivo;
                const mon = estado.jogador.equipe.pokemons[alvoIdx];
                if (mon) {
                    mon.hp = dados.hp;
                    mon.status = dados.status || null;
                }
                if (alvoIdx === estado.jogador.equipe.indiceAtivo) {
                    atualizarInfo("jogador");
                }
                await esperar(800);
                if (dados.texto) await dizer(dados.texto);
                break;
            }

            case "atualizar_info": {
                // Atualiza barra/badge de status (ex.: queimadura, sono, cura por drenagem)
                const mon = ativo(dados.lado);
                if (typeof dados.hp === "number") mon.hp = dados.hp;
                if ("status" in dados) mon.status = dados.status || null;
                atualizarInfo(dados.lado);
                break;
            }

            case "item_usado":
                // Desconta 1 unidade no contador local do inventário exibido no painel
                if (dados.chave && estado.itens[dados.chave] > 0) {
                    estado.itens[dados.chave]--;
                }
                break;

            case "item_esgotado":
                if (dados.chave) {
                    estado.itens[dados.chave] = 0;
                }
                break;

            case "desmaio":
                // Zera HP visual e anima a queda do sprite
                ativo(dados.lado).hp = 0;
                atualizarInfo(dados.lado);
                LADOS[dados.lado].sprite.classList.add("desmaiou");
                LADOS[dados.lado].sombra.classList.add("sumiu");
                if (dados.texto) await dizer(dados.texto);
                break;

            case "troca": {
                // Troca de pokémon (jogador ou robô)
                const lado = dados.lado;
                if (lado === "jogador") {
                    LADOS.jogador.sprite.classList.add("desmaiou");
                    LADOS.jogador.sombra.classList.add("sumiu");
                    await esperar(300);
                }
                estado[lado].equipe.indiceAtivo = dados.indice;
                if (dados.texto) await dizer(dados.texto);
                mostrarPokemon(lado);
                await esperar(300);
                break;
            }

            case "troca_forcada": {
                // O pokémon ativo do jogador desmaiou, mas ainda há reserva viva:
                // abre o painel para o jogador escolher o substituto e envia ao servidor.
                const novoIndice = await estado.jogador.escolherSubstituto();
                const respostaTroca = await enviarAcaoNoServidor(estado.idBatalha, {
                    tipo: "trocar_forcado",
                    indice: novoIndice
                });

                if (respostaTroca.sair) return { tratouFim: true, jaSincronizou: true };
                if (respostaTroca.erro) {
                    await dizer(respostaTroca.erro);
                    break;
                }

                const resSub = await reproduzirEventos(respostaTroca.eventos);
                if (resSub.tratouFim) tratouFim = true;
                sincronizarEstadoComServidor(respostaTroca.estado);
                jaSincronizou = true;
                break;
            }

            case "fim":
                // O servidor já finalizou a batalha e calculou recompensa + conquistas
                tratouFim = true;
                await encerrarInterfaceBatalha(dados);
                break;

            default:
                console.warn("Evento de batalha desconhecido:", evento);
        }
    }

    return { tratouFim, jaSincronizou };
}

/**
 * Envia a jogada escolhida pelo jogador para validação no backend e reproduz o turno.
 * Usa as configurações de batalha carregadas em CONFIG_BATALHA.
 *
 * @param {Object} acao { tipo: "golpe"|"trocar"|"item"|"fugir", ... }
 */
async function executarRodada(acao) {
    // Validação preventiva rápida no cliente usando CONFIG_BATALHA antes de chamar o servidor
    if (acao.tipo === "trocar" && estado.cooldownTroca > 0) {
        await dizer(`Troca bloqueada por mais ${estado.cooldownTroca} round(s).`);
        return;
    }

    // Monta o payload exatamente como o endpoint /api/batalha/{id}/turno espera
    let payload;
    if (acao.tipo === "fugir") {
        payload = { tipo: "fugir" };
    } else if (acao.tipo === "trocar") {
        payload = { tipo: "trocar", indice: acao.indice };
    } else if (acao.tipo === "item") {
        payload = { tipo: "item", item: acao.item, alvo: acao.alvo };
    } else {
        payload = { tipo: "golpe", indice: acao.indice };
    }

    const resposta = await enviarAcaoNoServidor(estado.idBatalha, payload);

    // Se a sessão expirou, o redirecionamento já foi disparado
    if (resposta.sair) {
        estado.fim = true;
        return;
    }

    // Caso o servidor recuse a jogada por regra de jogo (ex.: item sem efeito, cooldown),
    // exibe a mensagem retornada e permite que o jogador escolha outra ação no mesmo turno.
    if (resposta.erro) {
        await dizer(resposta.erro);
        return;
    }

    // Reproduz os eventos e sincroniza caso a troca forçada já não tenha aplicado estado mais recente
    const { tratouFim, jaSincronizou } = await reproduzirEventos(resposta.eventos);
    if (!jaSincronizou) {
        sincronizarEstadoComServidor(resposta.estado);
    }

    // Garantia adicional caso o estado indique fim sem evento explícito
    if (estado.fim && !tratouFim) {
        const resultado = resposta.estado?.vencedor === "jogador"
            ? "vitoria"
            : (resposta.estado?.vencedor === "robo" ? "derrota" : "fuga");
        const vivos = estado.jogador.equipe.vivos().length;
        const dadosFim = await fecharBatalhaNoServidor(resultado, vivos);
        await encerrarInterfaceBatalha({ resultado, ...dadosFim });
    }
}

// ===================== fim de jogo =====================

/**
 * Informa ao jogador quais pokémons inimigos entraram para sua coleção após a vitória.
 */
async function anunciarConquistas(pokemons) {
    const nomes = (lista) => lista.map((p) => p.nome).join(", ").replace(/, ([^,]*)$/, " e $1");
    const novos = pokemons.filter((p) => p.novo);
    const repetidos = pokemons.filter((p) => !p.novo);

    if (novos.length) await dizer(`Você conquistou ${nomes(novos)}! Eles já estão na sua coleção.`);
    if (repetidos.length) await dizer(`Você já tinha ${nomes(repetidos)}.`);
}

/**
 * Atualiza a interface ao fim da batalha usando os valores reais calculados pelo servidor.
 *
 * @param {Object} dadosFim { resultado, recompensa, pokedolares, pokemons, erro? }
 */
async function encerrarInterfaceBatalha(dadosFim) {
    estado.fim = true;
    pararTempo();
    fecharPainel();

    // Se era batalha de chefe, desliga trovões e volta para a música normal
    if (estado.robo.chefe) iniciarMusica(false);

    if (dadosFim.erro) {
        await dizer(dadosFim.erro);
    } else if (dadosFim.recompensa > 0) {
        await dizer(`Você ganhou ${dadosFim.recompensa} pokédollars! Agora você tem ${dadosFim.pokedolares}.`);
    } else {
        await dizer("Você não ganhou pokédollars.");
    }

    if (dadosFim.resultado === "vitoria" && !dadosFim.erro) {
        await anunciarConquistas(dadosFim.pokemons || []);
    }

    bloquearMenu(false);
    mostrarVoltar(null);
    montarBotoes([
        // Revanche sem recarregar a página (recarregar acumularia entradas no histórico do navegador)
        { texto: "Revanche", onclick: () => { fugaEnviada = false; saidaLiberada = false; iniciar(); } },
        { texto: "Equipe", onclick: () => (location.href = URL_EQUIPE) },
        { texto: "Loja", onclick: () => (location.href = URL_LOJA) },
        { texto: "Início", onclick: () => (location.href = URL_HOME) }
    ]);
}

// ===================== sair da batalha =====================
// Sair no meio da batalha (voltar, fechar aba, recarregar) conta como fuga
// e é registrado no servidor por jogo.js via sendBeacon para /finalizar.
