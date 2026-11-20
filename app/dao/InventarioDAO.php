<?php

require_once __DIR__ . "/../model/ItemInventario.php";

/**
 * Controla itens consumíveis, carteira e recompensas de kit
 * 
 * @author Miguel Granville / Vinicius Grub
 */
class InventarioDAO extends DAO
{
    private const IDS_LENDARIOS = [144, 145, 146, 150];

    /**
     * Retorna a lista de itens com saldo acima de 0 no inventário
     * 
     * @author Miguel Granville / Vinicius Grub
     */
    public function listar(int $idUsuario): array
    {
        // Busca todos os itens que o jogador possui mais que zero usando a chave id
        $dados = $this->Buscar([
            "colunas" => "i.*, iu.quantidade",
            "tabela" => ["item_usuario iu", "JOIN item_loja i", "ON i.id_item_loja = iu.id_item_loja"],
            "pesquisa" => "iu.id_usuario = :id AND iu.quantidade > 0 ORDER BY i.id_item_loja",
            "valores" => ["id" => $idUsuario]
        ], true);

        $itens = [];
        foreach ($dados as $linha)
        {
            $itens[] = new ItemInventario(
                (int) $linha["id_item_loja"],
                $linha["chave"],
                $linha["nome"],
                $linha["descricao"],
                $linha["categoria"],
                (int) $linha["preco"],
                $linha["imagem"],
                (int) $linha["cura_hp"],
                $linha["cura_status"],
                (bool) $linha["reviver"],
                (int) $linha["quantidade"]
            );
        }

        return $itens;
    }

    /**
     * Consome um item específico do inventário caso exista
     * 
     * @author Miguel Granville / Vinicius Grub
     */
    public function usar(int $idUsuario, string $chave): bool
    {
        // Busca se existe saldo do item específico a ser usado no inventário com as chaves id e chave
        $itemValido = $this->Buscar([
            "colunas" => "1",
            "tabela" => ["item_usuario iu", "JOIN item_loja i", "ON i.id_item_loja = iu.id_item_loja"],
            "pesquisa" => "iu.id_usuario = :id AND i.chave = :chave AND iu.quantidade > 0 AND i.categoria NOT IN ('evolucao', 'pokebola') FOR UPDATE",
            "valores" => ["id" => $idUsuario, "chave" => $chave]
        ]);

        if (!$itemValido)
        {
            return false;
        }

        // Atualiza decrescendo a quantidade do item usado no inventário com as chaves id e chave
        $this->Atualizar([
            "tabela" => "item_usuario iu JOIN item_loja i ON i.id_item_loja = iu.id_item_loja",
            "colunas" => ["iu.quantidade" => "iu.quantidade - 1"],
            "condicao" => "iu.id_usuario = :id AND i.chave = :chave",
            "valores" => ["id" => $idUsuario, "chave" => $chave]
        ]);

        return true;
    }

    /**
     * Sorteia e entrega um Pokemon advindo da pokebola surpresa, consumindo 1 unidade do item
     * 
     * @author Miguel Granville / Vinicius Grub
     */
    public function abrirPokebola(int $idUsuario): array
    {
        if (!$this->IniciarTransaction())
        {
            throw new PDOException("Não foi possivel iniciar uma transaction", 500);
        }

        try
        {
            // Busca o saldo de pokebolas que o jogador tem, validando com a chave usuario
            $item = $this->Buscar([
                "colunas" => "iu.id_item_loja, iu.quantidade",
                "tabela" => ["item_usuario iu", "JOIN item_loja i", "ON i.id_item_loja = iu.id_item_loja"],
                "pesquisa" => "iu.id_usuario = :usuario AND i.chave = 'pokebola' FOR UPDATE",
                "valores" => ["usuario" => $idUsuario]
            ]);

            if (!$item || (int) $item["quantidade"] < 1)
            {
                throw new RegraDeJogoException("Voce nao tem uma Pokebola Surpresa", 409);
            }

            // Busca a lista da pokedex para o sorteio, mapeando quais já pertencem usando a chave usuario
            $opcoesPokedex = $this->Buscar([
                "colunas" => "p.id_pokemon, p.nome, EXISTS (SELECT 1 FROM pokemon_usuario pu WHERE pu.id_usuario = :usuario AND pu.id_pokemon = p.id_pokemon) AS ja_tinha",
                "tabela" => "vw_pokedex p",
                "pesquisa" => "1",
                "valores" => ["usuario" => $idUsuario]
            ], true);

            $lendarios = [];
            $comuns = [];
            foreach ($opcoesPokedex as $opcao)
            {
                if (in_array((int) $opcao["id_pokemon"], self::IDS_LENDARIOS, true))
                {
                    $lendarios[] = $opcao;
                }
                else
                {
                    $comuns[] = $opcao;
                }
            }

            if (!$lendarios || !$comuns)
            {
                throw new RuntimeException("Catalogo sem Pokemons lendarios ou comuns para a Pokebola");
            }

            $lendario = random_int(1, 100) === 1;
            $opcoes = $lendario ? $lendarios : $comuns;
            $novos = array_values(array_filter($opcoes, fn (array $opcao) => !(bool) $opcao["ja_tinha"]));
            if ($novos)
            {
                $opcoes = $novos;
            }
            $pokemon = $opcoes[random_int(0, count($opcoes) - 1)];

            // Atualiza subtraindo a quantidade da pokebola usada via chaves usuario e item
            $this->Atualizar([
                "tabela" => "item_usuario",
                "colunas" => ["quantidade" => "quantidade - 1"],
                "condicao" => "id_usuario = :usuario AND id_item_loja = :item",
                "valores" => ["usuario" => $idUsuario, "item" => $item["id_item_loja"]]
            ]);

            // [CORREÇÃO AQUI]: Valores precisam ter os mesmos nomes das colunas: id_usuario e id_pokemon
            $this->Inserir([
                "tabela" => "pokemon_usuario",
                "colunas" => ["id_usuario", "id_pokemon"],
                "extra" => "ON DUPLICATE KEY UPDATE id_pokemon = id_pokemon",
                "valores" => ["id_usuario" => $idUsuario, "id_pokemon" => $pokemon["id_pokemon"]]
            ]);

            // Busca a nova quantidade restante de pokebolas atualizada usando chaves usuario e item
            $quantidadeData = $this->Buscar([
                "colunas" => "quantidade",
                "tabela" => "item_usuario",
                "pesquisa" => "id_usuario = :usuario AND id_item_loja = :item",
                "valores" => ["usuario" => $idUsuario, "item" => $item["id_item_loja"]]
            ]);

            $this->FinalizarTransaction();

            return [
                "pokemon" => [
                    "id" => (int) $pokemon["id_pokemon"],
                    "nome" => $pokemon["nome"],
                    "lendario" => $lendario,
                    "jaTinha" => (bool) $pokemon["ja_tinha"]
                ],
                "quantidade" => (int) $quantidadeData["quantidade"]
            ];
        }
        catch (Throwable $erro)
        {
            $this->ReverterTransaction();
            throw $erro;
        }
    }

    /**
     * Retorna a quantidade de dinheiro interno do jogador
     * 
     * @author Miguel Granville / Vinicius Grub
     */
    public function pokedolares(int $idUsuario): int
    {
        // Busca o saldo atual em dinheiro do jogador usando a chave id
        $resultado = $this->Buscar([
            "colunas" => "pokedolares",
            "tabela" => "usuario",
            "pesquisa" => "id_usuario = :id",
            "valores" => ["id" => $idUsuario]
        ]);

        return (int) $resultado["pokedolares"];
    }

    /**
     * Cede ao jogador iniciante um kit inicial com itens gratuitos
     * 
     * @author Miguel Granville / Vinicius Grub
     */
    public function darKitInicial(int $idUsuario): void
    {
        // Busca os itens que estão demarcados para serem entregues no início (sem filtro de chave específico)
        $itensIniciais = $this->Buscar([
            "colunas" => "id_item_loja, kit_inicial",
            "tabela" => "item_loja",
            "pesquisa" => "kit_inicial > 0",
            "valores" => []
        ], true);

        if ($itensIniciais)
        {
            foreach ($itensIniciais as $item)
            {
                // Insere no banco as quantidades iniciais usando as chaves id_usuario, id_item_loja e quantidade
                $this->Inserir([
                    "tabela" => "item_usuario",
                    "colunas" => ["id_usuario", "id_item_loja", "quantidade"],
                    "extra" => "ON DUPLICATE KEY UPDATE quantidade = quantidade", 
                    "valores" => [
                        "id_usuario" => $idUsuario,
                        "id_item_loja" => $item["id_item_loja"],
                        "quantidade" => $item["kit_inicial"]
                    ]
                ]);
            }
        }
    }
}