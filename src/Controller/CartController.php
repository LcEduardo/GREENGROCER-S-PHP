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
     * @return array{count: float, total: string, items: list<array{productId: int, name: string, quantity: string, priceLabel: ?string, lineLabel: ?string}>}
     */
    private function cartData(): array
    {
        // resumo() é o mesmo método que templates/layout/default.php chama
        // no primeiro paint — a soma do total existe numa fórmula só, não
        // uma em cada lugar que precisa mostrar o carrinho.
        $resumo = $this->cart->resumo((int) $_SESSION['user_id'], $this->products);

        // priceLabel/lineLabel ficam nulos quando o produto foi removido —
        // mesmo caso que 'Produto removido' cobre pro name, só que aqui não
        // há preço nenhum pra mostrar (nem "cada", nem o total da linha).
        $items = array_map(function (array $linha) {
            $produto = $linha['produto'];
            $quantidade = (float) $linha['item']->quantity;

            return [
                'productId'  => $linha['item']->productId,
                'name'       => $produto?->name ?? 'Produto removido',
                'quantity'   => $linha['item']->quantity,
                'priceLabel' => $produto?->formattedPrice(),
                'lineLabel'  => $produto !== null
                    ? 'R$ ' . number_format((float) $produto->salePrice * $quantidade, 2, ',', '.')
                    : null,
            ];
        }, $resumo['items']);

        // 'count' é a quantidade total do carrinho (soma das quantidades),
        // não o número de produtos distintos — é o que o badge da nav mostra.
        return [
            'count' => $resumo['count'],
            'total' => 'R$ ' . number_format($resumo['total'], 2, ',', '.'),
            'items' => $items,
        ];
    }

    // fetch() não manda X-Requested-With sozinho (isso era coisa do jQuery) —
    // por isso o cart.js seta esse header à mão, e é ele que diferencia "veio
    // do JS" de "form comum submetendo".
    private function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    }
}
