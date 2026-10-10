
<?php
session_start();
enum Acesso: string
{
    case VISITANTE = "visitante";
    case USUARIO = "usuario";
    case ADMIN = "admin";
}

class AutenticacaoController
{
    private array $restricaoAcesso;
    private string $rota;
    private string $redirecionar;

    public function __construct(
        array $restricaoAcesso,
        string $rota,
        string $redirecionar = "/monstroguelselnius/login"
    ) {
        $this->restricaoAcesso = $restricaoAcesso;
        $this->rota = $rota;
        $this->redirecionar = $redirecionar;
    }

    public function getRedirecionamento(): string
    {
        return $this->redirecionar;
    }

    public function getRestricao(): array
    {
        return $this->restricaoAcesso;
    }

    public function acessar(): string
    {
        $role = $_SESSION["role"] ?? Acesso::VISITANTE->value;
        $acesso = Acesso::tryFrom($role);

        if (
            $acesso === null ||
            !in_array($acesso, $this->restricaoAcesso, true)
        ) {
            http_response_code(403);
            return json_encode([
                "status" => 403,
                "mensagem" => "nao autorizado",
                "redirecione" => $this->redirecionar
            ]);
        }

        return json_encode([
            "status" => 200,
            "mensagem" => "autorizado",
            "continue" => $this->rota
        ]);
    }
}
