<?php

function autoload(string $classe) {
    $classe .= ".php";

    $diretoios = new RecursiveDirectoryIterator(__DIR__);
    $arquivos = new RecursiveIteratorIterator($diretoios);

    foreach ($arquivos as $arquivo) {
        if ($arquivo->getFilename() === $classe) {
            require_once $arquivo->getPathname();
            return;
        }
    }
}

spl_autoload_register("autoload");