<?php

class PerfilController
{
    // GET api/perfil: tudo da tela de perfil do jogador logado
    public function ver(): void
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

        try
        {
            $dao = new PerfilDAO(Conexao::getConexao());
            $treinador = $dao->treinador((int) $meuId);

            if ($treinador === null)
            {
                http_response_code(404);
                echo json_encode([
                    "status" => 404,
                    "mensagem" => "Jogador nao encontrado"
                ]);
                return;
            }

            $placar = $dao->placar((int) $meuId);

            echo json_encode([
                "status" => 200,
                "treinador" => $treinador->paraArray(),
                "placar" => $placar,
                "posicao" => $dao->posicao($placar["vitorias"]),
                "ranking" => $dao->ranking(),
                "historico" => $dao->historico((int) $meuId),
                "colecao" => $dao->colecao((int) $meuId),
                "itens" => $dao->itens((int) $meuId)
            ]);
            return;
        }

        catch (Exception $e)
        {
            error_log("PerfilController::ver: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "mensagem" => "Nao foi possivel carregar o perfil. Confira se o app/database/schema.sql foi executado."
            ]);
            return;
        }
    }
}
