<?php

declare(strict_types=1);

namespace User\Greengrocers\Controller;

use User\Greengrocers\Repository\CartRepository;

class CartController
{
    public function __construct(private readonly CartRepository $cart)
    {
    }

    public function store(): void
    {
        $productId = (int) filter_input(INPUT_POST, 'productId', FILTER_VALIDATE_INT);
        $quantity = (string) filter_input(INPUT_POST, 'quantity');

        $this->cart->addItem((int) $_SESSION['user_id'], $productId, $quantity);

        header('Location: /');
    }
}
