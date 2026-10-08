<?php 
// chamar roteador
require_once __DIR__ . '/routes/Router.php';

// caminho base para todas rotas
$caminhoBase = "/monstroguelselnius/";

// instancia um roteador
$roteador = new Roteador();

// adicionar rota /login
$roteador->get($caminhoBase . "login", function () {

    // chamar view login
    view("login");
});

$roteador->get($caminhoBase . "signup", function () {

    // chamar view login
    view("signup");
});

// despachar usuario para a rota
$roteador->despacharRota();