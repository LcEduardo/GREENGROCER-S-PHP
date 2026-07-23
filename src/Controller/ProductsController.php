<?php

declare(strict_types=1);

namespace User\Greengrocers\Controller;

use User\Greengrocers\View\View;
use User\Greengrocers\Repository\ProductRepository;

class ProductsController
{
    private View $view;

    /**
     * As categorias da vitrine são um conjunto fixo: "Todos | Frutas | Legumes |
     * Verduras". Ficam aqui, e não numa consulta, porque são um punhado estável —
     * ir ao banco a cada request só pra montar 3 links seria peso sem retorno.
     *
     * ⚠️ Os ids têm que bater com as linhas de `categories` no banco: é o
     * `category_id` que vai pro filtro do repositório.
     */
    private const CATEGORIAS = [
        1 => 'Frutas',
        2 => 'Legumes',
        3 => 'Verduras',
    ];

    // O repositório chega pronto de fora (montado em public/index.php). Assim
    // este controller não sabe que existe um banco: se amanhã os produtos
    // vierem de outra fonte, nada aqui muda.
    public function __construct(private readonly ProductRepository $products)
    {
        $this->view = new View();
    }

    public function index(): void
    {
        // ?category vem da URL — sempre string, e editável por quem quiser. O
        // findActive() espera ?int, então convertemos: 1/2/3 passam; ausente ou
        // lixo ("abc") vira null, que a vitrine lê como "Todos".
        $categoryId = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: null;

        $this->view->render('Products/index', [
            'title'                => 'Produtos',
            'produtos'             => $this->products->findActive($categoryId),
            'categorias'           => self::CATEGORIAS,
            'categoriaSelecionada' => $categoryId,
        ]);
    }
}