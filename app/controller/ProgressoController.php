<?php

class ProgressoController
{
    // GET api/progresso/exportar: baixa o progresso da conta num arquivo .json
    public function exportar(): void
    {
        $meuId = $_SESSION["id"] ?? null;
        if (!isset($meuId))
        {
            header("Content-Type: application/json");
            http_response_code(401);
            echo json_encode([
                "status" => 401,
                "mensagem" => "usuario nao foi identificado"
            ]);
            return;
        }

        try
        {
            $arquivo = (new ProgressoDAO(Conexao::getConexao()))->exportar((int) $meuId);

            // so letras e numeros no nome do arquivo
            $nome = preg_replace("/[^A-Za-z0-9_-]+/", "", (string) $arquivo["conta"]) ?: "conta";

            header("Content-Type: application/json; charset=utf-8");
            header('Content-Disposition: attachment; filename="progresso-' . $nome . '.json"');
            echo json_encode($arquivo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        catch (Exception $e)
        {
            error_log("ProgressoController::exportar: " . $e->getMessage());

            header("Content-Type: application/json");
            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel salvar o progresso"
            ]);
            return;
        }
    }

    // POST api/progresso/importar (arquivo no campo "arquivo"): troca o progresso da conta pelo do arquivo
    public function importar(): void
    {
        header("Content-Type: application/json");

        $meuId = $_SESSION["id"] ?? null;
        if (!isset($meuId))
        {
            http_response_code(401);
            echo json_encode([
                "status" => 401,
                "mensagem" => "usuario nao foi identificado"
            ]);
            return;
        }

        $envio = $_FILES["arquivo"] ?? null;
        if (!is_array($envio) || ($envio["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $envio["size"] > 1024 * 1024)
        {
            http_response_code(400);
            echo json_encode([
                "status" => 400,
                "mensagem" => "Envie o arquivo de progresso (.json, ate 1 MB)"
            ]);
            return;
        }

        try
        {
            $conteudo = json_decode((string) file_get_contents($envio["tmp_name"]), true);
            $resumo = (new ProgressoDAO(Conexao::getConexao()))->importar((int) $meuId, $conteudo);

            echo json_encode([
                "status" => 200,
                "mensagem" => "Progresso carregado!",
                "resumo" => $resumo
            ]);
            return;
        }

        catch (DomainException $e)
        {
            // erro esperado (arquivo invalido, alterado...)
            http_response_code($e->getCode());
            echo json_encode([
                "status" => $e->getCode(),
                "mensagem" => $e->getMessage()
            ]);
            return;
        }

        catch (Exception $e)
        {
            error_log("ProgressoController::importar: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel carregar o progresso"
            ]);
            return;
        }
    }
}
