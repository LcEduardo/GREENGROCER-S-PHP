<?php

declare(strict_types=1);

namespace User\Greengrocers\Tests\Repository;

use DomainException;
use Throwable;
use User\Greengrocers\Model\Purchase;
use User\Greengrocers\Model\PurchaseItem;
use User\Greengrocers\Repository\PurchaseRepository;
use User\Greengrocers\Tests\Support\DatabaseTestCase;

class PurchaseRepositoryTest extends DatabaseTestCase
{
    private int $fornecedorId;
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarCategoria(1, 'Legumes');
        $this->fornecedorId = $this->criarFornecedor('Hortifruti do Zé');
        $this->adminId = $this->criarUsuario();
    }

    // ===== A tela inicial =====

    public function test_findAll_traz_todos_os_pedidos_criados(): void
    {
        $repository = new PurchaseRepository($this->pdo);

        $primeiro = $repository->save($this->pedido([$this->item($this->criarProduto('Tomate'), '2')]));
        $segundo  = $repository->save($this->pedido([$this->item($this->criarProduto('Cebola'), '5')]));

        $pedidos = $repository->findAll();

        $this->assertCount(2, $pedidos);
        $this->assertEqualsCanonicalizing(
            [$primeiro, $segundo],
            array_map(fn (Purchase $pedido) => $pedido->id, $pedidos),
        );
    }

    public function test_findAll_devolve_lista_vazia_quando_nao_ha_pedido(): void
    {
        $this->assertSame([], new PurchaseRepository($this->pdo)->findAll());
    }

    /** A tela inicial mostra fornecedor, total e status — tudo vem hidratado. */
    public function test_findById_traz_o_pedido_com_seus_itens(): void
    {
        $tomateId = $this->criarProduto('Tomate');
        $repository = new PurchaseRepository($this->pdo);

        $id = $repository->save($this->pedido([
            $this->item($tomateId, '2', '3.00'),
            $this->item($this->criarProduto('Cebola'), '1', '5.00'),
        ]));

        $pedido = $repository->findById($id);

        $this->assertNotNull($pedido);
        $this->assertSame($this->fornecedorId, $pedido->supplierId);
        $this->assertSame(Purchase::ABERTO, $pedido->statusId);
        $this->assertCount(2, $pedido->items);
        $this->assertSame($tomateId, $pedido->items[0]->productId);
        $this->assertSame(11.0, (float) $pedido->totalValue());
    }

    public function test_findById_retorna_null_quando_o_pedido_nao_existe(): void
    {
        $this->assertNull(new PurchaseRepository($this->pdo)->findById(999));
    }

    // ===== Salvar (status 0) =====

    /**
     * O ponto central do rascunho: salvar registra o pedido e NADA mais. O
     * estoque e o livro de movimentações só são tocados no finalize().
     */
    public function test_pedido_aberto_nao_mexe_no_estoque_nem_cria_movimentacao(): void
    {
        $tomateId = $this->criarProduto('Tomate', estoque: '10');

        new PurchaseRepository($this->pdo)->save($this->pedido([$this->item($tomateId, '7')]));

        $this->assertSame(10.0, $this->estoque($tomateId));
        $this->assertSame(0, $this->totalDeMovimentacoes());
    }

    public function test_save_recusa_item_de_produto_inativo(): void
    {
        $inativoId = $this->criarProduto('Abóbora fora de época', ativo: false);

        $this->expectException(DomainException::class);

        new PurchaseRepository($this->pdo)->save($this->pedido([$this->item($inativoId, '3')]));
    }

    /**
     * Produto ativo entra independentemente do estoque atual: pedido de compra
     * é justamente o que repõe o que está zerado.
     */
    public function test_save_aceita_produto_ativo_com_estoque_zerado(): void
    {
        $zeradoId = $this->criarProduto('Tomate', estoque: '0');

        $id = new PurchaseRepository($this->pdo)->save($this->pedido([$this->item($zeradoId, '3')]));

        $this->assertGreaterThan(0, $id);
    }

    public function test_save_persiste_item_com_custo_zerado(): void
    {
        $tomateId = $this->criarProduto('Tomate');
        $repository = new PurchaseRepository($this->pdo);

        $id = $repository->save($this->pedido([$this->item($tomateId, '3', '0')]));

        $pedido = $repository->findById($id);

        $this->assertSame(0.0, (float) $pedido->items[0]->costValue);
        $this->assertSame(0.0, (float) $pedido->totalValue());
    }

    /** Enquanto aberto, o pedido é editável por inteiro: fornecedor e itens. */
    public function test_save_edita_pedido_aberto_trocando_fornecedor_e_itens(): void
    {
        $tomateId = $this->criarProduto('Tomate');
        $cebolaId = $this->criarProduto('Cebola');
        $outroFornecedor = $this->criarFornecedor('Sítio Boa Vista');

        $repository = new PurchaseRepository($this->pdo);
        $id = $repository->save($this->pedido([$this->item($tomateId, '2')]));

        $repository->save(new Purchase(
            id: $id,
            supplierId: $outroFornecedor,
            userId: $this->adminId,
            items: [$this->item($cebolaId, '9', '2.00')],
        ));

        $pedido = $repository->findById($id);

        $this->assertSame($outroFornecedor, $pedido->supplierId);
        $this->assertCount(1, $pedido->items);
        $this->assertSame($cebolaId, $pedido->items[0]->productId);
        $this->assertSame(9.0, (float) $pedido->items[0]->quantity);

        // Editar não pode virar um pedido novo: o id é o mesmo do começo.
        $this->assertCount(1, $repository->findAll());
    }

    public function test_save_recusa_edicao_de_pedido_ja_finalizado(): void
    {
        $tomateId = $this->criarProduto('Tomate');

        $repository = new PurchaseRepository($this->pdo);
        $id = $repository->save($this->pedido([$this->item($tomateId, '2')]));
        $repository->finalize($id);

        $this->expectException(DomainException::class);

        $repository->save(new Purchase(
            id: $id,
            supplierId: $this->fornecedorId,
            userId: $this->adminId,
            items: [$this->item($tomateId, '99')],
        ));
    }

    // ===== Finalizar (status 1) =====

    public function test_finalize_soma_a_quantidade_no_estoque_do_produto(): void
    {
        $tomateId = $this->criarProduto('Tomate', estoque: '10');

        $repository = new PurchaseRepository($this->pdo);
        $id = $repository->save($this->pedido([$this->item($tomateId, '7.5')]));
        $repository->finalize($id);

        $this->assertSame(17.5, $this->estoque($tomateId));
        $this->assertTrue($repository->findById($id)->isFinalizado());
    }

    public function test_finalize_substitui_o_custo_do_produto(): void
    {
        $tomateId = $this->criarProduto('Tomate', custo: '4.00');

        $repository = new PurchaseRepository($this->pdo);
        $id = $repository->save($this->pedido([$this->item($tomateId, '2', '6.50')]));
        $repository->finalize($id);

        $this->assertSame(6.5, $this->custo($tomateId));
    }

    /**
     * Item sem custo é reajuste de estoque, não compra: gravar o zero por cima
     * apagaria o custo real do produto e estragaria a margem de lucro.
     */
    public function test_finalize_mantem_o_custo_quando_o_item_vem_sem_valor(): void
    {
        $tomateId = $this->criarProduto('Tomate', estoque: '10', custo: '4.00');

        $repository = new PurchaseRepository($this->pdo);
        $id = $repository->save($this->pedido([$this->item($tomateId, '2', '0')]));
        $repository->finalize($id);

        $this->assertSame(4.0, $this->custo($tomateId));
        $this->assertSame(12.0, $this->estoque($tomateId)); // o estoque entra mesmo assim
    }

    public function test_finalize_cria_uma_movimentacao_de_entrada_por_item(): void
    {
        $tomateId = $this->criarProduto('Tomate');
        $cebolaId = $this->criarProduto('Cebola');

        $repository = new PurchaseRepository($this->pdo);
        $id = $repository->save($this->pedido([
            $this->item($tomateId, '3'),
            $this->item($cebolaId, '4'),
        ]));
        $repository->finalize($id);

        $movimentacoes = $this->movimentacoes();

        $this->assertCount(2, $movimentacoes);

        foreach ($movimentacoes as $movimentacao) {
            $this->assertSame('in', $movimentacao['type']);
            $this->assertSame('purchase', $movimentacao['reference_type']);
            $this->assertSame($id, (int) $movimentacao['reference_id']);
            $this->assertSame('Entrada de produto pelo pedido ' . $id, $movimentacao['description']);
        }

        $this->assertSame($tomateId, (int) $movimentacoes[0]['product_id']);
        $this->assertSame(3.0, (float) $movimentacoes[0]['quantity']);
        $this->assertSame($cebolaId, (int) $movimentacoes[1]['product_id']);
        $this->assertSame(4.0, (float) $movimentacoes[1]['quantity']);
    }

    public function test_finalize_recusa_pedido_ja_finalizado(): void
    {
        $tomateId = $this->criarProduto('Tomate', estoque: '10');

        $repository = new PurchaseRepository($this->pdo);
        $id = $repository->save($this->pedido([$this->item($tomateId, '5')]));
        $repository->finalize($id);

        try {
            $repository->finalize($id);
            $this->fail('finalize() deveria recusar um pedido já finalizado.');
        } catch (DomainException) {
        }

        // O que importa não é a exceção, é que o estoque não entrou duas vezes.
        $this->assertSame(15.0, $this->estoque($tomateId));
        $this->assertSame(1, $this->totalDeMovimentacoes());
    }

    /**
     * O mesmo produto em duas linhas do pedido (ex.: dois lotes, custos
     * diferentes): o estoque recebe a SOMA das duas, e cada linha vira uma
     * movimentação própria — o livro de auditoria registra o pedido como ele
     * foi lançado.
     */
    public function test_finalize_soma_as_quantidades_quando_o_mesmo_produto_se_repete(): void
    {
        $tomateId = $this->criarProduto('Tomate', estoque: '10');

        $repository = new PurchaseRepository($this->pdo);
        $id = $repository->save($this->pedido([
            $this->item($tomateId, '3', '4.00'),
            $this->item($tomateId, '2', '5.00'),
        ]));
        $repository->finalize($id);

        $this->assertSame(15.0, $this->estoque($tomateId));
        $this->assertSame(2, $this->totalDeMovimentacoes());

        // Sem custo médio ainda: o último item lançado é o que fica.
        $this->assertSame(5.0, $this->custo($tomateId));
    }

    /**
     * O requisito técnico da transação, exercitado: um pedido com dois itens
     * escreve em `products` e em `stock_movements` várias vezes, e uma falha no
     * meio não pode deixar metade gravada.
     *
     * O gatilho derruba a SEGUNDA movimentação de propósito — a primeira já
     * entrou, então o teste só passa se o rollback desfizer também o que já
     * tinha dado certo.
     */
    public function test_finalize_nao_persiste_nada_quando_uma_das_escritas_falha(): void
    {
        $tomateId = $this->criarProduto('Tomate', estoque: '10');
        $cebolaId = $this->criarProduto('Cebola', estoque: '20');

        $repository = new PurchaseRepository($this->pdo);
        $id = $repository->save($this->pedido([
            $this->item($tomateId, '3'),
            $this->item($cebolaId, '4'),
        ]));

        $this->pdo->exec(
            "CREATE TRIGGER falha_na_segunda_movimentacao BEFORE INSERT ON stock_movements
             WHEN (SELECT COUNT(*) FROM stock_movements) >= 1
             BEGIN SELECT RAISE(ABORT, 'falha simulada no meio do finalize'); END"
        );

        try {
            $repository->finalize($id);
            $this->fail('finalize() deveria ter propagado a falha da segunda escrita.');
        } catch (Throwable) {
        }

        $this->assertSame(10.0, $this->estoque($tomateId));
        $this->assertSame(20.0, $this->estoque($cebolaId));
        $this->assertSame(0, $this->totalDeMovimentacoes());
        $this->assertFalse($repository->findById($id)->isFinalizado());
    }

    // ===== Apoio =====

    /** @param PurchaseItem[] $items */
    private function pedido(array $items): Purchase
    {
        return new Purchase(
            supplierId: $this->fornecedorId,
            userId: $this->adminId,
            items: $items,
        );
    }

    private function item(int $produtoId, string $quantidade, string $custo = '1.00'): PurchaseItem
    {
        return new PurchaseItem(productId: $produtoId, quantity: $quantidade, costValue: $custo);
    }

    private function estoque(int $produtoId): float
    {
        return (float) $this->coluna('stock_quantity', $produtoId);
    }

    private function custo(int $produtoId): float
    {
        return (float) $this->coluna('cost_price', $produtoId);
    }

    private function coluna(string $coluna, int $produtoId): string
    {
        $statement = $this->pdo->prepare("SELECT {$coluna} FROM products WHERE id = :id");
        $statement->execute(['id' => $produtoId]);

        return (string) $statement->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    private function movimentacoes(): array
    {
        return $this->pdo->query('SELECT * FROM stock_movements ORDER BY id')->fetchAll();
    }

    private function totalDeMovimentacoes(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn();
    }

    private function criarFornecedor(string $nome): int
    {
        $statement = $this->pdo->prepare('INSERT INTO suppliers (name) VALUES (:nome)');
        $statement->execute(['nome' => $nome]);

        return (int) $this->pdo->lastInsertId();
    }

    private function criarUsuario(): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (name, email, password, admin, created_at)
             VALUES (:nome, :email, :senha, :admin, :criado_em)'
        );

        $statement->execute([
            'nome'      => 'Admin',
            'email'     => 'admin@example.com',
            'senha'     => 'hash',
            'admin'     => 1,
            'criado_em' => '2026-08-15 10:00:00',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function criarCategoria(int $id, string $nome): void
    {
        $statement = $this->pdo->prepare('INSERT INTO categories (id, name) VALUES (:id, :nome)');
        $statement->execute(['id' => $id, 'nome' => $nome]);
    }

    private function criarProduto(
        string $nome,
        bool $ativo = true,
        string $estoque = '0',
        string $custo = '0',
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO products (name, category_id, unit, sale_price, cost_price, stock_quantity, active, created_at)
             VALUES (:nome, 1, :unidade, :preco, :custo, :estoque, :ativo, :criado_em)'
        );

        $statement->execute([
            'nome'      => $nome,
            'unidade'   => 'kg',
            'preco'     => '7.90',
            'custo'     => $custo,
            'estoque'   => $estoque,
            'ativo'     => $ativo ? 1 : 0,
            'criado_em' => '2026-08-15 10:00:00',
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
