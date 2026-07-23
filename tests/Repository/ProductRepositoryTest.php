<?php

declare(strict_types=1);

namespace User\Greengrocers\Tests\Repository;

use User\Greengrocers\Tests\Support\DatabaseTestCase;
use User\Greengrocers\Repository\ProductRepository;

class ProductRepositoryTest extends DatabaseTestCase 
{
    public function test_findActive_nao_traz_produto_inativo(): void
    {
        $this->criarCategoria(1, 'Legumes');
        $this->criarProduto(nome: 'Tomate', ativo: true);
        $this->criarProduto(nome: 'Abóbora fora de época', ativo: false);

        $produtosAtivos = new ProductRepository($this->pdo)->findActive();

        $this->assertCount(1, $produtosAtivos);
        $this->assertSame('Tomate', $produtosAtivos[0]->name);
    }

    /**
     * O filtro da vitrine: findActive(categoryId) traz só os produtos ativos
     * daquela categoria. É o que sustenta o "Todos | Frutas | Legumes" na tela.
     *
     * O inativo entra de propósito: garante que o filtro por categoria não
     * afrouxa o filtro por `active` — os dois recortes valem juntos.
     */
    public function test_findActive_filtra_por_categoria(): void
    {
        $this->criarCategoria(1, 'Legumes');
        $this->criarCategoria(2, 'Frutas');

        $this->criarProduto(nome: 'Tomate', ativo: true,  categoriaId: 1);
        $this->criarProduto(nome: 'Maçã',   ativo: true,  categoriaId: 2);
        $this->criarProduto(nome: 'Banana', ativo: false, categoriaId: 2);

        $frutas = new ProductRepository($this->pdo)->findActive(categoryId: 2);

        $this->assertCount(1, $frutas);
        $this->assertSame('Maçã', $frutas[0]->name);
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

    /**
     * Insere só o mínimo que o schema exige: cost_price e stock_quantity têm
     * DEFAULT na migration, e ean/image são nulláveis. O created_at não tem
     * default, então precisa vir sempre.
     *
     * O `active` também tem default, mas entra explícito de propósito — é a
     * única coluna que este teste discrimina.
     */
    private function criarProduto(string $nome, bool $ativo, int $categoriaId = 1): void
    {
        $sql = $this->pdo->prepare(
            'INSERT INTO products (name, category_id, unit, sale_price, active, created_at)
             VALUES (:nome, :categoria_id, :unidade, :preco, :ativo, :criado_em)'
        );

        $sql->execute([
            'nome'         => $nome,
            'categoria_id' => $categoriaId,
            'unidade'      => 'kg',

            // Preço como STRING, não float: é DECIMAL(10,2) no banco. Float não
            // representa 0,10 exatamente — a mesma razão da migration.
            'preco'        => '7.90',

            // No SQLite não existe BOOLEAN de verdade; é 0/1.
            'ativo'        => $ativo ? 1 : 0,

            'criado_em'    => '2026-07-22 10:00:00',
        ]);
    }
}