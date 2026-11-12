<?php

// Procura a classe pelo nome do arquivo (Classe.php) em qualquer pasta do projeto.
// O mapa e montado uma vez so por requisicao, e as pastas de imagens/documentos/git sao ignoradas.
function autoload(string $classe) {
    static $mapa = null;

    if ($mapa === null) {
        $mapa = [];
        $ignorar = ["public", "docs", ".git", "node_modules", "vendor"];

        $diretorios = new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS);
        $filtro = new RecursiveCallbackFilterIterator($diretorios, function ($atual) use ($ignorar) {
            return !($atual->isDir() && in_array($atual->getFilename(), $ignorar, true));
        });

        foreach (new RecursiveIteratorIterator($filtro) as $arquivo) {
            if ($arquivo->getExtension() === "php") {
                $mapa[$arquivo->getFilename()] ??= $arquivo->getPathname();
            }
        }
    }

    if (isset($mapa[$classe . ".php"])) {
        require_once $mapa[$classe . ".php"];
    }
}

spl_autoload_register("autoload");
