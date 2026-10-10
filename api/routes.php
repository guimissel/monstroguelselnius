<?php

require_once __DIR__ . "/../app/controller/UsuarioController.php";

// crie as todas rotas da api
define("ROTAS_API", [
    "GET" => [
        "api/usuario/eu" => function () {
            $controlador = new UsuarioController();
            $controlador->eu();
        }
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
        }
    ],

    "PUT" => [
    ],

    "PATCH" => [
    ],

    "DELETE" => [
    ],

    "QUERY" => [
    ]
]);