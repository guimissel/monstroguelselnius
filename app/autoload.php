<?php

function autoload($classe) {
  $classeBuscada = $classe . ".php";

  $diretorios = new RecursiveDirectoryIterator(__DIR__);
  $arquivos = new RecursiveIteratorIterator($diretorios);

  foreach ($arquivos as $arquivo) {
    if ($arquivo->getFilename() === $classeBuscada) {
      require_once $arquivo->getPathname();
      return;
    }
  }
}

spl_autoload_register("autoload");