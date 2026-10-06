<?php

// caminho base das paginas
$basePath = "../public_html/pages/";

// classe abstrata com funções que retornam arquivos html
abstract class RouteSwitch 
{
    // GET /
    protected function home ()
    {
        require __DIR__ . $basePath . "home.html";
    }
}