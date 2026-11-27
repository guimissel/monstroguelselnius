<?php


// crie as todas rotas da api
// apenas Acesso::USUARIO & Acesso::ADMIN
define("ROTAS_API_NEEDAUTH", [
    "GET" => [
        "api/usuario/eu" => function () {
            (UsuarioController::NovaInstancia())->eu();
        },

        "api/inventario" => function () {
            (InventarioController::NovaInstancia())->listar();
        },

        "api/loja" => function () {
            (LojaController::NovaInstancia())->listar();
        },

        "api/equipe" => function () {
            (EquipeController::NovaInstancia())->listar();
        },

        "api/pokemon" => function () {
            (PokemonController::NovaInstancia())->listar();
        },

        "api/perfil" => function () {
            (PerfilController::NovaInstancia())->ver();
        },

        "api/progresso/exportar" => function () {
            (ProgressoController::NovaInstancia())->exportar();
        },

        "api/montarSeed" => function () {
            $montadorSeed = new MontadorSeed(__DIR__ . "/../app/database/seeds/");
            $montadorSeed->criarSeeds();
        },
        "api/popularBanco" => function () {
            $montadorSeed = new MontadorSeed(__DIR__ . "/../app/database/seeds");
            $montadorSeed->popularBanco();
        }
    ],

    "POST" => [
        "api/usuario/sair" => function () {
            (UsuarioController::NovaInstancia())->sair();
        },

        "api/inventario/{chave}/usar" => function ($chave) {
            (InventarioController::NovaInstancia())->usar($chave);
        },

        "api/loja/{chave}/comprar" => function ($chave) {
            (LojaController::NovaInstancia())->comprar($chave);
        },

        "api/equipe" => function () {
            (EquipeController::NovaInstancia())->salvar();
        },

        "api/equipe/evoluir" => function () {
            (EquipeController::NovaInstancia())->evoluir();
        },

        "api/progresso/importar" => function () {
            (ProgressoController::NovaInstancia())->importar();
        },

        "api/usuario/atualizar" => function () {
            (UsuarioController::NovaInstancia())->atualizar();
        },

        "api/batalha/iniciar" => function () {
            (BatalhaController::NovaInstancia())->iniciar();
        },

        "api/batalha/{id}/turno" => function ($id) {
            (BatalhaController::NovaInstancia())->turno($id);
        },

        "api/batalha/{id}/finalizar" => function ($id) {
            (BatalhaController::NovaInstancia())->finalizar($id);
        }
    ],

    "PUT" => [
    ],

    "PATCH" => [
    ],

    "DELETE" => [
        "api/usuario/{id}/excluir" => function ($id) {
            (UsuarioController::NovaInstancia())->excluir($id);
        }
    ],

    "QUERY" => [
    ]
]);

// todos tem direito de usar estas rotas
define("ROTAS_API_NOAUTH", [
    "POST" => [
        "api/usuario/criar" => function () {
            // pode trocar se souber um jeito melhor 
            // foi um mais rapido que pensei ;)
            (UsuarioController::NovaInstancia())->criar();
        },

        "api/usuario/entrar" => function () {
            (UsuarioController::NovaInstancia())->entrar();
        },
    ],
]);