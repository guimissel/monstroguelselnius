<?php
/**
 * Essa classe é herdada por todos arquivos em /dao
 * 
 * @author Miguel Granville
 * @method void __construct(PDO $pdo)
 * @method mixed Buscar (array $info, bool $multiplasLinhas = false)
 * @method mixed Inserir (array $info, bool $retornarUltimoId = false)
 * @method void Atualizar (array $info)
 * @method void Excluir (array $info)
 * @method void IniciarTransaction ()
 * @method void FinalizaTransaction ()
 */
class DAO
{
    use NovaInstancia;

    /**
     * @var PDO $pdo pdo para executar comando no banco de dados
     */
    protected PDO $pdo;

    // Guarda a conexão recebida para que as consultas da subclasse usem o mesmo recurso.
    /**
     * @author Miguel Granville
     * @param PDO $pdo objeto de pdo definido no construtor
     */
    public function __construct(PDO $pdo)
    {
        // define o pdo, recebendo como argumento
        $this->pdo = $pdo;
    }

    /** 
     * Faz uma busca no banco de dados
     * 
     *   @author Miguel Granville
     *   @param bool $multiplasLinhas define retorno de multiplas linhas
     *   @param array $info array associativo com valores da busca: colunas, tabela, pesquisa e valores
     *   @return mixed
    **/
    public function Buscar (array $info, bool $multiplasLinhas = false): mixed
    {
        // colunas pode ser um array associativo
        // colunas pode ser tambem string
        // se for null o valor sera "*"
        $colunas = $info["colunas"] ?? "*";

        // valores para serem inseridos
        $valores = $info["valores"];
        $tabela = $info["tabela"];
        $pesquisa = $info['pesquisa'];

        // caso alguma das variaveis necessarias não seja definida
        if (!isset($tabela, $valores, $pesquisa, $colunas))
        {
            // trata a exceção
            throw new RuntimeException("Existem variaveis não declaradas na lista associativa (DAO linha 197)");
            return null;
        }

        // caso a tabela venha em lista para buscas com join
        if(is_array($tabela))
        {
            // transforma em uma string
            $tabela = implode(" ", $tabela);
        }

        // string para as colunas
        $colunasString = "";

        // se não for definido é utilizado um valor padrão
        if (is_array($colunas)) 
        {
            // construir para a query
            $colunasString = implode(",", $colunas);
        }

        else 
        {
            // caso não seja um array
            $colunasString = $colunas; 
        }

        try {
            // sql a ser executado
            $sql = "SELECT $colunasString FROM $tabela WHERE $pesquisa";
    
            // prepara o sql
            $preparar = $this->pdo->prepare($sql);
    
            // caso de erro
            if ($preparar === false) {
                throw new RuntimeException("Não foi possível preparar a consulta.");
            }

            // precisa ser uma lista 
            if (!is_array($valores))
            {
                throw new RuntimeException("Não é posssivel utilizar valores que não estão em uma lista associativo");
            }

            // executa com os valores passados
            $preparar->execute($valores);
    
            // se for falso ou null vai executar um fetch simples
            if ($multiplasLinhas === false || !isset($multiplasLinhas))
            {
                return $preparar->fetch();
            }
    
            // se passar pelo if vai ser fetchAll
            return $preparar->fetchAll();
        }
        
        // caso tenha erro no banco de dados
        catch (PDOException $e) {
            throw new PDOException("Não foi possivel executar a busca (PDOException)", 1, $e);
        }
    }

    /**
     * Faz uma inserção no banco de dados
     * 
     * @author Miguel Granville
     * @param array $info array associativo com valores da busca: colunas, tabela e valores
     * @param bool $retornarUltimoId define se a função vai retornar o ultimo id (int)
     * @return mixed retorna o ultimo id se definido e null se não definido
     */
    public function Inserir (array $info, bool $retornarUltimoId = false): mixed
    {
        // colunas que vão ser envolvidas na inserção
        $colunas = $info["colunas"] ?? "id";

        // valores para serem inseridos
        $valores = $info["valores"];
        $tabela = $info["tabela"];
        $extra = $info["extra"] ?? "";

        // caso alguma das variaveis necessarias não seja definida
        if (!isset($tabela, $valores, $colunas))
        {
            // trata a exceção
            throw new RuntimeException("Existem variaveis não declaradas na lista associativa (DAO linha 197)");
            return null;
        }

        // se extra for uma lista
        if (is_array($extra))
        {
            // tranforma em uma string
            $extra = implode(" ", $extra);
        }

        // string para as colunas
        $colunasString = "";
        $colunasValoresString = "";

        // se não for definido é utilizado um valor padrão
        if (is_array($colunas)) 
        {

            // construir as colunas para a query
            $colunasString = implode(",", $colunas);

            // gambiarrinha
            // essa gambiarra pega o valor vindo de $colunasString, exemplo: nome,senha
            // ela adiciona dois pontos no começo -> :nome,senha
            // e substitui todas virgulas por , : -> :nome, :senha
            $colunasValoresString = str_replace(
                ",", 
                ", :",
                (":" . $colunasString)
            );
        }

        else 
        {
            // caso não seja um array
            $colunasString = $colunas; 
            $colunasValoresString = ":$colunas";
        }

        // tenta executar a inserção
        try {

            // sql que sera executado no banco de dados
            $sql = "INSERT INTO $tabela ($colunasString) VALUES ($colunasValoresString) $extra";

            // prepara o pdo
            $preparar = $this->pdo->prepare($sql);

            // executa com os valores
            $preparar->execute($valores);

            // se precisar do ultimo id inserido
            if ($retornarUltimoId  == true)
            {
                // retorna em inteiro o ultimo id inserido
                return (int) $this->pdo->lastInsertId();
            }

            // retorna um valor nulo caso não seja definido para retornar o ultimo id inserido
            return null;
        } 
        
        // trata exceções no banco de dados
        catch (PDOException $e) 
        {
            throw new PDOException("Não foi possivel executar a inserção (PDOException)", 1, $e);
            return null;
        }
    }

    /**
     * Atualiza algum dado no banco de dados
     * 
     * @author Miguel Granville
     * @param array $info array associativo com valores da busca: colunas, tabela, valores e condicao
     * @return void não retorna nenhuma garantia de atualização
    */
    public function Atualizar (array $info): void
    {
        // colunas devem vir em uma lista
        $colunas = $info["colunas"];

        // resgatando valores vindo da lista info
        $tabela = $info["tabela"];
        $valores = $info["valores"];
        $condicao = $info["condicao"];

        // caso alguma das variaveis necessarias não seja definida
        if (!isset($tabela, $valores, $condicao, $colunas))
        {
            // trata a exceção
            throw new RuntimeException("Existem variaveis não declaradas na lista associativa (DAO linha 197)");
            return;
        }

        // se não for uma lista
        if (!is_array($colunas))
        {
            // trata a exceção
            throw new RuntimeException("As colunas devem ser uma lista (DAO linha 205");
            return;
        }

        // precisa ser uma lista 
        if (!is_array($valores))
        {
            throw new RuntimeException("Não é posssivel utilizar valores que não estão em uma lista associativo");
            return;
        }

        // lista de SET para a query
        $listaSET = [];

        // percorre a lista de colunas
        foreach ($colunas as $chave => $valor)
        {
            // define uma string para o set
            // exemplo: $chave = nome & $valor = COALESCE(:nome, nome)
            // resultado: nome = COALESCE(:nome, nome)
            $stringColuna = "$chave = $valor";

            // adiciona a lista
            array_push($listaSET, $stringColuna);
        }

        // separa com virgulas ocorrências
        $stringSet = implode(", ", $listaSET);

        // cria o sql para ser executado
        $sql = "UPDATE $tabela SET $stringSet WHERE $condicao";

        // tenta executar o código do pdo
        try 
        {
            // prepara usando o pdo
            $preparar = $this->pdo->prepare($sql);

            // caso de erro
            if ($preparar === false) 
            {
                // trata a execeção
                throw new RuntimeException("Não foi possível preparar a consulta.");
                return;
            }

            // executa usando os valores
            $preparar->execute($valores);
        }

        // exceção de banco de dados
        catch (PDOException $e)
        {
            // trata a exceção
            throw new PDOException("Não foi possivel executar a atualização (PDOException)", 1, $e);
            return;
        }
    }

    /**
     * Exclui algum dado do banco de dados
     * 
     * @author Miguel Granville
     * @param array $info array associativo com valores da busca: tabela, valores e condicao
     * @return void não retorna nenhuma garantia de exclusão
    */
    public function Excluir (array $info): void
    {
        // resgatando valores vindo da lista info
        $tabela = $info["tabela"];
        $valores = $info["valores"];
        $condicao = $info["condicao"];

        // caso alguma das variaveis necessarias não seja definida
        if (!isset($tabela, $valores, $condicao))
        {
            // trata a exceção
            throw new RuntimeException("Existem variaveis não declaradas na lista associativa (DAO linha 197)");
            return;
        }

        // precisa ser uma lista 
        if (!is_array($valores))
        {
            throw new RuntimeException("Não é posssivel utilizar valores que não estão em uma lista associativo");
            return;
        }
        
        // sql que sera executado no banco de dados
        $sql = "DELETE FROM $tabela WHERE $condicao";

        // tenta executar o código
        try 
        {

            // prepara o pdo para executar
            $preparar = $this->pdo->prepare($sql);

            // executa o código
            $preparar->execute($valores);

            return;
        }

        // exceção de banco de dados
        catch (PDOException $e)
        {
            // trata a exceção
            throw new PDOException("Não foi possivel executar a exclusão (PDOException)", 1, $e);
        }
    }

    /**
     * Iniciar uma nova transação no banco de dados
     * @author Miguel Granville
     * @return bool retorna se foi um sucess
     */
    public function IniciarTransaction (): bool
    {
        // verifica se já existe uma transaction
        if ($this->pdo->inTransaction())
        {
            // trata uma nova exceção
            throw new PDOException("Não é possivel iniciar uma transaction, motivo: já existe uma transaction");
            return false;
        }

        // tenta iniciar a transaction
        try {

            // retorna se foi um sucesso ou não
            return $this->pdo->beginTransaction();
        } 
        
        // trata a exceção
        catch (PDOException $e) 
        {
            error_log(
                "Não foi possivel executar o inicio de uma transaction " . $e->getMessage(), 
                $e->getCode()
            );
            throw $e;
        }

        return false;
    }

    /**
     * Finaliza a transaction atual
     * @author Miguel Granville
     * @return bool retorna se foi um sucess
     */
    public function FinalizarTransaction (): bool
    {
        // verifica se não existe uma transaction
        if (!$this->EstaEmTransaction())
        {
            // trata uma nova exceção
            throw new PDOException("Não é possivel iniciar uma transaction, motivo: não existe uma transaction");
            return false;
        }

        // tenta finalizar a transaction
        try {

            // retorna se foi um sucesso ou não
            return $this->pdo->commit();
        } 
        
        // trata a exceção
        catch (PDOException $e) 
        {
            error_log(
                "Não foi possivel executar o inicio de uma transaction " . $e->getMessage(), 
                $e->getCode()
            );
            throw $e;
        }

        return false;
    }

    /**
     * Verifica a transaction atual
     * @author Miguel Granville
     * @return bool retorna se foi um sucesso
     */
    public function EstaEmTransaction() :bool
    {
        // retorna se esta
        return $this->pdo->inTransaction();
    }

    /**
     * Reverte a transaction atual
     * @author Miguel Granville
     * @return bool retorna se foi um sucess
     */
    public function ReverterTransaction (): bool
    {
        // verifica se não existe uma transaction
        if (!$this->EstaEmTransaction())
        {
            // trata uma nova exceção
            throw new PDOException("Não é possivel iniciar uma transaction, motivo: não existe uma transaction");
            return false;
        }

        // tenta finalizar a transaction
        try {

            // retorna se foi um sucesso ou não
            return $this->pdo->rollBack();
        } 
        
        // trata a exceção
        catch (PDOException $e) 
        {
            error_log(
                "Não foi possivel executar o inicio de uma transaction " . $e->getMessage(), 
                $e->getCode()
            );
            throw $e;
        }

        return false;
    }
}