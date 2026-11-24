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
/**
 * @author Miguel Granville
 * @method _construct (array $restricaoAcesso, string $rota, string $redirecionar = "/monstroguelselnius/login")
 * @method string getRedirecionamento()
 * @method array getRestricao()
 * @method string acessar()
 */
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
            Log::EscreverArquivo(
                "[" . date("Y-m-d H:i:s") . "] [AVISO] AutenticacaoController::acessar: Acesso negado a rota '{$this->rota}' para papel '{$role}'" . PHP_EOL,
                "logs/sistema.log",
                ["FILE_APPEND", "LOCK_EX"]
            );

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