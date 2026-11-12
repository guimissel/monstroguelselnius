<?php

require_once __DIR__ . "/../app/controller/UsuarioController.php";
require_once __DIR__ . "/../app/controller/InventarioController.php";
require_once __DIR__ . "/../app/controller/LojaController.php";
require_once __DIR__ . "/../app/controller/BatalhaController.php";
require_once __DIR__ . "/../app/controller/EquipeController.php";
require_once __DIR__ . "/../app/controller/PokemonController.php";
require_once __DIR__ . "/../app/controller/PerfilController.php";

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

        "api/loja" => function () {
            $controlador = new LojaController();
            $controlador->listar();
        },

        "api/equipe" => function () {
            $controlador = new EquipeController();
            $controlador->listar();
        },

        "api/pokemon" => function () {
            $controlador = new PokemonController();
            $controlador->listar();
        },

        "api/perfil" => function () {
            $controlador = new PerfilController();
            $controlador->ver();
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
        },

        "api/loja/{chave}/comprar" => function ($chave) {
            $controlador = new LojaController();
            $controlador->comprar($chave);
        },

        "api/equipe" => function () {
            $controlador = new EquipeController();
            $controlador->salvar();
        },

        "api/equipe/evoluir" => function () {
            $controlador = new EquipeController();
            $controlador->evoluir();
        },

        "api/usuario/atualizar" => function () {
            $controlador = new UsuarioController();
            $controlador->atualizar();
        },

        "api/batalha/iniciar" => function () {
            $controlador = new BatalhaController();
            $controlador->iniciar();
        },

        "api/batalha/{id}/finalizar" => function ($id) {
            $controlador = new BatalhaController();
            $controlador->finalizar($id);
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