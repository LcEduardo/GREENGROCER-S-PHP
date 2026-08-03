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
     * Diminui 1 unidade da quantidade do item. Se o resultado ficar abaixo de 1,
     * remove a linha em vez de deixar o carrinho com quantidade zerada/negativa.
     */
    public function decreaseQuantity(int $userId, int $productId): void
    {
        $sql = 'SELECT quantity FROM cart WHERE user_id = :userId AND product_id = :productId';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['userId' => $userId, 'productId' => $productId]);

        $row = $statement->fetch();

        // Já não está no carrinho (ex.: duplo clique) — nada a fazer.
        if ($row === false) {
            return;
        }

        $novaQuantidade = (float) $row['quantity'] - 1;

        if ($novaQuantidade < 1) {
            $this->remove($userId, $productId);
            return;
        }

        $sql = 'UPDATE cart SET quantity = :quantity, updated_at = :updatedAt'
             . ' WHERE user_id = :userId AND product_id = :productId';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'quantity'  => (string) $novaQuantidade,
            'updatedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'userId'    => $userId,
            'productId' => $productId,
        ]);
    }

    private function remove(int $userId, int $productId): void
    {
        $sql = 'DELETE FROM cart WHERE user_id = :userId AND product_id = :productId';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['userId' => $userId, 'productId' => $productId]);
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
