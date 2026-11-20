<?php

require_once __DIR__ . "/../model/ItemInventario.php";

/**
 * Controlar ações da loja
 * 
 * @author Vinicius Grub / Miguel Granville
 * @method array listar (int $idUsuario)
 * @method array comprar(int $idUsuario, string $chave, int $quantidade)
 */
class LojaDAO extends DAO
{
    
    /**
     * todos os itens a venda, com a quantidade que o jogador ja tem (pode ser 0)
     * 
     * @author Vinicius Grub / Miguel Granville 
     * @param int $idUsuario id do usuario para fazer um JOIN verififcando se o usuario possui cada item
     * @return array retorna uma lista de itens
     */
    public function listar(int $idUsuario): array
    {
        // realiza a busca no banco de dados
        $dados = $this->Buscar([
            // i.* é um alias
            // O COALESCE retorna o primeiro valor que não seja NULL
            "colunas" => "i.*, COALESCE(iu.quantidade, 0) AS quantidade",

            "tabela" => [
                "item_loja i",
                "LEFT JOIN item_usuario iu",
                "ON iu.id_item_loja = i.id_item_loja",
                "AND iu.id_usuario = :id"
            ],

            "pesquisa" => "1 ORDER BY i.id_item_loja",
            "valores" => [
                "id" => $idUsuario
            ]
        ], true);

        // lista com todos itens
        // isso inclui a quantidade que o usuario já posssui
        $itens = [];

        // percorre toda lista de dados
        foreach ($dados as $linha) {

            // retorna adiciona um item de catalogo
            // é passado a quantidade que o usuario possui para exibir na loja
            $itens[] = new ItemInventario(

                // parte item da loja
                (int)   $linha["id_item_loja"],
                        $linha["chave"],
                        $linha["nome"],
                        $linha["descricao"],
                        $linha["categoria"],
                (int)   $linha["preco"],
                        $linha["imagem"],
                (int)   $linha["cura_hp"],
                        $linha["cura_status"],
                (bool)  $linha["reviver"],

                // para exibir na loja
                (int)   $linha["quantidade"]
            );
        }

        return $itens;
    }

    /**
     * @author Vinicius Grub / Miguel Granville
     * @param int $idUsuario usado para procurar o usuario e seu dinheiro
     * @param string $chave item que o usuario quer comprar
     * @param int $quantidade quantidade do item desejado
     * @return array retorna uma lista contendo os pokedolares depois da compra e a quantidade do item 
     */
    public function comprar(int $idUsuario, string $chave, int $quantidade): array
    {
        // inicia uma transação
        if(!$this->IniciarTransaction())
        {
            throw new PDOException("Não foi possivel iniciar uma transaction", 500);
        }

        // tenta realiza a compra que o usuario quer
        try {

            // item que o jogador quer
            $item = $this->Buscar([
                "colunas" => ["id_item_loja", "preco"],
                "tabela" => "item_loja",
                "pesquisa" => "chave = :chave",
                "valores" => [
                    "chave" => $chave
                ]
            ]);

            // caso o retorno não exista
            if (!$item) 
            {
                // trat uma nova exception
                throw new RegraDeJogoException("Esse item nao existe", 404);
            }

            // verificar se a quantidade não é valida
            if ($quantidade <= 0 || $quantidade > 99)
            {
                throw new RuntimeException("A quantidade não é valida", 503);
            }

            // calcula o total e garante que não seja um valor negativo
            $total = abs((int) $item["preco"] * $quantidade);
            
            // caso o valor não seja válido
            if($total <= 0)
            {
                throw new RuntimeException("O total não pode ser negativo ou zerado", 503);
            }

            // trava a linha do jogador ate o fim da transaction
            // usa fetch simples, não é o melhor método mas se eu tiver tempo atualizarei esse metodo
            $saldo = (int) $this->Buscar([
                "colunas" => "pokedolares",
                "tabela" => "usuario",
                "pesquisa" => "id_usuario = :id FOR UPDATE",
                "valores" => [
                    "id" => $idUsuario
                ]
            ])["pokedolares"];

            // se for pobre
            if ($saldo < $total) 
            {
                // trata a exceção
                throw new SaldoInsuficienteException($saldo, $total);
            }

            // ataliza o saldo do jogador
            $this->Atualizar([
                "tabela" => "usuario",
                "colunas" => [
                    "pokedolares" => "pokedolares - :total"
                ],
                "condicao" => "id_usuario = :id",
                "valores" => [
                    "total" => $total, 
                    "id" => $idUsuario
                ]
            ]);

            // se ja tem o item soma, senao cria a linha
            $this->Inserir([
                "tabela" => "item_usuario",
                "colunas" => [
                    "id_usuario", 
                    "id_item_loja", 
                    "quantidade"
                ],

                "extra" => "ON DUPLICATE KEY UPDATE quantidade = quantidade + :quantidade2",
                "valores" => [
                    "id_usuario" => $idUsuario,
                    "id_item_loja" => $item["id_item_loja"],
                    "quantidade" => $quantidade,
                    "quantidade2" => $quantidade
                ]
            ]);

            // historico da loja (quem comprou o que e por quanto)
            $this->Inserir([
                "tabela" => "log_loja",
                "colunas" => [
                    "id_usuario", 
                    "id_item_loja", 
                    "quantidade", 
                    "preco_unitario", 
                    "total"
                ],

                "valores" => [
                    "id_usuario" => $idUsuario, 
                    "id_item_loja" => $item["id_item_loja"], 
                    "quantidade" => $quantidade, 
                    "preco_unitario" => $item["preco"], 
                    "total" => $total
                ]
            ]);

            // buscar a quantidade do item
            $novaQuantidade = (int) $this->Buscar([
                "colunas" => "quantidade",
                "tabela" => "item_usuario",
                "pesquisa" => "id_usuario = :id AND id_item_loja = :item",
                "valores" => [
                    "id" => $idUsuario, 
                    "item" => $item["id_item_loja"]
                ]
            ])["quantidade"];

            // finaliza a transaction
            $this->FinalizarTransaction();

            // retorna a quantidade que o usuario tem e os pokedolares do usuario
            return [
                "pokedolares" => $saldo - $total,
                "quantidade" => $novaQuantidade
            ];
        } 
        
        catch (Throwable $erro) 
        {
            // reverte o resultado
            $this->ReverterTransaction();
            throw $erro;
        }
    }
}
