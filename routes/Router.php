<?php

require_once __DIR__ . "RouteSwitch.php";

class Router extends RouteSwitch 
{
    public function run (string $requestPath)
    {
        // remove a barra da requisição
        $route = substr($requestPath, 1);

        // retorno do GET /
        if ($route === "") $this.home();

        // retorno do GET /*
        else $this.$route();
    }
}