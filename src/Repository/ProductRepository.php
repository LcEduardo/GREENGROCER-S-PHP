<?php

declare(strict_types=1);

namespace User\Greengrocers\Repository;

use PDO;
use User\Greengrocers\Model\Product;

class ProductRepository 
{
    public function __construct(private readonly PDO $pdo)
    {

    }

    /**
     * Traduz a linha do banco para o objeto de domínio.
     *
     * Mora aqui, e não num Product::fromRow(), para que o Model não precise
     * conhecer os nomes das colunas.
     *
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Product
    {
        return new Product(
            id:         (int) $row['id'],
            categoryId: (int) $row['category_id'],
            name: $row['name'],
            unit: $row['unit'],
            // DECIMAL segue como string até a View, mas o cast é obrigatório
            // porque os drivers divergem: o Postgres devolve a string "7.90", o
            // SQLite devolve float(7.9). O SQLite não tem DECIMAL de verdade —
            // por afinidade de tipo ele guarda NUMERIC como REAL, e a escala se
            // perde na ESCRITA. Aqui só uniformizamos o tipo; o que o banco
            // jogou fora não volta.
            salePrice:     (string) $row['sale_price'],
            stockQuantity: (string) $row['stock_quantity'],
            image: $row['image'],
        );
    }

    /** @return Product[] */
    public function findActive(): array
    {
        $statement = $this->pdo->query(<<<SQL
            SELECT id, name, category_id, unit, sale_price, stock_quantity, image
            FROM products
            WHERE active = TRUE
            ORDER BY name
        SQL);

        $produtos = [];

        foreach ($statement as $row) {
            $produtos[] = $this->hydrate($row);
        }

        return $produtos;
    }
}