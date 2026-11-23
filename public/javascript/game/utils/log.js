// Log de texto, sons e trovoes da batalha.
let pularTexto = null;

logTexto.addEventListener("click", () => pularTexto && pularTexto());

// escreve no log letra por letra. rapido = aparece na hora e nao espera
// musica de fundo da batalha: fica em loop enquanto a tela estiver aberta
// (o navegador so deixa tocar som depois que a pessoa clicou em algo no site; se bloquear, tenta de novo no primeiro clique)
const MUSICA_NORMAL = "public/audio/fundo.mp3";
const MUSICA_CHEFE = "public/audio/suspense.mp3";
const musicaFundo = new Audio(MUSICA_NORMAL);
musicaFundo.loop = true;
musicaFundo.preload = "auto";
musicaFundo.volume = 0.4;
let musicaLigada = false;       // a batalha pediu musica
let esperandoInteracao = false;

// Inicia a trilha respeitando as preferências e o estado atual do áudio.
function tocarMusica() {
    const tocando = musicaFundo.play();
    if (!tocando) return;
    tocando.catch(() => {
        if (esperandoInteracao) return;
        esperandoInteracao = true;
        const tentar = () => {
            esperandoInteracao = false;
            document.removeEventListener("pointerdown", tentar);
            document.removeEventListener("keydown", tentar);
            if (musicaLigada && !document.hidden) musicaFundo.play().catch(() => {});
        };
        document.addEventListener("pointerdown", tentar);
        document.addEventListener("keydown", tentar);
    });
}

// chefe = true: musica de suspense e trovoes. Na batalha normal (ou depois que o chefe acaba) volta a musica de sempre
function iniciarMusica(chefe = false) {
    const arquivo = chefe ? MUSICA_CHEFE : MUSICA_NORMAL;
    if (!musicaFundo.src.endsWith(arquivo)) musicaFundo.src = arquivo;   // trocar o arquivo para a musica

    musicaFundo.volume = chefe ? 0.5 : 0.4;
    musicaLigada = true;
    if (musicaFundo.paused) tocarMusica();   // na revanche ela ja esta tocando e continua de onde estava

    if (chefe) trovoes.ligar();
    else trovoes.desligar();
}

// aba escondida: pausa a musica; ao voltar, continua
document.addEventListener("visibilitychange", () => {
    if (document.hidden) musicaFundo.pause();
    else if (musicaLigada) tocarMusica();
});

// trovoes da batalha do chefe: de tempos em tempos soa um trovao e a tela da um clarao
class Trovoes {
    #sons = [new Audio("public/audio/trovao1.mp3"), new Audio("public/audio/trovao2.mp3")];
    #timer = null;

    constructor() {
        this.#sons.forEach((som) => { som.preload = "auto"; som.volume = 0.7; });
    }

    ligar() {
        if (this.#timer !== null) return;   // ja esta ligado
        this.#agendar(3000 + Math.random() * 3000);
    }

    desligar() {
        clearTimeout(this.#timer);
        this.#timer = null;
        this.#sons.forEach((som) => som.pause());
    }

    #agendar(ms) {
        this.#timer = setTimeout(() => {
            this.#trovejar();
            this.#agendar(7000 + Math.random() * 9000);
        }, ms);
    }

    #trovejar() {
        if (document.hidden) return;

        const som = this.#sons[Math.floor(Math.random() * this.#sons.length)];
        som.currentTime = 0;
        const tocando = som.play();
        // o clarao so aparece se o som tocou (o navegador pode bloquear o som ate o primeiro clique)
        if (tocando) tocando.then(() => this.#clarao()).catch(() => {});
        else this.#clarao();
    }

    #clarao() {
        palco.classList.remove("relampago");
        void palco.offsetWidth;
        palco.classList.add("relampago");
    }
}

const trovoes = new Trovoes();

// som de texto sendo escrito: toca toda vez que algo aparece na caixa de log
// (o navegador so deixa tocar som depois que a pessoa clicou em algo no site; se bloquear, o jogo segue sem som)
const somLog = new Audio("public/audio/log.mp3");
somLog.preload = "auto";
somLog.volume = 0.6;
let timerSomLog = null;

// Interrompe a reprodução associada ao registro de falas da batalha.
function pararSomLog() {
    clearTimeout(timerSomLog);
    somLog.pause();
}

// duracaoMax (ms): para mensagens que aparecem de uma vez, o som toca so um pouquinho
function tocarSomLog(duracaoMax = 0) {
    try {
        pararSomLog();
        somLog.currentTime = 0;
        const tocando = somLog.play();
        if (tocando) tocando.catch(() => {});
        if (duracaoMax > 0) timerSomLog = setTimeout(pararSomLog, duracaoMax);
    } catch (erro) {
        // sem som: segue o jogo
    }
}

// Apresenta uma fala e aguarda sua conclusão antes de liberar o próximo passo.
function dizer(texto, { rapido = false, pausa = 650 } = {}) {
    const p = document.createElement("p");
    logTexto.appendChild(p);
    //  Repete o bloco enquanto (logTexto.children.length > 80) logTexto.firstChild.remove( for verdadeiro.
    while (logTexto.children.length > 80) logTexto.firstChild.remove();

    if (rapido) {
        p.textContent = texto;
        logTexto.scrollTop = logTexto.scrollHeight;
        tocarSomLog(350);
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        let i = 0;
        tocarSomLog();
        const terminar = () => {
            clearInterval(intervalo);
            pararSomLog();
            p.textContent = texto;
            logTexto.scrollTop = logTexto.scrollHeight;
            pularTexto = null;
            setTimeout(resolve, pausa);
        };
        const intervalo = setInterval(() => {
            i++;
            p.textContent = texto.slice(0, i);
            logTexto.scrollTop = logTexto.scrollHeight;
            if (i >= texto.length) terminar();
        }, 22);
        pularTexto = () => { pularTexto = null; clearInterval(intervalo); pararSomLog(); p.textContent = texto; setTimeout(resolve, 250); };
    });
}

// ===================== tela =====================