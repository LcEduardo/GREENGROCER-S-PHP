<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use User\Greengrocers\Controller\UsersController;

// Roteamento simples baseado no caminho da URL
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$controller = new UsersController();

switch ($path) {
    case '/':
        // Entrada do site: manda direto para o formulário de cadastro
        header('Location: /users/add');
        break;

    case '/users':
        $controller->index();
        break;

    case '/users/add':
        $controller->add();
        break;

    default:
        http_response_code(404);
        echo 'Página não encontrada';
}
