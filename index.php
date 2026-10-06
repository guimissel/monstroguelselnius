<?php 
require_once __DIR__ . '/routes/Router.php';

// resgata o valor do path
$caminhoRequisitado = $_SERVER['REQUEST_URI'];

$roteador = new Roteador();
$roteador->run($caminhoRequisitado);