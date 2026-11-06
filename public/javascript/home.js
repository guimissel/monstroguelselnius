const nomeUsuario = document.getElementById("nomeUsuario");
const saudacao = document.getElementById("saudacao");

// "Bom dia", "Boa tarde"... de acordo com a hora do computador
function saudacaoDoDia() {
    const hora = new Date().getHours();
    if (hora < 5) return "Boa madrugada,";
    if (hora < 12) return "Bom dia,";
    if (hora < 18) return "Boa tarde,";
    return "Boa noite,";
}

// dicas tiradas das regras do jogo
const DICAS = [
    "Você tem 20 segundos para escolher cada jogada.",
    "Golpe supereficaz causa o dobro de dano. Se os dois tipos do inimigo forem fracos, causa 4x!",
    "Trocar de pokémon gasta o seu turno e bloqueia novas trocas por 2 rounds.",
    "Poções só podem ser usadas na sua vez de jogar.",
    "Terminar a batalha com 3 pokémons vivos dobra os pokédollars da vitória.",
    "Pokémons de tipos diferentes cobrem as fraquezas uns dos outros."
];

// um pokemon da PokeAPI aparece para dar boas-vindas
const AMIGOS = [1, 4, 7, 25, 39, 52, 54, 133, 143];

async function chamarAmigo() {
    try {
        const id = AMIGOS[Math.floor(Math.random() * AMIGOS.length)];
        const resposta = await fetch(`https://pokeapi.co/api/v2/pokemon/${id}`);
        if (!resposta.ok) return;

        const dados = await resposta.json();
        const nome = dados.name.charAt(0).toUpperCase() + dados.name.slice(1);

        document.getElementById("spriteCompanheiro").src = dados.sprites.front_default;
        document.getElementById("falaCompanheiro").textContent = `${nome} apareceu para te dar as boas-vindas!`;
        document.getElementById("companheiro").hidden = false;
    } catch (erro) {
        // sem internet ou PokeAPI fora do ar: a home continua funcionando sem o pokemon
    }
}

document.addEventListener("DOMContentLoaded", async () => {
    saudacao.textContent = saudacaoDoDia();
    document.getElementById("dica").textContent = DICAS[Math.floor(Math.random() * DICAS.length)];
    chamarAmigo();

    // faz o fetch para a api
    const resposta = await fetch("/monstroguelselnius/api/usuario/eu", {
        method: "GET"
    });

    // recebe o resultado
    const resultado = await resposta.json();

    nomeUsuario.innerText = resultado.nome;
    document.title = `Início - ${resultado.nome}`;
})

const botaoSair = document.getElementById("sair");
botaoSair.addEventListener("click", async () => {
    // faz o fetch para a api
    const resposta = await fetch("/monstroguelselnius/api/usuario/sair", {
        method: "POST"
    });

    // recebe o resultado
    const resultado = await resposta.json();

    if (resultado.refresh) window.location.reload();
})

// o botao de excluir conta nao existe mais no html, entao so liga o evento se ele existir
const botaoExcluir = document.getElementById("excluir");
if (botaoExcluir) {
    botaoExcluir.addEventListener("click", async () => {
        const me = await fetch("/monstroguelselnius/api/usuario/eu", {
            method: "GET"
        });

        // recebe o resultado
        const mejson = await me.json();

        // faz o fetch para a api
        const resposta = await fetch(`/monstroguelselnius/api/usuario/${mejson.id}/excluir`, {
            method: "DELETE"
        });

        // recebe o resultado
        const resultado = await resposta.json();
        if (resultado.refresh) window.location.reload();
    })
}
