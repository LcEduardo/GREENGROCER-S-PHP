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

// Mesmo critério do CartController::isAjax() — o cart.js seta esse header à
// mão em toda chamada fetch(). Usado aqui pra decidir 401 (JS trata) vs.
// redirect (form comum navegando) quando o carrinho é acessado deslogado.
function isAjax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

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
            if (isAjax()) {
                http_response_code(401);
            } else {
                header('Location: /login');
            }
            break;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $cartRepo = new CartRepository(Connection::get());
            $productRepo = new ProductRepository(Connection::get());
            (new CartController($cartRepo, $productRepo))->store();
        } else {
            http_response_code(405);
        }
        break;

    case '/cart/decrease':
        if (!Guard::isLoggedIn()) {
            if (isAjax()) {
                http_response_code(401);
            } else {
                header('Location: /login');
            }
            break;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $cartRepo = new CartRepository(Connection::get());
            $productRepo = new ProductRepository(Connection::get());
            (new CartController($cartRepo, $productRepo))->decrease();
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
