<?php 
require_once __DIR__ . '/routes/Router.php';

// resgata o valor do path
$requestPath = $_SERVER['REQUEST_URI'];

$router = new Router();
$router->run($requestPath);