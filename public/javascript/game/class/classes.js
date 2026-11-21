// Classes do modelo de batalha.
// ===================== classes =====================

// efetividade entre os tipos (a tabela vem da PokeAPI)
class TabelaTipos {
    static dados = {};

    static async carregar() {
        await Promise.all(Object.keys(NOMES_TIPOS).map(async (tipo) => {
            const resposta = await buscar(`${API}/type/${tipo}`);
            const rel = resposta.damage_relations;
            TabelaTipos.dados[tipo] = {
                forte: rel.double_damage_to.map((t) => t.name),
                fraco: rel.half_damage_to.map((t) => t.name),
                imune: rel.no_damage_to.map((t) => t.name)
            };
        }));
    }

    // 2x se for forte, 0.5x se for fraco. com 2 tipos multiplica (4x, 0.25x)
    static multiplicador(tipoAtaque, tiposDefensor) {
        const rel = TabelaTipos.dados[tipoAtaque];
        let mult = 1;
        for (const t of tiposDefensor) {
            if (rel.imune.includes(t)) return 0;
            if (rel.forte.includes(t)) mult *= 2;
            else if (rel.fraco.includes(t)) mult *= 0.5;
        }
        return mult;
    }
}

// um pokemon em batalha: guarda os atributos e sabe fazer as contas dele
class Pokemon {
    constructor(dados) {
        Object.assign(this, dados);
    }

    estaDesmaiado() {
        return this.hp <= 0;
    }

    // devolve o dano que realmente foi tirado
    receberDano(dano) {
        const antes = this.hp;
        this.hp = Math.max(0, this.hp - dano);
        return antes - this.hp;
    }

    curar(quantidade) {
        this.hp = Math.min(this.maxHp, this.hp + quantidade);
    }

    limparStatus() {
        this.status = null;
        this.sono = 0;
    }

    // volta de um desmaio com metade do HP
    reviver() {
        this.hp = Math.floor(this.maxHp / 2);
        this.limparStatus();
    }

    get velocidadeEfetiva() {
        return this.status === "paralysis" ? this.spe / 2 : this.spe;
    }

    multiplicadorContra(tipoGolpe) {
        return TabelaTipos.multiplicador(tipoGolpe, this.tipos);
    }

    // dano que este pokemon causa em "alvo" com o golpe
    calcularDanoContra(alvo, golpe) {
        const fisico = golpe.classe === "physical";
        let ataque = fisico ? this.atk : this.spa;
        const defesa = fisico ? alvo.def : alvo.spd;
        if (fisico && this.status === "burn") ataque /= 2;

        const stab = this.tipos.includes(golpe.tipo) ? 1.5 : 1;
        const ef = alvo.multiplicadorContra(golpe.tipo);
        const critico = Math.random() < 1 / 16;
        const aleatorio = 0.85 + Math.random() * 0.15;

        const base = Math.floor((Math.floor((2 * this.nivel) / 5) + 2) * golpe.poder * ataque / defesa / 50) + 2;
        const total = Math.floor(base * stab * ef * (critico ? 1.5 : 1) * aleatorio);

        return { dano: ef === 0 ? 0 : Math.max(1, total), ef, critico };
    }
}

// os 3 pokemons de um treinador e qual deles esta em campo
class Time {
    #pokemons;

    constructor(pokemons = []) {
        this.#pokemons = pokemons;
        this.indiceAtivo = 0;
    }

    get pokemons() { return this.#pokemons; }
    get ativo() { return this.#pokemons[this.indiceAtivo]; }

    vivos() { return this.#pokemons.filter((p) => !p.estaDesmaiado()); }
    proximoVivo() { return this.#pokemons.findIndex((p) => !p.estaDesmaiado()); }
}

// quem batalha. Classe abstrata: o Jogador pergunta na tela, o Robo decide sozinho (polimorfismo)
class Treinador {
    constructor(lado) {
        if (new.target === Treinador) throw new Error("Treinador e abstrata");
        this.lado = lado;
        this.equipe = new Time();   // composicao: o time e deste treinador
    }

    // devolve { tipo: "golpe", indice } (o jogador ainda pode devolver "trocar", "item" ou "fugir")
    escolherAcao(batalha) { throw new Error("escolherAcao nao foi implementado"); }

    // indice do pokemon que entra quando o da vez desmaia
    escolherSubstituto() { throw new Error("escolherSubstituto nao foi implementado"); }

    mensagemEntrada(pokemon) { throw new Error("mensagemEntrada nao foi implementada"); }
}

class Jogador extends Treinador {
    constructor() { super("jogador"); }

    escolherAcao() { return perguntarAcao(); }
    escolherSubstituto() { return perguntarTrocaForcada(); }
    mensagemEntrada(pokemon) { return `Vai, ${pokemon.nome}!`; }
}

class Robo extends Treinador {
    constructor() { super("robo"); }

    // 70% das vezes escolhe o golpe que causa mais dano, senao um aleatorio
    escolherAcao(batalha) {
        const atq = this.equipe.ativo;
        const def = batalha.jogador.equipe.ativo;

        const pontos = (g) => g.poder
            ? g.poder * def.multiplicadorContra(g.tipo) * (atq.tipos.includes(g.tipo) ? 1.5 : 1) * ((g.precisao || 100) / 100)
            : (def.status ? 0 : 30);

        const golpe = Math.random() < 0.7
            ? [...atq.golpes].sort((a, b) => pontos(b) - pontos(a))[0]
            : atq.golpes[Math.floor(Math.random() * atq.golpes.length)];

        return { tipo: "golpe", indice: atq.golpes.indexOf(golpe) };
    }

    escolherSubstituto() { return this.equipe.proximoVivo(); }
    mensagemEntrada(pokemon) { return `O robô enviou ${pokemon.nome}!`; }
}

// a batalha inteira: os dois treinadores e o andamento
class Batalha {
    constructor() {
        this.jogador = new Jogador();
        this.robo = new Robo();
        this.rodada = 1;
        this.cooldownTroca = 0;
        this.itens = {};           // chave -> quantidade (preenchido por carregarInventario)
        this.fim = false;
        this.idBatalha = null;     // batalha aberta no servidor (para receber a recompensa)
        this.timer = null;
    }
}
