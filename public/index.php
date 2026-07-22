<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use User\Greengrocers\Controller\ProductsController;
use User\Greengrocers\Repository\ProductRepository;
use User\Greengrocers\Database\Connection;
// Roteamento simples baseado no caminho da URL
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

switch ($path) {
    case '/':
        $repository = new ProductRepository(Connection::get());
        (new ProductsController($repository))->index();
        break;

    default:
        http_response_code(404);
        echo 'Página não encontrada';
}
