<?php

declare(strict_types=1);

namespace User\Greengrocers\Controller;

use User\Greengrocers\Repository\CartRepository;
use User\Greengrocers\Repository\ProductRepository;

class CartController
{
    public function __construct(
        private readonly CartRepository $cart,
        private readonly ProductRepository $products,
    ) {
    }

    public function store(): void
    {
        $productId = (int) filter_input(INPUT_POST, 'productId', FILTER_VALIDATE_INT);
        $quantity = (string) filter_input(INPUT_POST, 'quantity');

        $this->cart->addItem((int) $_SESSION['user_id'], $productId, $quantity);

        $this->respond();
    }

    public function decrease(): void
    {
        $productId = (int) filter_input(INPUT_POST, 'productId', FILTER_VALIDATE_INT);

        $this->cart->decreaseQuantity((int) $_SESSION['user_id'], $productId);

        $this->respond();
    }

    /**
     * Chamada via fetch (ver public/js/cart.js): devolve o carrinho como JSON,
     * pro JS redesenhar o painel sem reload. Form comum, sem JS: continua
     * navegando de volta pra / — mesmo fallback de antes do AJAX.
     */
    private function respond(): void
    {
        if (!$this->isAjax()) {
            header('Location: /');
            return;
        }

        header('Content-Type: application/json');
        echo json_encode($this->cartData());
    }

    /**
     * @return array{count: float, items: list<array{productId: int, name: string, quantity: string}>}
     */
    private function cartData(): array
    {
        $userId = (int) $_SESSION['user_id'];
        $itens = $this->cart->findByUser($userId);

        $items = array_map(function ($item) {
            $produto = $this->products->findById($item->productId);

            return [
                'productId' => $item->productId,
                'name'      => $produto?->name ?? 'Produto removido',
                'quantity'  => $item->quantity,
            ];
        }, $itens);

        // 'count' é a quantidade total do carrinho (soma das quantidades),
        // não o número de produtos distintos — é o que o badge da nav mostra.
        return ['count' => $this->cart->qtdTotal($userId), 'items' => $items];
    }

    // fetch() não manda X-Requested-With sozinho (isso era coisa do jQuery) —
    // por isso o cart.js seta esse header à mão, e é ele que diferencia "veio
    // do JS" de "form comum submetendo".
    private function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    }
}
