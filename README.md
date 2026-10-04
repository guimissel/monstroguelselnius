# Monstroguelselnius
### Integrantes: Guilherme Rahmeier Missel, Miguel Postal Sarmento Granville e Vinícius de Lima Grub

### Turma: DS3 Manhã

## 1. Telas do jogo (e o que cada uma faz)
### Montagem de Time
- O jogador escolhe seu time de três pokémons para batalhar
- Mostrar todos os pokémons que ele possui com a evolução lado a lado
- O usuário deve selecionar três pokémons distintos
- Ao clicar pronto, é redirecionado para a batalha contra o robô

### Tela de batalha
- Menu de pokémons, podendo alternar entre eles com um cooldown de 2 rounds
- Poções (de HP e de condição) que só poderão ser usadas na sua vez de jogar
- Tabela de ataques do pokémon
- Um round corresponde a um ataque de cada um dos jogadores
- O jogador terá 20 segundos para selecionar o ataque
- Para cada batalha:
  - Vitória: 50 pokédollars
    - Com 1 pokémon vivo: 1x
    - Com 2 pokémons vivos: 1.5x
    - Com 3 pokémons vivos: 2x
  - Derrota: 20 pokédollars

### Loja
- Mostrar pokédollars
- Mostrar categorias
  - Evoluções
    - Mostrar apenas as evoluções de pokémons que o usuário possui
  - Poções de HP e reviver
  - Poções de condição
  - Comprar sorteios de pokémon (pokébolas)
    - Poké Bola: 250 pokédollars
      - 100% de chance de vir pokémon básico
    - Grande Bola: 500 pokédollars
      - 30% de chance de vir pokémon básico e sua evolução
      - 70% de chance de vir pokémon básico
    - Ultra Bola: 1000 pokedóllars
      - 100% de chance de vir pokémon básico e sua evolução
- Exibir o preço em vermelho para produtos que o usuário não consegue comprar

## 2. Tipos de ataque e pokémon
### Para ataques
- Supereficaz: 2x o dano padrão. Se os dois tipos do pokémon defensor forem fracos contra o tipo do ataque, o dano é 4x.
- Pouco eficaz: 0.5x o dano padrão. Se os dois tipos do pokémon defensor forem fortes contra o tipo do ataque, o dano é 0.25x.

### Tabel de tipos
![tabela de tipos e suas fraquezas](https://i.pinimg.com/1200x/37/92/15/379215f6cee9fbd568e23c6d30c835fe.jpg)

## 3. Pokémons
