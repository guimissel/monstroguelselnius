<?php
session_start();

// Limita os estados de identidade reconhecidos pelo controle de rotas.
enum Acesso: string
{
    case VISITANTE = "visitante";
    case USUARIO = "usuario";
    case ADMIN = "admin";
}

// Centraliza a autorização de rotas e o destino de navegação quando o acesso é recusado.
class AutenticacaoController extends Controller
{
    private array $restricaoAcesso;
    private string $rota;
    private string $redirecionar;

    // Recebe os papeis permitidos e o destino de redirecionamento.
    public function __construct(
        array $restricaoAcesso,
        string $rota,
        string $redirecionar = "/monstroguelselnius/login"
    ) {
        $this->restricaoAcesso = $restricaoAcesso;
        $this->rota = $rota;
        $this->redirecionar = $redirecionar;
    }

    // Busca o destino usado quando o acesso for negado.
    public function getRedirecionamento(): string
    {
        return $this->redirecionar;
    }

    // Retorna os papeis autorizados pela rota.
    public function getRestricao(): array
    {
        return $this->restricaoAcesso;
    }

    // Valida o papel da sessao contra os papeis da rota.
    public function acessar(): string
    {
        // Uma sessão sem papel explícito é tratada como visitante.
        $role = $_SESSION["role"] ?? Acesso::VISITANTE->value;
        $acesso = Acesso::tryFrom($role);

        // Papéis desconhecidos e papéis fora da lista permitida recebem a mesma recusa.
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
