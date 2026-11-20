<?php

/**
 * Gerencia a equipe de combate dos jogadores e suas evoluções
 * 
 * @author Miguel Granville / Vinicius Grub
 */
class EquipeDAO extends DAO
{
    private const TAMANHO_EQUIPE = 3;
    private const PEDRA = "pedra-evolutiva";

    /**
     * Lista os pokémons do tipo básico para seleção inicial
     * 
     * @author Miguel Granville / Vinicius Grub
     */
    public function basicos(): array
    {
        // Busca todos os pokemons base sem filtro de chaves específicas
        $dados = $this->Buscar([
            "colunas" => "b.id_pokemon, COALESCE(m.nome, b.nome) AS nome",
            "tabela" => ["pokemon_basico b", "LEFT JOIN monstro m", "ON m.id_monstro = b.id_pokemon"],
            "pesquisa" => "1 ORDER BY b.id_pokemon",
            "valores" => []
        ], true);

        $lista = [];
        foreach ($dados as $linha)
        {
            $lista[] = ["id" => (int) $linha["id_pokemon"], "nome" => $linha["nome"]];
        }
        return $lista;
    }

    /**
     * Traz todos os pokemons que compõem a coleção atual do jogador
     * 
     * @author Miguel Granville / Vinicius Grub
     */
    public function colecao(int $idUsuario): array
    {
        // Busca a coleção de pokemons do jogador e possíveis evoluções usando a chave id
        $dados = $this->Buscar([
            "colunas" => "pu.id_pokemon, m.nome, e.id_evolucao, evoluido.nome AS nome_evolucao",
            "tabela" => [
                "pokemon_usuario pu",
                "LEFT JOIN monstro m ON m.id_monstro = pu.id_pokemon",
                "LEFT JOIN evolucao_monstro e ON e.id_monstro = pu.id_pokemon",
                "LEFT JOIN monstro evoluido ON evoluido.id_monstro = e.id_evolucao"
            ],
            "pesquisa" => "pu.id_usuario = :id ORDER BY pu.id_pokemon",
            "valores" => ["id" => $idUsuario]
        ], true);

        $lista = [];
        foreach ($dados as $linha)
        {
            $id = (int) $linha["id_pokemon"];
            $evolucao = $linha["id_evolucao"] === null
                ? null
                : [
                    "id" => (int) $linha["id_evolucao"],
                    "nome" => $linha["nome_evolucao"]
                ];
            $lista[] = [
                "id" => $id,
                "nome" => $linha["nome"] ?? "#" . $id,
                "evolucao" => $evolucao
            ];
        }
        return $lista;
    }

    /**
     * Retorna a lista de onde o jogador irá montar a equipe, lidando com contas novas
     * 
     * @author Miguel Granville / Vinicius Grub
     */
    public function disponiveis(int $idUsuario): array
    {
        $colecao = $this->colecao($idUsuario);

        if (count($colecao) > 0)
        {
            return ["inicial" => false, "lista" => $colecao];
        }
        return ["inicial" => true, "lista" => $this->basicos()];
    }

    /**
     * Lista qual a equipe atual configurada e salva do jogador
     * 
     * @author Miguel Granville / Vinicius Grub
     */
    public function listar(int $idUsuario): array
    {
        // Busca a equipe ativa do jogador usando a chave id
        $dados = $this->Buscar([
            "colunas" => "e.id_pokemon, m.nome",
            "tabela" => ["equipe_usuario e", "LEFT JOIN monstro m", "ON m.id_monstro = e.id_pokemon"],
            "pesquisa" => "e.id_usuario = :id ORDER BY e.posicao",
            "valores" => ["id" => $idUsuario]
        ], true);

        $lista = [];
        foreach ($dados as $linha)
        {
            $id = (int) $linha["id_pokemon"];
            $lista[] = ["id" => $id, "nome" => $linha["nome"] ?? "#" . $id];
        }
        return $lista;
    }

    /**
     * Conta a quantidade de Pedras Evolutivas do jogador
     * 
     * @author Miguel Granville / Vinicius Grub
     */
    public function pedras(int $idUsuario): int
    {
        // Busca o total de pedras evolutivas no inventário usando as chaves id e chave
        $resultado = $this->Buscar([
            "colunas" => "COALESCE(SUM(iu.quantidade), 0) AS total",
            "tabela" => ["item_usuario iu", "JOIN item_loja i", "ON i.id_item_loja = iu.id_item_loja"],
            "pesquisa" => "iu.id_usuario = :id AND i.chave = :chave",
            "valores" => ["id" => $idUsuario, "chave" => self::PEDRA]
        ]);

        return (int) $resultado["total"];
    }

    /**
     * Aplica uma pedra em um pokemon elegível para evoluí-lo
     * 
     * @author Miguel Granville / Vinicius Grub
     */
    public function evoluir(int $idUsuario, int $idPokemon): array
    {
        if (!$this->IniciarTransaction())
        {
            throw new PDOException("Não foi possivel iniciar uma transaction", 500);
        }

        try
        {
            // Busca se o jogador realmente tem aquele pokemon usando as chaves usuario e pokemon
            $temPokemon = $this->Buscar([
                "colunas" => "1",
                "tabela" => "pokemon_usuario",
                "pesquisa" => "id_usuario = :usuario AND id_pokemon = :pokemon FOR UPDATE",
                "valores" => ["usuario" => $idUsuario, "pokemon" => $idPokemon]
            ]);
            if (!$temPokemon)
            {
                throw new RegraDeJogoException("Voce nao tem esse pokemon", 404);
            }

            // Busca os dados da evolução para a qual ele se transforma usando a chave pokemon
            $evolucao = $this->Buscar([
                "colunas" => "e.id_evolucao, a.nome AS nome_antigo, n.nome AS nome_novo",
                "tabela" => [
                    "evolucao_monstro e",
                    "JOIN monstro a ON a.id_monstro = e.id_monstro",
                    "JOIN monstro n ON n.id_monstro = e.id_evolucao"
                ],
                "pesquisa" => "e.id_monstro = :pokemon",
                "valores" => ["pokemon" => $idPokemon]
            ]);
            if (!$evolucao)
            {
                throw new RegraDeJogoException("Esse pokemon nao tem evolucao", 409);
            }
            $idNovo = (int) $evolucao["id_evolucao"];

            // Busca se o usuário já tem essa evolução usando as chaves usuario e novo
            $jaTemEvolucao = $this->Buscar([
                "colunas" => "1",
                "tabela" => "pokemon_usuario",
                "pesquisa" => "id_usuario = :usuario AND id_pokemon = :novo",
                "valores" => ["usuario" => $idUsuario, "novo" => $idNovo]
            ]);
            if ($jaTemEvolucao)
            {
                throw new RegraDeJogoException("Voce ja tem um " . $evolucao["nome_novo"], 409);
            }

            // Busca confirmando se tem pedra suficiente no inventário usando as chaves usuario e chave
            $temPedra = $this->Buscar([
                "colunas" => "iu.quantidade",
                "tabela" => ["item_usuario iu", "JOIN item_loja i ON i.id_item_loja = iu.id_item_loja"],
                "pesquisa" => "iu.id_usuario = :usuario AND i.chave = :chave AND iu.quantidade > 0 FOR UPDATE",
                "valores" => ["usuario" => $idUsuario, "chave" => self::PEDRA]
            ]);
            
            if (!$temPedra)
            {
                throw new RegraDeJogoException("Voce precisa de uma Pedra Evolutiva (compre na loja)", 409);
            }

            // Atualiza consumindo 1 unidade da pedra evolutiva do usuário baseando-se nas chaves usuario e chave
            $this->Atualizar([
                "tabela" => "item_usuario iu JOIN item_loja i ON i.id_item_loja = iu.id_item_loja",
                "colunas" => ["iu.quantidade" => "iu.quantidade - 1"],
                "condicao" => "iu.id_usuario = :usuario AND i.chave = :chave",
                "valores" => ["usuario" => $idUsuario, "chave" => self::PEDRA]
            ]);

            // Atualiza a linha do pokemon na coleção transformando no id da evolução (chave novo, usuario, antigo)
            $this->Atualizar([
                "tabela" => "pokemon_usuario",
                "colunas" => ["id_pokemon" => ":novo"],
                "condicao" => "id_usuario = :usuario AND id_pokemon = :antigo",
                "valores" => ["novo" => $idNovo, "usuario" => $idUsuario, "antigo" => $idPokemon]
            ]);

            // Atualiza também na equipe caso o pokemon esteja atrelado nela (chave novo, usuario, antigo)
            $this->Atualizar([
                "tabela" => "equipe_usuario",
                "colunas" => ["id_pokemon" => ":novo"],
                "condicao" => "id_usuario = :usuario AND id_pokemon = :antigo",
                "valores" => ["novo" => $idNovo, "usuario" => $idUsuario, "antigo" => $idPokemon]
            ]);

            $this->FinalizarTransaction();

            return [
                "antigo" => ["id" => $idPokemon, "nome" => $evolucao["nome_antigo"]],
                "novo" => ["id" => $idNovo, "nome" => $evolucao["nome_novo"]]
            ];
        }
        catch (Throwable $erro)
        {
            $this->ReverterTransaction();
            throw $erro;
        }
    }

    /**
     * Salva a equipe escolhida pelo jogador no banco de dados
     * 
     * @author Miguel Granville / Vinicius Grub
     */
    public function salvar(int $idUsuario, array $ids): void
    {
        $ids = array_values(array_unique(array_map("intval", $ids)));

        if (count($ids) !== self::TAMANHO_EQUIPE)
        {
            throw new RegraDeJogoException("Escolha " . self::TAMANHO_EQUIPE . " pokemons diferentes", 400);
        }

        $opcoes = $this->disponiveis($idUsuario);
        $permitidos = array_column($opcoes["lista"], "id");

        foreach ($ids as $id)
        {
            if (!in_array($id, $permitidos, true))
            {
                throw new RegraDeJogoException(
                    $opcoes["inicial"] ? "Escolha apenas pokemons basicos da lista" : "Escolha apenas pokemons que voce tem",
                    400
                );
            }
        }

        if (!$this->IniciarTransaction())
        {
            throw new PDOException("Não foi possivel iniciar uma transaction", 500);
        }

        try
        {
            if ($opcoes["inicial"])
            {
                foreach ($ids as $idPokemon)
                {
                    // [CORREÇÃO AQUI]: Valores arrumados para id_usuario e id_pokemon
                    $this->Inserir([
                        "tabela" => "pokemon_usuario",
                        "colunas" => ["id_usuario", "id_pokemon"],
                        "extra" => "ON DUPLICATE KEY UPDATE id_pokemon = id_pokemon",
                        "valores" => ["id_usuario" => $idUsuario, "id_pokemon" => $idPokemon]
                    ]);
                }
            }

            // Exclui a formação anterior da equipe na tabela baseando-se na chave id
            $this->Excluir([
                "tabela" => "equipe_usuario",
                "condicao" => "id_usuario = :id",
                "valores" => ["id" => $idUsuario]
            ]);

            foreach ($ids as $indice => $idPokemon)
            {
                // Insere as novas posições da equipe recém configurada com chaves id_usuario, posicao e id_pokemon
                $this->Inserir([
                    "tabela" => "equipe_usuario",
                    "colunas" => ["id_usuario", "posicao", "id_pokemon"],
                    "valores" => [
                        "id_usuario" => $idUsuario,
                        "posicao" => $indice + 1,
                        "id_pokemon" => $idPokemon
                    ]
                ]);
            }

            $this->FinalizarTransaction();
        }
        catch (Throwable $erro)
        {
            $this->ReverterTransaction();
            throw $erro;
        }
    }
}