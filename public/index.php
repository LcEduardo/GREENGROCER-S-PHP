<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use User\Greengrocers\Auth\Guard;
use User\Greengrocers\Controller\CartController;
use User\Greengrocers\Controller\ProductsController;
use User\Greengrocers\Controller\SessionsController;
use User\Greengrocers\Controller\UsersController;
use User\Greengrocers\Repository\CartRepository;
use User\Greengrocers\Repository\ProductRepository;
use User\Greengrocers\Repository\UserRepository;
use User\Greengrocers\Database\Connection;
// Roteamento simples baseado no caminho da URL
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

session_start();

switch ($path) {
    case '/':
        $repository = new ProductRepository(Connection::get());
        (new ProductsController($repository))->index();
        break;
    
    case '/admin/products':
        if (!Guard::isAdmin()) {
            header('Location: ' . (Guard::isLoggedIn() ? '/' : '/login'));
            break;
        }

        $repo = new ProductRepository(Connection::get());
        (new ProductsController($repo))->admin();
        break;

    case '/admin/products/edit':
        if (!Guard::isAdmin()) {
            header('Location: ' . (Guard::isLoggedIn() ? '/' : '/login'));
            break;
        }

        $repo = new ProductRepository(Connection::get());
        $controller = new ProductsController($repo);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $controller->update();
        } else {
            $controller->edit();
        }
        break;

    case '/admin/products/create':
        if (!Guard::isAdmin()) {
            header('Location: ' . (Guard::isLoggedIn() ? '/' : '/login'));
            break;
        }

        $repo = new ProductRepository(Connection::get());
        $controller = new ProductsController($repo);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $controller->store();
        } else {
            $controller->create();
        }
        break;

    case '/cart/add':
        if (!Guard::isLoggedIn()) {
            header('Location: /login');
            break;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $repo = new CartRepository(Connection::get());
            (new CartController($repo))->store();
        } else {
            http_response_code(405);
        }
        break;

    case '/register':
        $repository = new UserRepository(Connection::get());
        $controller = new UsersController($repository);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $controller->store();
        } else {
            $controller->create();
        }
        break;

    case '/login':
        $repository = new UserRepository(Connection::get());
        $controller = new SessionsController($repository);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $controller->store();
        } else {
            $controller->create();
        }
        break;

    case '/logout':
        $repository = new UserRepository(Connection::get());
        (new SessionsController($repository))->destroy();
        break;

    default:
        http_response_code(404);
        echo 'Página não encontrada';
}
