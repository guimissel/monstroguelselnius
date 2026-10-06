<?php

require_once __DIR__ . "/RouteSwitch.php";

class Roteador extends TrocarRota
{
    public function run (string $caminhoRequisitado)
    {
        // remove a barra da requisição
        $rota = substr($caminhoRequisitado, 1);

        // retorno do GET /
        if ($rota === "") $this->home();

        // retorno do GET /*
        else $this->$rota();
    }
}