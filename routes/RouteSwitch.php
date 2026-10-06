<?php

// caminho base das paginas
$caminhoBase = "/../public_html/pages/";

// classe abstrata com funções que retornam arquivos html
abstract class TrocarRota 
{
    // GET /
    protected function home ()
    {
        global $caminhoBase;
        require __DIR__ . $caminhoBase . "home.html";
    }
}