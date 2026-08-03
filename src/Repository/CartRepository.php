<?php

declare(strict_types=1);

namespace User\Greengrocers\Repository;

use PDO;
use User\Greengrocers\Model\CartItem;

class CartRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): CartItem
    {
        return new CartItem(
            id: (int) $row['id'],
            userId: (int) $row['user_id'],
            productId: (int) $row['product_id'],
            quantity: (string) $row['quantity'],
        );
    }

    public function addItem(int $userId, int $productId, string $quantity): void
    {
        $sql = 'INSERT INTO cart (user_id, product_id, quantity, updated_at)'
             . ' VALUES (:userId, :productId, :quantity, :updatedAt)';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'userId'    => $userId,
            'productId' => $productId,
            'quantity'  => $quantity,
            'updatedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return CartItem[]
     */
    public function findByUser(int $userId): array
    {
        $sql = 'SELECT id, user_id, product_id, quantity FROM cart WHERE user_id = :userId';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['userId' => $userId]);

        $itens = [];

        foreach ($statement as $row) {
            $itens[] = $this->hydrate($row);
        }

        return $itens;
    }
}
