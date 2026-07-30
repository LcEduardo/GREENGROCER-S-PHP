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
            salePrice:     (string) $row['sale_price'],
            stockQuantity: (string) $row['stock_quantity'],
            active: in_array($row['active'], [true, 1, '1', 't'], true),
            image: $row['image'],
        );
    }

    /**
     * A vitrine. Sem categoria, todos os ativos; com categoria, só os daquela —
     * e o recorte por `active` continua valendo junto, nunca no lugar dele.
     *
     * @return Product[]
     */
    public function findActive(?int $categoryId = null, ?string $search = null): array
    {
        // Cláusula e parâmetro nascem no MESMO if: some a repetição de checar
        // null duas vezes, e sem categoria o execute só recebe um array vazio.
        $sql = 'SELECT id, name, category_id, unit, sale_price, stock_quantity, active, image'
             . ' FROM products'
             . ' WHERE active = TRUE';

        $params = [];

        if ($categoryId !== null) {
            $sql .= ' AND category_id = :categoryId';
            $params['categoryId'] = $categoryId;
        }

        if ($search !== null) {
            // LOWER() nos dois lados em vez de ILIKE: ILIKE não existe no SQLite
            // dos testes, só no Postgres de produção.
            $sql .= ' AND LOWER(name) LIKE LOWER(:search)';
            $params['search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY name';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $produtos = [];

        foreach ($statement as $row) {
            $produtos[] = $this->hydrate($row);
        }

        return $produtos;
    }

    public function findAllProducts(): array
    {
        $sql = $sql = 'SELECT id, name, category_id, unit, sale_price, stock_quantity, active,image'
                    . ' FROM products'
                    . ' order by active desc';

        $statement = $this->pdo->prepare($sql);
        $statement->execute();

        foreach ($statement as $row) {
            $produtos[] = $this->hydrate($row);
        }

        return $produtos;
    }

    public function findById(int $id): ?Product
    {
        $sql = 'SELECT id, name, category_id, unit, sale_price, stock_quantity, active, image'
             . ' FROM products'
             . ' WHERE id = :id';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * Mesmos campos de update(), mais created_at: a coluna não tem DEFAULT na
     * migration, então precisa vir de algum lugar na criação.
     *
     * @return int O id gerado, para quem cria já poder buscar o produto de volta.
     */
    public function create(
        string $name,
        int $categoryId,
        string $unit,
        string $salePrice,
        string $stockQuantity,
        bool $active,
        ?string $image,
    ): int {
        $sql = 'INSERT INTO products'
             . ' (name, category_id, unit, sale_price, stock_quantity, active, image, created_at)'
             . ' VALUES (:name, :categoryId, :unit, :salePrice, :stockQuantity, :active, :image, :createdAt)';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'name'          => $name,
            'categoryId'    => $categoryId,
            'unit'          => $unit,
            'salePrice'     => $salePrice,
            'stockQuantity' => $stockQuantity,
            'active'        => $active ? '1' : '0',
            'image'         => $image,
            'createdAt'     => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Campos editáveis pela tela de admin. `cost_price`, `ean` e `created_at`
     * ficam de fora de propósito: não fazem parte do formulário de edição.
     */
    public function update(
        int $id,
        string $name,
        int $categoryId,
        string $unit,
        string $salePrice,
        string $stockQuantity,
        bool $active,
        ?string $image,
    ): void {
        $sql = 'UPDATE products'
             . ' SET name = :name, category_id = :categoryId, unit = :unit,'
             . ' sale_price = :salePrice, stock_quantity = :stockQuantity,'
             . ' active = :active, image = :image'
             . ' WHERE id = :id';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'name'          => $name,
            'categoryId'    => $categoryId,
            'unit'          => $unit,
            'salePrice'     => $salePrice,
            'stockQuantity' => $stockQuantity,
            'active'        => $active ? '1' : '0',
            'image'         => $image,
            'id'            => $id,
        ]);
    }
}