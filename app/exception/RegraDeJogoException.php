<?php

// Erro "esperado" do jogo (item que nao existe, batalha ja finalizada, equipe invalida...).
// O codigo da excecao e o status HTTP que o controller devolve para o navegador.
// Extende DomainException, entao qualquer "catch (DomainException)" continua pegando.
class RegraDeJogoException extends DomainException
{
    public function __construct(string $mensagem, int $statusHttp = 400, ?Throwable $anterior = null)
    {
        parent::__construct($mensagem, $statusHttp, $anterior);
    }

    public function getStatusHttp(): int
    {
        return $this->getCode();
    }
}
