<?php

declare(strict_types=1);

namespace User\Greengrocers\Controller;

use User\Greengrocers\View\View;
use User\Greengrocers\Repository\ProductRepository;

class ProductsController
{
    private View $view;

    // O repositório chega pronto de fora (montado em public/index.php). Assim
    // este controller não sabe que existe um banco: se amanhã os produtos
    // vierem de outra fonte, nada aqui muda.
    public function __construct(private readonly ProductRepository $products)
    {
        $this->view = new View();
    }

    public function index(): void
    {
        $this->view->render('Products/index', [
            'title'    => 'Produtos',
            'produtos' => $this->products->findActive(),
        ]);
    }
}