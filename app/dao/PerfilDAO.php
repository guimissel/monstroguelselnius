<?php

/**
 * Acessa e atualiza os dados de perfil e progresso dos jogadores
 *
 * @author Miguel Granville / Vinicius Grub
 * @method ?Treinador treinador(int $idUsuario)
 * @method array placar(int $idUsuario)
 * @method array ranking()
 * @method array historico(int $idUsuario, ?int $limite = 10, bool $somenteConcluidas = false)
 * @method array colecao(int $idUsuario)
 * @method array itens(int $idUsuario)
 * @method void importarProgresso(int $idUsuario, int $pokedolares, array $colecao, array $equipe, array $itens, array $historico)
 * @method int posicao(int $vitorias)
 */
class PerfilDAO extends DAO
{

    /**
     * Monta um treinador com carteira, coleção e equipe
     * 
     * @author Miguel Granville / Vinicius Grub
     * @param int $idUsuario identificador do jogador
     * @return ?Treinador retorna o treinador montado ou null se o jogador não existir
    */
    public function treinador(int $idUsuario): ?Treinador
    {
        // Busca os dados básicos necessários para criar o treinador.
        $linha = $this->Buscar([
            "colunas" => ["id_usuario", "nome", "pokedolares"],
            "tabela" => "usuario",
            "pesquisa" => "id_usuario = :id",
            "valores" => [
                "id" => $idUsuario
            ]
        ]);

        // Interrompe a montagem quando o jogador não existe.
        if (!$linha) 
        {
            return null;
        }

        // Cria o treinador usando o ID, nome e saldo retornados pelo banco.
        $treinador = new Treinador(
            (int) $linha["id_usuario"], 
            $linha["nome"], 
            (int) $linha["pokedolares"]
        );

        // Reutiliza a mesma conexão nas consultas da coleção e da equipe.
        $pokemonDAO = PokemonDAO::NovaInstancia(["pdo" => $this->pdo]);

        // Carrega e adiciona ao treinador todos os Pokémon da coleção.
        $colecao = $pokemonDAO->daColecao($idUsuario);
        foreach ($colecao as $pokemon) {
            $treinador->adicionarNaColecao($pokemon);
        }

        // Carrega a equipe já ordenada pelo DAO de Pokémon e a copia para o treinador.
        $equipe = $pokemonDAO->daEquipe($idUsuario)->todos();
        foreach ($equipe as $pokemon) {
            $treinador->getEquipe()->adicionar($pokemon);
        }

        // Retorna o treinador com os dados e Pokémon carregados.
        return $treinador;
    }

    /**
     * Busca o placar do usuário na view de ranking
     * 
     * @author Miguel Granville / Vinicius Grub
     * @param int $idUsuario identificador do jogador
     * @return array retorna contagens de batalhas, vitórias, derrotas, fugas e ganhos
    */
    public function placar(int $idUsuario): array
    {
        // Busca os dados agregados; a consulta retorna false quando não há linha.
        $linha = $this->Buscar([
            "tabela" => "vw_ranking_jogadores",
            "pesquisa" => "id_usuario = :id",
            "valores" => [
                "id" => $idUsuario
            ]
        ]) ?: [];

        // Normaliza os totais para inteiros e fornece zero caso não exista resultado.
        return [
            "batalhas"      => (int) ($linha["batalhas"] ?? 0),
            "vitorias"      => (int) ($linha["vitorias"] ?? 0),
            "derrotas"      => (int) ($linha["derrotas"] ?? 0),
            "fugas"         => (int) ($linha["fugas"] ?? 0),
            "totalGanho"    => (int) ($linha["total_ganho"] ?? 0)
        ];
    }

    /**
     * Busca os cinco jogadores mais bem colocados no ranking
     * 
     * @author Miguel Granville / Vinicius Grub
     * @return array retorna jogadores ordenados por vitórias, ganhos e nome
     */
    public function ranking(): array
    {
        // Seleciona jogadores com partidas concluídas e aplica os critérios do ranking.
        $linhas = $this->Buscar([
            "colunas" => ["nome", "vitorias", "batalhas", "total_ganho"],
            "tabela" => "vw_ranking_jogadores",
            "pesquisa" => "batalhas > 0 ORDER BY vitorias DESC, total_ganho DESC, nome LIMIT 5",
            "valores" => []
        ], true);

        // Converte os totais do banco para inteiros e adapta as chaves da resposta.
        return array_map(fn (array $l) => [
            "nome" => $l["nome"],
            "vitorias" => (int) $l["vitorias"],
            "batalhas" => (int) $l["batalhas"],
            "totalGanho" => (int) $l["total_ganho"]
        ], $linhas);
    }

    /**
     * Busca o histórico de batalhas de um jogador
     * 
     * @author Miguel Granville / Vinicius Grub
     * @param int $idUsuario identificador do jogador
     * @param ?int $limite quantidade máxima de batalhas; null não limita o resultado
     * @param bool $somenteConcluidas indica se batalhas em andamento devem ser excluídas
     * @return array retorna as batalhas com os dados preparados para o perfil
     */
    public function historico(int $idUsuario, ?int $limite = 10, bool $somenteConcluidas = false): array
    {
        // Define os dados do histórico e o indicador de batalha contra chefe.
        $arrayColunas = [
            "h.id_batalha",
            "h.adversario",
            "h.status",
            "h.recompensa",
            "h.iniciada_em",
            "h.finalizada_em",
            "h.duracao_segundos",
            "b.is_boss"
        ];
        
        // Junta a view de histórico à tabela de batalha para obter o indicador de chefe.
        $tabelaEJoin = "
            vw_historico_batalhas 
            h JOIN batalha b 
            ON b.id_batalha = h.id_batalha
        ";
        
        // Filtra pelo jogador, opcionalmente remove batalhas em andamento e ordena da mais recente.
        $query = "h.id_usuario = :id"
            . ($somenteConcluidas ? " AND h.status <> 'em_andamento'" : "")
            . " ORDER BY h.id_batalha DESC";
        
        // Acrescenta um limite não negativo quando a chamada solicitar um número máximo.
        if ($limite !== null) 
        {
            $query .= " LIMIT " . max(0, $limite);
        }

        // Executa a busca compartilhada e solicita todas as linhas encontradas.
        $linhas = $this->Buscar([
            "colunas" => $arrayColunas,
            "tabela" => $tabelaEJoin,
            "pesquisa" => $query,
            "valores" => [
                "id" => $idUsuario
            ]
        ], true);

        // Converte os campos numéricos e renomeia as chaves para o formato da aplicação.
        return array_map(fn (array $l) => [
            "id" => (int) $l["id_batalha"],
            "adversario" => $l["adversario"],
            "status" => $l["status"],
            "recompensa" => (int) $l["recompensa"],
            "iniciadaEm" => $l["iniciada_em"],
            "finalizadaEm" => $l["finalizada_em"],
            "duracao" => $l["duracao_segundos"] === null ? null : (int) $l["duracao_segundos"],
            "chefe" => (bool) $l["is_boss"]
        ], $linhas);
    }

    /**
     * Busca a coleção de Pokémon e indica quais estão na equipe
     * 
     * @author Miguel Granville / Vinicius Grub
     * @param int $idUsuario identificador do jogador
     * @return array retorna os Pokémon da coleção com nome, data e estado na equipe
     */
    public function colecao(int $idUsuario): array
    {
        // Busca os Pokémon do jogador, priorizando os que estão na equipe.
        $linhas = $this->Buscar([
            "colunas" => ["id_pokemon", "pokemon", "conquistado_em", "na_equipe"],
            "tabela" => "vw_colecao_usuario",
            "pesquisa" => "id_usuario = :id ORDER BY na_equipe DESC, id_pokemon",
            "valores" => ["id" => $idUsuario]
        ], true);

        // Converte o ID e o indicador de equipe para os tipos esperados pela aplicação.
        return array_map(fn (array $l) => [
            "id" => (int) $l["id_pokemon"],
            "nome" => $l["pokemon"],
            "naEquipe" => (bool) $l["na_equipe"],
            "conquistadoEm" => $l["conquistado_em"]
        ], $linhas);
    }

    /**
     * Busca os itens que o jogador possui
     * 
     * @author Miguel Granville / Vinicius Grub
     * @param int $idUsuario identificador do jogador
     * @return array retorna os itens ordenados pelo nome
     */
    public function itens(int $idUsuario): array
    {
        // Busca os itens do inventário do jogador, ordenados pelo nome exibido.
        $linhas = $this->Buscar([
            "colunas" => ["chave", "item", "quantidade"],
            "tabela" => "vw_inventario_usuario",
            "pesquisa" => "id_usuario = :id ORDER BY item",
            "valores" => ["id" => $idUsuario]
        ], true);

        // Converte a quantidade para inteiro e adapta os nomes dos campos para a resposta.
        return array_map(fn (array $l) => [
            "chave" => $l["chave"],
            "nome" => $l["item"],
            "quantidade" => (int) $l["quantidade"]
        ], $linhas);
    }

    /**
     * Substitui o progresso do jogador após validar as referências importadas
     * 
     * @author Miguel Granville / Vinicius Grub
     * @param int $idUsuario identificador do jogador
     * @param int $pokedolares saldo que será gravado
     * @param array $colecao Pokémon da coleção e respectivas datas de aquisição
     * @param array $equipe IDs dos Pokémon na ordem da equipe
     * @param array $itens itens e quantidades a serem restaurados
     * @param array $historico batalhas que serão restauradas em ordem cronológica
     * @return void
     */
    public function importarProgresso(
        int $idUsuario,
        int $pokedolares,
        array $colecao,
        array $equipe,
        array $itens,
        array $historico
    ): void {
        // Carrega o catálogo instalado e prepara um conjunto para validar IDs rapidamente.
        $catalogoPokemon = $this->Buscar([
            "colunas" => ["id_pokemon"],
            "tabela" => "vw_pokedex",
            "pesquisa" => "1 = 1",
            "valores" => []
        ], true);
        $idsPokemonValidos = array_fill_keys(
            array_map("intval", array_column($catalogoPokemon, "id_pokemon")),
            true
        );

        // Impede importar referências a Pokémon ausentes do catálogo atual.
        foreach ($colecao as $pokemon) {
            if (!isset($idsPokemonValidos[$pokemon["id"]])) {
                throw new DomainException("O arquivo contém um Pokémon que não existe no catálogo atual");
            }
        }

        // Carrega as identidades atuais dos itens para aceitar chaves ou nomes do arquivo.
        $catalogoItens = $this->Buscar([
            "colunas" => ["id_item_loja", "chave", "nome"],
            "tabela" => "item_loja",
            "pesquisa" => "1 = 1",
            "valores" => []
        ], true);
        $itensPorChave = [];
        $itensPorNome = [];

        // Indexa os itens pelos dois identificadores aceitos em versões do progresso.
        foreach ($catalogoItens as $item) {
            $itensPorChave[$item["chave"]] = (int) $item["id_item_loja"];
            $itensPorNome[$item["nome"]] = (int) $item["id_item_loja"];
        }

        // Resolve os itens importados para IDs atuais e rejeita referências inválidas ou repetidas.
        $itensImportados = [];
        foreach ($itens as $item) {
            $idItem = $item["chave"] !== null
                ? ($itensPorChave[$item["chave"]] ?? null)
                : ($itensPorNome[$item["nome"]] ?? null);
            if ($idItem === null) {
                throw new DomainException("O arquivo contém um item que não existe na loja atual");
            }
            if (isset($itensImportados[$idItem])) {
                throw new DomainException("O arquivo contém itens duplicados");
            }

            // Guarda a quantidade usando o ID atual do item como índice.
            $itensImportados[$idItem] = $item["quantidade"];
        }

        // Inicia a transação para que a substituição inteira seja atômica.
        $this->pdo->beginTransaction();
        try {
            // Bloqueia o jogador e confirma sua existência antes de remover dados.
            $jogador = $this->Buscar([
                "colunas" => ["id_usuario"],
                "tabela" => "usuario",
                "pesquisa" => "id_usuario = :id FOR UPDATE",
                "valores" => ["id" => $idUsuario]
            ]);
            if (!$jogador) {
                throw new DomainException("Jogador nao encontrado");
            }

            // Remove batalhas e dados dependentes do progresso anterior.
            $this->Excluir([
                "tabela" => "batalha",
                "condicao" => "id_usuario = :id",
                "valores" => ["id" => $idUsuario]
            ]);

            // Remove equipe, coleção e inventário antes de reconstruí-los.
            foreach (["equipe_usuario", "pokemon_usuario", "item_usuario"] as $tabela) {
                $this->Excluir([
                    "tabela" => $tabela,
                    "condicao" => "id_usuario = :id",
                    "valores" => ["id" => $idUsuario]
                ]);
            }

            // Atualiza o saldo do jogador com o valor validado do arquivo.
            $this->Atualizar([
                "tabela" => "usuario",
                "colunas" => ["pokedolares" => ":saldo"],
                "condicao" => "id_usuario = :id",
                "valores" => ["saldo" => $pokedolares, "id" => $idUsuario]
            ]);

            // Recria a coleção e preserva as datas de aquisição fornecidas.
            foreach ($colecao as $pokemon) {
                $colunasPokemon = ["id_usuario", "id_pokemon"];
                $valoresPokemon = [
                    "id_usuario" => $idUsuario,
                    "id_pokemon" => $pokemon["id"]
                ];

                // Omite a data quando ausente para deixar o banco aplicar seu valor padrão.
                if ($pokemon["conquistadoEm"] !== null) {
                    $colunasPokemon[] = "conquistado_em";
                    $valoresPokemon["conquistado_em"] = $pokemon["conquistadoEm"];
                }

                // Insere o Pokémon usando apenas as colunas presentes no arquivo.
                $this->Inserir([
                    "tabela" => "pokemon_usuario",
                    "colunas" => $colunasPokemon,
                    "valores" => $valoresPokemon
                ]);
            }

            // Recria a equipe usando a posição de cada Pokémon no array.
            foreach ($equipe as $indice => $idPokemon) {
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

            // Restaura apenas itens com quantidade positiva.
            foreach ($itensImportados as $idItem => $quantidade) {
                if ($quantidade > 0) {
                    $this->Inserir([
                        "tabela" => "item_usuario",
                        "colunas" => ["id_usuario", "id_item_loja", "quantidade"],
                        "valores" => [
                            "id_usuario" => $idUsuario,
                            "id_item_loja" => $idItem,
                            "quantidade" => $quantidade
                        ]
                    ]);
                }
            }

            // Insere do mais antigo para o mais recente para preservar a ordem dos IDs.
            foreach (array_reverse($historico) as $batalha) {
                $this->Inserir([
                    "tabela" => "batalha",
                    "colunas" => [
                        "id_usuario",
                        "is_boss",
                        "status",
                        "recompensa",
                        "iniciada_em",
                        "finalizada_em"
                    ],
                    "valores" => [
                        "id_usuario" => $idUsuario,
                        "is_boss" => (int) $batalha["chefe"],
                        "status" => $batalha["status"],
                        "recompensa" => $batalha["recompensa"],
                        "iniciada_em" => $batalha["iniciadaEm"],
                        "finalizada_em" => $batalha["finalizadaEm"]
                    ]
                ]);
            }

            // Confirma todas as alterações somente após restaurar cada parte do progresso.
            $this->pdo->commit();
        } catch (Throwable $erro) {
            // Desfaz a substituição inteira caso qualquer operação da transação falhe.
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Propaga o erro para que o chamador possa tratá-lo.
            throw $erro;
        }
    }

    /**
     * Calcula a posição do jogador no ranking de vitórias
     * 
     * @author Miguel Granville / Vinicius Grub
     * @param int $vitorias quantidade de vitórias do jogador
     * @return int posição do jogador, começando em 1
     */
    public function posicao(int $vitorias): int
    {
        // Conta quantos jogadores têm mais vitórias e soma um para obter a posição.
        $linha = $this->Buscar([
            "colunas" => ["COUNT(*) + 1 AS posicao"],
            "tabela" => "vw_ranking_jogadores",
            "pesquisa" => "vitorias > :v",
            "valores" => ["v" => $vitorias]
        ]);

        // Retorna a posição como inteiro.
        return (int) $linha["posicao"];
    }
}
