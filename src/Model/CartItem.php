<?php

declare(strict_types=1);

namespace User\Greengrocers\Model;

/**
 * Uma linha do carrinho: um produto e a quantidade que o cliente quer levar.
 *
 * `readonly` pelo mesmo motivo do Product e do User: nasce hidratado do banco,
 * e qualquer mudança de quantidade passa por uma escrita de verdade no
 * Repository, não por mutação em memória.
 */
class CartItem
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly int $productId,

        // QUANTIDADE como STRING, não float — mesma decisão do Product::$stockQuantity.
        public readonly string $quantity,
    ) {
    }
}
