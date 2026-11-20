<?php

// Fornece construção padronizada para serviços e DAOs com dependências simples.
trait NovaInstancia {
    // Resolve uma dependência PDO por reflexão quando o chamador não fornece argumentos.
    public static function NovaInstancia(array $argumentos = []): static
    {
        // A reflexão só é necessária para chamadas sem argumentos explícitos.
        if ($argumentos === []) {
            $construtor = (new ReflectionClass(static::class))->getConstructor();

            // Localiza parâmetros PDO para manter a criação automática restrita aos DAOs.
            if ($construtor !== null) {
                foreach ($construtor->getParameters() as $parametro) {
                    $tipo = $parametro->getType();

                    // Resolve a conexão apenas quando a classe de configuração está disponível.
                    if ($tipo instanceof ReflectionNamedType
                        //  verifica se o tipo é PDO
                        && $tipo->getName() === PDO::class
                    ) {
                        //  Armazena Conexao::getConexao() na posição $argumentos[$parametro->getName()].
                        $argumentos[$parametro->getName()] = Conexao::getConexao();
                        break;
                    }
                }
            }
        }

        return new static(...$argumentos);
    }
}