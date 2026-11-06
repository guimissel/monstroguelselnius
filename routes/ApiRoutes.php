<?php

require_once __DIR__ . "/../app/controller/UsuarioController.php";
require_once __DIR__ . "/../app/controller/InventarioController.php";

// crie as todas rotas da api
define("ROTAS_API", [
    "GET" => [
        "api/usuario/eu" => function () {
            $controlador = new UsuarioController();
            $controlador->eu();
        },

        "api/inventario" => function () {
            $controlador = new InventarioController();
            $controlador->listar();
        },
    ],

    "POST" => [
        "api/usuario/criar" => function () {
            // pode trocar se souber um jeito melhor 
            // foi um mais rapido que pensei ;)
            $controlador = new UsuarioController();
            $controlador->criar();
        },

        "api/usuario/entrar" => function () {
            $controlador = new UsuarioController();
            $controlador->entrar();
        },

        "api/usuario/sair" => function () {
            $controlador = new UsuarioController();
            $controlador->sair();
        },

        "api/inventario/{chave}/usar" => function ($chave) {
            $controlador = new InventarioController();
            $controlador->usar($chave);
        }
    ],

    "PUT" => [
    ],

    "PATCH" => [
    ],

    "DELETE" => [
        "api/usuario/{id}/excluir" => function ($id) {
            $controlador = new UsuarioController();
            $controlador->excluir($id);
        }
    ],

    "QUERY" => [
    ]
]);