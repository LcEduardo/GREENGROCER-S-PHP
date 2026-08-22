<?php

declare(strict_types=1);

namespace User\Greengrocers\Controller;

use User\Greengrocers\Repository\StockMovementRepository;
use User\Greengrocers\View\View;

class StockMovementsController
{
    private View $view;

    public function __construct(
        private readonly StockMovementRepository $movements,
    ) {
        $this->view = new View();
    }

    public function index(): void
    {

        $searchProduct = trim((string) filter_input(INPUT_GET, 'q')) ?: null;

        // O total é lido ANTES de decidir qual página mostrar, por dois
        // motivos: ele é o "N movimentações" do cabeçalho E o que limita o
        // pedido — `?page=999` numa lista de 3 páginas cai na 3, e não numa
        // tabela vazia sem explicação. O piso de 1 cobre o outro lado
        // (page=0, page=-4, page=abc).
        $total = $this->movements->countAll($searchProduct);
        $totalPaginas = StockMovementRepository::totalDePaginas($total);
        $pagina = min(
            max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT)),
            $totalPaginas,
        );

        $movimentacoes = $this->movements->findPage(
            $searchProduct,
            StockMovementRepository::POR_PAGINA,
            ($pagina - 1) * StockMovementRepository::POR_PAGINA,
        );

        $this->view->render('StockMovements/index', [
            'title'         => 'Movimentações de estoque',
            'movimentacoes' => $movimentacoes,
            'searchProduct' => $searchProduct,
            'total'         => $total,
            'pagina'        => $pagina,
            'totalPaginas'  => $totalPaginas,
            'porPagina'     => StockMovementRepository::POR_PAGINA,
        ]);
    }
}
