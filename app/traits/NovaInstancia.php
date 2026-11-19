<?php

trait NovaInstancia {
    public static function NovaInstancia(array $argumentos = []): static
    {
        if ($argumentos === []) {
            $construtor = (new ReflectionClass(static::class))->getConstructor();

            if ($construtor !== null) {
                foreach ($construtor->getParameters() as $parametro) {
                    $tipo = $parametro->getType();

                    if ($tipo instanceof ReflectionNamedType
                        && $tipo->getName() === PDO::class
                        && class_exists(Conexao::class)
                    ) {
                        $argumentos[$parametro->getName()] = Conexao::getConexao();
                        break;
                    }
                }
            }
        }

        return new static(...$argumentos);
    }
}