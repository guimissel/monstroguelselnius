<?php

// crie as todas rotas da api
define("ROTAS_API", [
    "GET" => [
        "olamundo" => function () {
            echo "hello world";
        }
    ],

    "POST" => [
        "api/usuario/criar" => function () {
            require __DIR__ . "/user/create.php";
        },

        "api/usuario/entrar" => function () {
            require __DIR__ . "/user/signin.php";
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
