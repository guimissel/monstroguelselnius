<?php

class ProgressoController
{
    private const TAMANHO_MAXIMO_ARQUIVO = 10485760;

    // GET api/progresso/exportar: baixa os dados de progresso da conta autenticada.
    public function exportar(): void
    {
        $idUsuario = ($_SESSION["id"] ?? null);
        if (!isset($idUsuario))
        {
            http_response_code(401);
            header("Content-Type: application/json; charset=utf-8");
            echo json_encode([
                "status" => 401,
                "mensagem" => "Usuario nao autenticado"
            ]);
            return;
        }

        try
        {
            $dao = PerfilDAO::novaInstancia();
            $treinador = $dao->treinador($idUsuario);
            if ($treinador === null)
            {
                http_response_code(404);
                header("Content-Type: application/json; charset=utf-8");
                echo json_encode([
                    "status" => 404,
                    "mensagem" => "Jogador nao encontrado"
                ]);
                return;
            }

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

            http_response_code(500);
            header("Content-Type: application/json; charset=utf-8");
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel exportar o progresso"
            ]);
        }
    }

    public function importar(): void
    {
        header("Content-Type: application/json; charset=utf-8");

        $idUsuario = $_SESSION["id"] ?? null;
        if (!isset($idUsuario)) {
            http_response_code(401);
            echo json_encode(["status" => 401, "mensagem" => "Usuario nao autenticado"]);
            return;
        }

        $arquivo = $_FILES["arquivo"] ?? null;
        if (!is_array($arquivo) || ($arquivo["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $erroUpload = is_array($arquivo) ? ($arquivo["error"] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
            $status = in_array($erroUpload, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 413 : 400;
            http_response_code($status);
            echo json_encode([
                "status" => $status,
                "mensagem" => $status === 413 ? "O arquivo excede o tamanho permitido" : "Selecione um arquivo JSON valido"
            ]);
            return;
        }

        $caminho = $arquivo["tmp_name"] ?? "";
        $tamanho = (int) ($arquivo["size"] ?? 0);
        if (!is_uploaded_file($caminho) || $tamanho < 1 || $tamanho > self::TAMANHO_MAXIMO_ARQUIVO) {
            $status = $tamanho > self::TAMANHO_MAXIMO_ARQUIVO ? 413 : 400;
            http_response_code($status);
            echo json_encode([
                "status" => $status,
                "mensagem" => $status === 413 ? "O arquivo excede o limite de 10 MB" : "Arquivo enviado invalido"
            ]);
            return;
        }

        $conteudo = file_get_contents($caminho);
        if ($conteudo === false) {
            http_response_code(400);
            echo json_encode(["status" => 400, "mensagem" => "Nao foi possivel ler o arquivo enviado"]);
            return;
        }

        try {
            $dados = json_decode($conteudo, true, 512, JSON_THROW_ON_ERROR);
            $progresso = $this->validarArquivo($dados);
            $dao = PerfilDAO::novaInstancia();
            $dao->importarProgresso((int) $idUsuario, ...$progresso);

            echo json_encode([
                "status" => 200,
                "mensagem" => "Progresso importado. O progresso anterior desta conta foi substituido."
            ], JSON_UNESCAPED_UNICODE);
        } catch (JsonException | InvalidArgumentException | DomainException $e) {
            http_response_code(400);
            echo json_encode(["status" => 400, "mensagem" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log("ProgressoController::importar: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => 500, "mensagem" => "Nao foi possivel importar o progresso"]);
        }
    }

    private function validarArquivo(mixed $dados): array
    {
        if (!is_array($dados)
            || ($dados["formato"] ?? null) !== "monstroguelselnius-progresso"
            || ($dados["versao"] ?? null) !== 1
            || !is_array($dados["treinador"] ?? null)
            || !is_array($dados["colecao"] ?? null)
            || !is_array($dados["itens"] ?? null)
            || !is_array($dados["historico"] ?? null)
        ) {
            throw new InvalidArgumentException("Arquivo de progresso invalido ou versao nao suportada");
        }

        $treinador = $dados["treinador"];
        $saldo = $treinador["pokedolares"] ?? null;
        if (!is_int($saldo) || $saldo < 0 || $saldo > 2147483647) {
            throw new InvalidArgumentException("Saldo de pokedolares invalido");
        }

        if (count($dados["colecao"]) > 1000 || count($dados["itens"]) > 500 || count($dados["historico"]) > 50000) {
            throw new InvalidArgumentException("O arquivo contem dados acima dos limites permitidos");
        }

        $colecao = [];
        $idsColecao = [];
        $equipeMarcada = [];
        foreach ($dados["colecao"] as $pokemon) {
            if (!is_array($pokemon)
                || !is_int($pokemon["id"] ?? null)
                || $pokemon["id"] < 1
                || !is_bool($pokemon["naEquipe"] ?? null)
            ) {
                throw new InvalidArgumentException("Dados de Pokémon invalidos");
            }
            $id = $pokemon["id"];
            if (isset($idsColecao[$id])) {
                throw new InvalidArgumentException("A colecao contem Pokémon duplicados");
            }
            $idsColecao[$id] = true;
            if ($pokemon["naEquipe"]) {
                $equipeMarcada[] = $id;
            }
            $colecao[] = [
                "id" => $id,
                "conquistadoEm" => $this->dataHoraValida($pokemon["conquistadoEm"] ?? null)
            ];
        }

        $equipeExportada = $treinador["equipe"]["pokemons"] ?? null;
        if ($equipeExportada !== null) {
            if (!is_array($equipeExportada)) {
                throw new InvalidArgumentException("Equipe invalida no arquivo");
            }
            $equipe = [];
            foreach ($equipeExportada as $pokemon) {
                if (!is_array($pokemon) || !is_int($pokemon["id"] ?? null) || $pokemon["id"] < 1) {
                    throw new InvalidArgumentException("Dados da equipe invalidos");
                }
                $equipe[] = $pokemon["id"];
            }
            if (count(array_unique($equipe)) !== count($equipe)
                || count($equipe) > 3
                || array_diff($equipe, array_keys($idsColecao))
                || array_diff($equipeMarcada, $equipe)
                || array_diff($equipe, $equipeMarcada)
            ) {
                throw new InvalidArgumentException("Equipe e colecao nao correspondem");
            }
        } else {
            $equipe = $equipeMarcada;
        }
        if (count($equipe) !== 0 && count($equipe) !== 3) {
            throw new InvalidArgumentException("A equipe precisa estar vazia ou conter exatamente 3 Pokémon");
        }

        $itens = [];
        foreach ($dados["itens"] as $item) {
            if (!is_array($item)
                || !is_int($item["quantidade"] ?? null)
                || $item["quantidade"] < 0
                || $item["quantidade"] > 1000000
                || (!is_string($item["chave"] ?? null) && !is_string($item["nome"] ?? null))
            ) {
                throw new InvalidArgumentException("Dados de item invalidos");
            }
            $chave = $item["chave"] ?? null;
            $nome = $item["nome"] ?? null;
            if (($chave !== null && ($chave === "" || strlen($chave) > 30))
                || ($nome !== null && ($nome === "" || strlen($nome) > 60))
            ) {
                throw new InvalidArgumentException("Identificador de item invalido");
            }
            $itens[] = ["chave" => $chave, "nome" => $nome, "quantidade" => $item["quantidade"]];
        }

        $historico = [];
        foreach ($dados["historico"] as $batalha) {
            if (!is_array($batalha)
                || !in_array($batalha["status"] ?? null, ["vitoria", "derrota", "fuga"], true)
                || !is_int($batalha["recompensa"] ?? null)
                || $batalha["recompensa"] < 0
                || $batalha["recompensa"] > 2147483647
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

    private function dataHoraValida(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        if (!is_string($valor)) {
            throw new InvalidArgumentException("Data do arquivo invalida");
        }
        $data = DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", $valor);
        $erros = DateTimeImmutable::getLastErrors();
        if ($data === false
            || ($erros !== false && ($erros["warning_count"] > 0 || $erros["error_count"] > 0))
            || $data->format("Y-m-d H:i:s") !== $valor
        ) {
            throw new InvalidArgumentException("Data do arquivo invalida");
        }
        return $valor;
    }
}
