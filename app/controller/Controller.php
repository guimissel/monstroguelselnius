<?php

// Base comum dos controladores, com fábrica para criar cada recurso conforme suas dependências.
class Controller
{
    use NovaInstancia;

    public static function RespostaJson (array $json, int $codigo): void
    {
        return;
    }
}
