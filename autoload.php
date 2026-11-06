<?php

function autoload(string $classe) {
    $classe .= ".php";

    $diretorios = new RecursiveDirectoryIterator(__DIR__);
    $arquivos = new RecursiveIteratorIterator($diretorios);

    foreach ($arquivos as $arquivo) {
        if ($arquivo->getFilename() === $classe) {
            require_once $arquivo->getPathname();
            return;
        }
    }
}

spl_autoload_register("autoload");