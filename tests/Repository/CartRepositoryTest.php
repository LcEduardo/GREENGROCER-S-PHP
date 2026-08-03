<?php

declare(strict_types=1);

namespace User\Greengrocers\Tests\Repository;

use User\Greengrocers\Model\User;
use User\Greengrocers\Repository\CartRepository;
use User\Greengrocers\Repository\UserRepository;
use User\Greengrocers\Tests\Support\DatabaseTestCase;

class CartRepositoryTest extends DatabaseTestCase
{
    public function test_addItem_adiciona_produto_novo_ao_carrinho_vazio(): void
    {
        $userId = $this->criarUsuario();
        $this->criarCategoria(1, 'Legumes');
        $produtoId = $this->criarProduto(nome: 'Tomate');

        $repository = new CartRepository($this->pdo);
        $repository->addItem($userId, $produtoId, '2.000');

        $itens = $repository->findByUser($userId);

        $this->assertCount(1, $itens);
        $this->assertSame($produtoId, $itens[0]->productId);
        $this->assertSame(2.0, (float) $itens[0]->quantity);
    }

    private function criarUsuario(): int
    {
        return new UserRepository($this->pdo)->create(
            name: 'Ana',
            email: 'ana@example.com',
            password: User::hashPassword('senha123'),
            admin: false,
        );
    }

    /**
     * A categoria vem SEMPRE antes do produto: products.category_id tem FK
     * RESTRICT para categories, e o DatabaseTestCase liga o PRAGMA que faz o
     * SQLite cobrar isso de verdade.
     */
    private function criarCategoria(int $id, string $nome): void
    {
        $sql = $this->pdo->prepare('INSERT INTO categories (id, name) VALUES (:id, :nome)');

        $sql->execute(['id' => $id, 'nome' => $nome]);
    }

    private function criarProduto(string $nome, int $categoriaId = 1): int
    {
        $sql = $this->pdo->prepare(
            'INSERT INTO products (name, category_id, unit, sale_price, active, created_at)
             VALUES (:nome, :categoria_id, :unidade, :preco, :ativo, :criado_em)'
        );

        $sql->execute([
            'nome'         => $nome,
            'categoria_id' => $categoriaId,
            'unidade'      => 'kg',
            'preco'        => '7.90',
            'ativo'        => 1,
            'criado_em'    => '2026-07-22 10:00:00',
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
