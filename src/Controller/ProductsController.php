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
        $busca = trim((string) filter_input(INPUT_GET, 'q')) ?: null;
        $produtos = $this->products->findActive($categoryId, $busca);

        $this->view->render('Products/index', [
            'title'                => 'Produtos',
            'produtos'             => $produtos,
            'categorias'           => self::CATEGORIAS,
            'categoriaSelecionada' => $categoryId,
            'countItems'           => count($produtos),
            'busca'                => $busca,
        ]);
    }
    
    public function admin(): void
    {
        $this->view->render('Products/admin', [
            'title'    => 'Produtos',
            'produtos' => $this->products->findAllProducts(),
        ]);
    }

    public function edit(): void
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
        $produto = $id !== null ? $this->products->findById($id) : null;

        if ($produto === null) {
            http_response_code(404);
            echo 'Produto não encontrado';
            return;
        }

        $this->view->render('Products/edit', [
            'title'      => 'Editar produto',
            'produto'    => $produto,
            'categorias' => self::CATEGORIAS,
        ]);
    }

    public function update(): void
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;

        if ($id === null) {
            http_response_code(404);
            echo 'Produto não encontrado';
            return;
        }

        $this->products->update(
            id: $id,
            name: (string) filter_input(INPUT_POST, 'name'),
            categoryId: (int) filter_input(INPUT_POST, 'categoryId', FILTER_VALIDATE_INT),
            unit: (string) filter_input(INPUT_POST, 'unit'),
            salePrice: (string) filter_input(INPUT_POST, 'salePrice'),
            stockQuantity: (string) filter_input(INPUT_POST, 'stockQuantity'),
            // Checkbox desmarcado nem entra no POST — a presença da chave é o sinal.
            active: filter_input(INPUT_POST, 'active') !== null,
            image: filter_input(INPUT_POST, 'image') ?: null,
        );

        header('Location: /admin/products');
    }

    public function create(): void
    {
        $this->view->render('Products/create', [
            'title'      => 'Novo produto',
            'categorias' => self::CATEGORIAS,
        ]);
    }

    public function store(): void
    {
        $this->products->create(
            name: (string) filter_input(INPUT_POST, 'name'),
            categoryId: (int) filter_input(INPUT_POST, 'categoryId', FILTER_VALIDATE_INT),
            unit: (string) filter_input(INPUT_POST, 'unit'),
            salePrice: (string) filter_input(INPUT_POST, 'salePrice'),
            stockQuantity: (string) filter_input(INPUT_POST, 'stockQuantity'),
            // Checkbox desmarcado nem entra no POST — a presença da chave é o sinal.
            active: filter_input(INPUT_POST, 'active') !== null,
            image: filter_input(INPUT_POST, 'image') ?: null,
        );

        header('Location: /admin/products');
    }
}