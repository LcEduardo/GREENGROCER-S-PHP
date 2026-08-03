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

        $this->redirectBack();
    }

    /**
     * Volta pra página que tinha o formulário, com o carrinho aberto. Só aceita
     * caminho relativo (começa com "/" e não com "//") — nunca um host vindo de
     * fora, que é o cliente quem controla esse campo.
     */
    private function redirectBack(): void
    {
        $redirect = (string) filter_input(INPUT_POST, 'redirect');

        if ($redirect === '' || $redirect[0] !== '/' || str_starts_with($redirect, '//')) {
            $redirect = '/';
        }

        $separator = str_contains($redirect, '?') ? '&' : '?';
        header('Location: ' . $redirect . $separator . 'cart=open');
    }
}
