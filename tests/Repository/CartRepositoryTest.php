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

    public function test_decreaseQuantity_diminui_quando_resultado_fica_maior_ou_igual_a_um(): void
    {
        $userId = $this->criarUsuario();
        $this->criarCategoria(1, 'Legumes');
        $produtoId = $this->criarProduto(nome: 'Tomate');

        $repository = new CartRepository($this->pdo);
        $repository->addItem($userId, $produtoId, '3');
        $repository->decreaseQuantity($userId, $produtoId);

        $itens = $repository->findByUser($userId);

        $this->assertCount(1, $itens);
        $this->assertSame(2.0, (float) $itens[0]->quantity);
    }

    public function test_decreaseQuantity_remove_item_quando_resultado_fica_menor_que_um(): void
    {
        $userId = $this->criarUsuario();
        $this->criarCategoria(1, 'Legumes');
        $produtoId = $this->criarProduto(nome: 'Tomate');

        $repository = new CartRepository($this->pdo);
        $repository->addItem($userId, $produtoId, '1');
        $repository->decreaseQuantity($userId, $produtoId);

        $this->assertCount(0, $repository->findByUser($userId));
    }

    public function test_decreaseQuantity_nao_faz_nada_quando_produto_nao_esta_no_carrinho(): void
    {
        $userId = $this->criarUsuario();
        $this->criarCategoria(1, 'Legumes');
        $produtoId = $this->criarProduto(nome: 'Tomate');

        $repository = new CartRepository($this->pdo);
        $repository->decreaseQuantity($userId, $produtoId);

        $this->assertCount(0, $repository->findByUser($userId));
    }

    public function test_qtdTotal_soma_quantidade_de_todos_os_itens_do_carrinho(): void
    {
        $userId = $this->criarUsuario();
        $this->criarCategoria(1, 'Legumes');
        $tomateId = $this->criarProduto(nome: 'Tomate');
        $cebolaId = $this->criarProduto(nome: 'Cebola');

        $repository = new CartRepository($this->pdo);
        $repository->addItem($userId, $tomateId, '2.000');
        $repository->addItem($userId, $cebolaId, '1.500');

        $this->assertSame(3.5, $repository->qtdTotal($userId));
    }

    public function test_qtdTotal_retorna_zero_quando_carrinho_esta_vazio(): void
    {
        $userId = $this->criarUsuario();

        $repository = new CartRepository($this->pdo);

        $this->assertSame(0.0, $repository->qtdTotal($userId));
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
