<?php

/**
 * Controla a exportação e importação do progresso do jogador
 * 
 * @author Miguel Granville / Vinicius Grub
 * @method void exportar()
 * @method void importar()
 */
class ProgressoController extends Controller
{
    private const TAMANHO_MAXIMO_ARQUIVO = 10485760;

    /**
     * Exporta o progresso da conta autenticada.
     * 
     * @author Miguel Granville / Vinicius Grub
     * @return void
     */
    public function exportar(): void
    {
        $idUsuario = ($_SESSION["id"] ?? null);
        
        // A exportação nunca deve aceitar um identificador de conta vindo do formulário.
        if (!isset($idUsuario))
        {
            Controller::RespostaJson()
                ->status(401)
                ->mensagem("Usuario nao autenticado")
                ->enviar();
            return;
        }

        try
        {
            $dao = PerfilDAO::NovaInstancia();
            $treinador = $dao->treinador($idUsuario);
            
            // Sem o perfil-base, os demais dados não formam um arquivo de progresso válido.
            if ($treinador === null)
            {
                Controller::RespostaJson()
                    ->status(404)
                    ->mensagem("Jogador nao encontrado")
                    ->enviar();
                return;
            }

            // O formato do arquivo exportado não utiliza a estrutura de RespostaJson
            $json = json_encode([
                "formato" => "monstroguelselnius-progresso",
                "versao" => 1,
                "exportadoEm" => (new DateTimeImmutable())->format(DATE_ATOM),
                "treinador" => $treinador->paraArray(),
                "placar" => $dao->placar($idUsuario),
                "colecao" => $dao->colecao($idUsuario),
                "itens" => $dao->itens($idUsuario),
                "historico" => $dao->historico($idUsuario, null, true)
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            header("Content-Type: application/json; charset=utf-8");
            header("Content-Disposition: attachment; filename=\"progresso-{$idUsuario}.json\"");
            header("Cache-Control: no-store, private");
            echo $json;
        }
        catch (Exception $e)
        {
            error_log("ProgressoController::exportar: " . $e->getMessage());

            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Nao foi possivel exportar o progresso")
                ->enviar();
        }
    }

    /**
     * Importa e substitui o progresso enviado pelo jogador.
     * 
     * @author Miguel Granville / Vinicius Grub
     * @return void
     */
    public function importar(): void
    {
        $idUsuario = $_SESSION["id"] ?? null;
        
        // Vincula a restauração à conta autenticada em vez de confiar no conteúdo do arquivo.
        if (!isset($idUsuario)) {
            Controller::RespostaJson()
                ->status(401)
                ->mensagem("Usuario nao autenticado")
                ->enviar();
            return;
        }

        $arquivo = $_FILES["arquivo"] ?? null;
        
        // Distingue tamanho excedido de outros problemas reportados pelo mecanismo de upload.
        if (!is_array($arquivo) || ($arquivo["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) 
        {
            $erroUpload = is_array($arquivo) ? ($arquivo["error"] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
            $status = in_array($erroUpload, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 413 : 400;
            
            Controller::RespostaJson()
                ->status($status)
                ->mensagem($status === 413 ? "O arquivo excede o tamanho permitido" : "Selecione um arquivo JSON valido")
                ->enviar();
            return;
        }

        $caminho = $arquivo["tmp_name"] ?? "";
        $tamanho = (int) ($arquivo["size"] ?? 0);
        
        // Só lê uploads temporários válidos e limita o volume processado pela aplicação.
        if (!is_uploaded_file($caminho) || $tamanho < 1 || $tamanho > self::TAMANHO_MAXIMO_ARQUIVO) 
        {
            $status = $tamanho > self::TAMANHO_MAXIMO_ARQUIVO ? 413 : 400;
            
            Controller::RespostaJson()
                ->status($status)
                ->mensagem($status === 413 ? "O arquivo excede o limite de 10 MB" : "Arquivo enviado invalido")
                ->enviar();
            return;
        }

        // Separa falha de leitura de erro de sintaxe no JSON enviado.
        $conteudo = file_get_contents($caminho);
        if ($conteudo === false) 
        {
            Controller::RespostaJson()
                ->status(400)
                ->mensagem("Nao foi possivel ler o arquivo enviado")
                ->enviar();
            return;
        }

        try 
        {
            // Valida todo o arquivo antes de delegar a substituição transacional ao DAO.
            $dados = json_decode($conteudo, true, 512, JSON_THROW_ON_ERROR);
            $progresso = $this->validarArquivo($dados);
            $dao = PerfilDAO::NovaInstancia();
            $dao->importarProgresso((int) $idUsuario, ...$progresso);

            Controller::RespostaJson()
                ->status(200)
                ->mensagem("Progresso importado. O progresso anterior desta conta foi substituido.")
                ->enviar();
        } 
        
        catch (JsonException | InvalidArgumentException | DomainException $e) 
        {
            // Erros de formato ou regras de importação são devolvidos como entrada inválida.
            Controller::RespostaJson()
                ->status(400)
                ->mensagem($e->getMessage())
                ->enviar();
            return;
        } 
        
        catch (Throwable $e) 
        {
            // Falhas inesperadas ficam no log e não revelam detalhes da persistência.
            error_log("ProgressoController::importar: " . $e->getMessage());
            
            // resposta em json
            Controller::RespostaJson()
                ->status(500)
                ->mensagem("Nao foi possivel importar o progresso")
                ->enviar();
            return;
        }
    }

    /**
     * Confere estrutura, limites e referências internas antes de aceitar dados importados.
     * 
     * @author Miguel Granville / Vinicius Grub
     * @param mixed $dados Dados lidos do JSON
     * @return array Progresso validado
     */
    private function validarArquivo(mixed $dados): array
    {
        // Exige o envelope e as coleções definidas pelo formato de exportação suportado.
        if (!is_array($dados)
            || ($dados["formato"] ?? null) !== "monstroguelselnius-progresso"
            || ($dados["versao"] ?? null) !== 1
            || !is_array($dados["treinador"] ?? null)
            || !is_array($dados["colecao"] ?? null)
            || !is_array($dados["itens"] ?? null)
            || !is_array($dados["historico"] ?? null)
        ) 
        {
            throw new InvalidArgumentException("Arquivo de progresso invalido ou versao nao suportada");
        }

        $treinador = $dados["treinador"];
        $saldo = $treinador["pokedolares"] ?? null;

        // O saldo será gravado em coluna inteira, por isso precisa respeitar seu intervalo.
        if (!is_int($saldo) || $saldo < 0 || $saldo > 2147483647) 
        {
            throw new InvalidArgumentException("Saldo de pokedolares invalido");
        }

        // Limites por coleção reduzem o custo de processar arquivos excessivamente grandes.
        if (count($dados["colecao"]) > 1000 
            || count($dados["itens"]) > 500 
            || count($dados["historico"]) > 50000
        ) {
            throw new InvalidArgumentException("O arquivo contem dados acima dos limites permitidos");
        }

        $colecao = [];
        $idsColecao = [];
        $equipeMarcada = [];

        // Rejeita IDs repetidos e guarda a marcação de equipe para compará-la mais adiante.
        foreach ($dados["colecao"] as $pokemon) {
            if (!is_array($pokemon)
                || !is_int($pokemon["id"] ?? null)
                || $pokemon["id"] < 1
                || !is_bool($pokemon["naEquipe"] ?? null)
            ) {
                throw new InvalidArgumentException("Dados de Pokémon invalidos");
            }
            $id = $pokemon["id"];
            if (isset($idsColecao[$id])) 
            {
                throw new InvalidArgumentException("A colecao contem Pokémon duplicados");
            }

            //  Armazena true na posição $idsColecao[$id].
            $idsColecao[$id] = true;
            if ($pokemon["naEquipe"]) 
            {
                $equipeMarcada[] = $id;
            }

            $colecao[] = [
                "id" => $id,
                "conquistadoEm" => $this->dataHoraValida($pokemon["conquistadoEm"] ?? null)
            ];
        }

        $equipeExportada = $treinador["equipe"]["pokemons"] ?? null;

        // Aceita a equipe explícita do formato atual e mantém compatibilidade com arquivos sem ela.
        if ($equipeExportada !== null) 
        {
            if (!is_array($equipeExportada)) 
            {
                throw new InvalidArgumentException("Equipe invalida no arquivo");
            }

            $equipe = [];
            foreach ($equipeExportada as $pokemon) 
            {
                if (!is_array($pokemon) || !is_int($pokemon["id"] ?? null) || $pokemon["id"] < 1) 
                {
                    throw new InvalidArgumentException("Dados da equipe invalidos");
                }
                $equipe[] = $pokemon["id"];
            }

            if (count(array_unique($equipe)) !== count($equipe)
                //  Acrescenta à validação a condição || count($equipe) > 3.
                || count($equipe) > 3
                //  Acrescenta à validação a condição || array_diff($equipe, array_keys($idsColecao)).
                || array_diff($equipe, array_keys($idsColecao))
                //  Acrescenta à validação a condição || array_diff($equipeMarcada, $equipe).
                || array_diff($equipeMarcada, $equipe)
                //  Acrescenta à validação a condição || array_diff($equipe, $equipeMarcada).
                || array_diff($equipe, $equipeMarcada)
            ) 
            {
                throw new InvalidArgumentException("Equipe e colecao nao correspondem");
            }
        } 
        
        else 
        {
            $equipe = $equipeMarcada;
        }

        // A regra do jogo admite uma equipe vazia durante a preparação ou exatamente três Pokémon.
        if (count($equipe) !== 0 && count($equipe) !== 3) 
        {
            throw new InvalidArgumentException("A equipe precisa estar vazia ou conter exatamente 3 Pokémon");
        }

        $itens = [];
        // Normaliza chaves e nomes sem aceitar quantidades ou identificadores fora dos limites.
        foreach ($dados["itens"] as $item) 
        {
            if (!is_array($item)
                //  Acrescenta à validação a condição || !is_int($item["quantidade"] ?? null).
                || !is_int($item["quantidade"] ?? null)
                //  Acrescenta à validação a condição || $item["quantidade"] < 0.
                || $item["quantidade"] < 0
                //  Acrescenta à validação a condição || $item["quantidade"] > 1000000.
                || $item["quantidade"] > 1000000
                //  Acrescenta à validação a condição || (!is_string($item["chave"] ?? null) && !is_string($item["nome"] ?? null)).
                || (!is_string($item["chave"] ?? null) && !is_string($item["nome"] ?? null))
            ) {
                throw new InvalidArgumentException("Dados de item invalidos");
            }

            $chave = $item["chave"] ?? null;
            $nome = $item["nome"] ?? null;

            if (($chave !== null && ($chave === "" || strlen($chave) > 30))
                //  Acrescenta à validação a condição || ($nome !== null && ($nome === "" || strlen($nome) > 60)).
                || ($nome !== null && ($nome === "" || strlen($nome) > 60))
            ) {
                throw new InvalidArgumentException("Identificador de item invalido");
            }
            $itens[] = ["chave" => $chave, "nome" => $nome, "quantidade" => $item["quantidade"]];
        }

        $historico = [];
        // Verifica datas e recompensas para que a restauração não grave partidas incoerentes.
        foreach ($dados["historico"] as $batalha) {
            if (!is_array($batalha)
                //  Acrescenta à validação a condição || !in_array($batalha["status"] ?? null, ["vitoria", "derrota", "fuga"], true).
                || !in_array($batalha["status"] ?? null, ["vitoria", "derrota", "fuga"], true)
                //  Acrescenta à validação a condição || !is_int($batalha["recompensa"] ?? null).
                || !is_int($batalha["recompensa"] ?? null)
                //  Acrescenta à validação a condição || $batalha["recompensa"] < 0.
                || $batalha["recompensa"] < 0
                //  Acrescenta à validação a condição || $batalha["recompensa"] > 2147483647.
                || $batalha["recompensa"] > 2147483647
                //  Acrescenta à validação a condição || !is_bool($batalha["chefe"] ?? false).
                || !is_bool($batalha["chefe"] ?? false)
            ) {
                throw new InvalidArgumentException("Dados de batalha invalidos");
            }

            $iniciadaEm = $this->dataHoraValida($batalha["iniciadaEm"] ?? null);
            if ($iniciadaEm === null) {
                throw new InvalidArgumentException("Data de inicio de batalha invalida");
            }
            $finalizadaEm = $this->dataHoraValida($batalha["finalizadaEm"] ?? null);
            $duracao = $batalha["duracao"] ?? null;
            if ($duracao !== null && (!is_int($duracao) || $duracao < 0 || $duracao > 315576000)) {
                throw new InvalidArgumentException("Duracao de batalha invalida");
            }
            if ($finalizadaEm === null && $duracao !== null) {
                $inicio = DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", $iniciadaEm);
                $finalizadaEm = $inicio->modify("+" . $duracao . " seconds")->format("Y-m-d H:i:s");
            }
            if ($finalizadaEm !== null && $finalizadaEm < $iniciadaEm) {
                throw new InvalidArgumentException("Datas de batalha invalidas");
            }

            $historico[] = [
                "status" => $batalha["status"],
                "recompensa" => $batalha["recompensa"],
                "iniciadaEm" => $iniciadaEm,
                "finalizadaEm" => $finalizadaEm,
                "chefe" => $batalha["chefe"] ?? false
            ];
        }

        return [$saldo, $colecao, $equipe, $itens, $historico];
    }

    /**
     * Normaliza apenas datas no formato exportado, rejeitando valores impossíveis.
     * 
     * @author Miguel Granville / Vinicius Grub
     * @param mixed $valor Data para validar
     * @return string|null
     */
    private function dataHoraValida(mixed $valor): ?string
    {
        if ($valor === null) 
        {
            return null;
        }
        if (!is_string($valor)) 
        {
            throw new InvalidArgumentException("Data do arquivo invalida");
        }

        $data = DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", $valor);
        $erros = DateTimeImmutable::getLastErrors();
        
        if ($data === false
            //  Acrescenta à validação a condição || ($erros !== false && ($erros["warning_count"] > 0 || $erros["error_count"] > 0)).
            || ($erros !== false && ($erros["warning_count"] > 0 || $erros["error_count"] > 0))
            //  Acrescenta à validação a condição || $data->format("Y-m-d H:i:s") !== $valor.
            || $data->format("Y-m-d H:i:s") !== $valor
        ) {
            throw new InvalidArgumentException("Data do arquivo invalida");
        }
        return $valor;
    }
}