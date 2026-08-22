<?php

declare(strict_types=1);

namespace User\Greengrocers\Tests\Repository;

use User\Greengrocers\Model\StockMovement;
use User\Greengrocers\Repository\StockMovementRepository;
use User\Greengrocers\Tests\Support\DatabaseTestCase;

/**
 * A tela de movimentações de estoque.
 *
 * O que está sendo defendido aqui é a CONFIANÇA na lista: ela é o livro de
 * auditoria do estoque, e uma linha que não aparece — ou que aparece duas
 * vezes — é pior do que não ter a tela. Por isso os testes de contagem batem o
 * que a tela mostra contra um `COUNT(*)` direto no banco, em vez de contra um
 * número escrito à mão no teste: o `COUNT(*)` continua verdadeiro no dia em que
 * a consulta do repositório ganhar um JOIN a mais.
 */
class StockMovementRepositoryTest extends DatabaseTestCase
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

    // ===== Contagem: o que a tela mostra é tudo o que existe =====

    /**
     * Sem filtro: o total do banco tem que bater com o total das movimentações
     * exibidas — somando TODAS as páginas, que é como quem varre o livro pelos
     * links do rodapé o percorre. Paginação errada aparece aqui: repetir uma
     * linha infla a soma, pular uma a encolhe.
     */
    public function test_total_sem_filtro_bate_com_a_contagem_no_banco(): void
    {
        $tomateId = $this->criarProduto('Tomate');
        $cebolaId = $this->criarProduto('Cebola');

        $this->criarMovimentacao($tomateId, '2026-08-01 08:00:00');
        $this->criarMovimentacao($cebolaId, '2026-08-02 08:00:00');
        $this->criarMovimentacao($tomateId, '2026-08-03 08:00:00');

        $repository = new StockMovementRepository($this->pdo);

        $this->assertSame($this->totalNoBanco(), $repository->countAll());
        $this->assertCount($this->totalNoBanco(), $this->todasAsPaginas($repository));
    }

    /**
     * Com filtro: o total tem que bater com a contagem daquele produto — não
     * com a da tabela inteira. Um filtro que conta tudo e lista só uma parte
     * faria o rodapé numerar páginas que voltam vazias.
     */
    public function test_total_com_filtro_bate_com_a_contagem_daquele_produto(): void
    {
        $tomateId = $this->criarProduto('Tomate');
        $cebolaId = $this->criarProduto('Cebola');

        $this->criarMovimentacao($tomateId, '2026-08-01 08:00:00');
        $this->criarMovimentacao($tomateId, '2026-08-02 08:00:00');
        $this->criarMovimentacao($cebolaId, '2026-08-03 08:00:00');

        $repository = new StockMovementRepository($this->pdo);

        $this->assertSame($this->totalNoBanco($tomateId), $repository->countAll('Tomate'));
        $this->assertCount(
            $this->totalNoBanco($tomateId),
            $this->todasAsPaginas($repository, 'Tomate'),
        );
    }

    public function test_total_com_filtro_sem_resultado_e_zero(): void
    {
        $this->criarMovimentacao($this->criarProduto('Tomate'), '2026-08-01 08:00:00');

        $repository = new StockMovementRepository($this->pdo);

        $this->assertSame(0, $repository->countAll('Jabuticaba'));
        $this->assertSame([], $repository->findPage('Jabuticaba'));
    }

    // ===== Paginação =====

    /**
     * A tela mostra 7 por vez, e o número mora numa constante só porque ele é
     * usado em DOIS lugares que precisam concordar: o `LIMIT` da consulta e a
     * divisão que numera as páginas do rodapé. Se discordassem, o rodapé
     * ofereceria uma página que a consulta devolve vazia.
     */
    public function test_pagina_traz_no_maximo_sete_movimentacoes(): void
    {
        $this->criarMovimentacoes($this->criarProduto('Tomate'), 8);

        $this->assertSame(7, StockMovementRepository::POR_PAGINA);
        $this->assertCount(7, new StockMovementRepository($this->pdo)->findPage());
    }

    /** A última página vem incompleta — e o que sobrou não pode sumir. */
    public function test_ultima_pagina_traz_o_que_sobrou(): void
    {
        $this->criarMovimentacoes($this->criarProduto('Tomate'), 8);

        $ultima = new StockMovementRepository($this->pdo)->findPage(
            null,
            StockMovementRepository::POR_PAGINA,
            StockMovementRepository::POR_PAGINA,
        );

        $this->assertCount(1, $ultima);
    }

    /**
     * A conta que o rodapé usa arredonda PARA CIMA: 8 movimentações em páginas
     * de 7 são duas páginas, não uma e um resto que ninguém alcança.
     */
    public function test_total_de_paginas_arredonda_para_cima(): void
    {
        $tomateId = $this->criarProduto('Tomate');
        $repository = new StockMovementRepository($this->pdo);

        $this->criarMovimentacoes($tomateId, 7);
        $this->assertSame(1, StockMovementRepository::totalDePaginas($repository->countAll()));

        $this->criarMovimentacao($tomateId, '2026-08-08 08:00:00');
        $this->assertSame(2, StockMovementRepository::totalDePaginas($repository->countAll()));
    }

    /**
     * Lista vazia ainda é "página 1 de 1". Zero páginas deixaria o rodapé
     * dizendo "1 de 0", e a página atual não caberia dentro do próprio total.
     */
    public function test_total_de_paginas_e_um_quando_nao_ha_movimentacao(): void
    {
        $repository = new StockMovementRepository($this->pdo);

        $this->assertSame(0, $repository->countAll());
        $this->assertSame(1, StockMovementRepository::totalDePaginas($repository->countAll()));
    }

    /**
     * Filtrou, a conta muda junto — é o mesmo `WHERE` do countAll(). Um total
     * de páginas cego ao filtro ofereceria links para páginas vazias.
     */
    public function test_total_de_paginas_respeita_o_filtro(): void
    {
        $this->criarMovimentacoes($this->criarProduto('Tomate'), 8);
        $this->criarMovimentacoes($this->criarProduto('Cebola'), 2);

        $repository = new StockMovementRepository($this->pdo);

        $this->assertSame(2, StockMovementRepository::totalDePaginas($repository->countAll()));
        $this->assertSame(1, StockMovementRepository::totalDePaginas($repository->countAll('Cebola')));
    }

    /**
     * O contrato da paginação numerada: a página 2 começa exatamente onde a 1
     * parou. Repetir uma linha ou pular outra é o defeito que este teste pega —
     * e num livro de auditoria isso é pior do que não ter a tela.
     */
    public function test_paginas_nao_repetem_nem_pulam_movimentacao(): void
    {
        $esperados = $this->criarMovimentacoes($this->criarProduto('Tomate'), 9);

        $repository = new StockMovementRepository($this->pdo);
        $porPagina = StockMovementRepository::POR_PAGINA;

        $ids = array_map(
            static fn (StockMovement $movimentacao) => $movimentacao->id,
            array_merge(
                $repository->findPage(null, $porPagina, 0),
                $repository->findPage(null, $porPagina, $porPagina),
            ),
        );

        $this->assertSame($esperados, $ids);
    }

    // ===== Ordenação =====

    /** A mais recente em cima: é o que se quer ver ao abrir a auditoria. */
    public function test_lista_vem_ordenada_por_moved_at_decrescente(): void
    {
        $tomateId = $this->criarProduto('Tomate');

        // Inseridas fora de ordem de propósito: se a consulta esquecer o ORDER
        // BY, o SQLite devolve na ordem de inserção e o teste pega.
        $this->criarMovimentacao($tomateId, '2026-08-02 08:00:00');
        $this->criarMovimentacao($tomateId, '2026-08-10 08:00:00');
        $this->criarMovimentacao($tomateId, '2026-08-05 08:00:00');

        $datas = array_map(
            static fn (StockMovement $movimentacao) => $movimentacao->movedAt->format('Y-m-d H:i:s'),
            new StockMovementRepository($this->pdo)->findPage(),
        );

        $this->assertSame(
            ['2026-08-10 08:00:00', '2026-08-05 08:00:00', '2026-08-02 08:00:00'],
            $datas,
        );
    }

    /**
     * Duas movimentações no MESMO instante (um pedido de compra finalizado
     * lança todos os itens com o mesmo `moved_at`) precisam de um desempate
     * estável, senão a página 2 pode repetir uma linha da página 1.
     */
    public function test_movimentacoes_no_mesmo_instante_tem_ordem_estavel(): void
    {
        $tomateId = $this->criarProduto('Tomate');

        $primeira = $this->criarMovimentacao($tomateId, '2026-08-01 08:00:00');
        $segunda  = $this->criarMovimentacao($tomateId, '2026-08-01 08:00:00');
        $terceira = $this->criarMovimentacao($tomateId, '2026-08-01 08:00:00');

        $repository = new StockMovementRepository($this->pdo);

        $ids = array_map(
            static fn (StockMovement $movimentacao) => $movimentacao->id,
            $repository->findPage(),
        );

        $this->assertSame([$terceira, $segunda, $primeira], $ids);

        // E a paginação segue o mesmo desempate: sem ele, uma página repetiria
        // o que a outra já mostrou.
        $this->assertSame($ids, $this->todasAsPaginas($repository, limite: 2));
    }

    // ===== Filtro por produto =====

    public function test_lista_filtrada_traz_so_movimentacoes_daquele_produto(): void
    {
        $tomateId = $this->criarProduto('Tomate');
        $cebolaId = $this->criarProduto('Cebola');

        $this->criarMovimentacao($tomateId, '2026-08-01 08:00:00');
        $this->criarMovimentacao($cebolaId, '2026-08-02 08:00:00');
        $this->criarMovimentacao($tomateId, '2026-08-03 08:00:00');

        $movimentacoes = new StockMovementRepository($this->pdo)->findPage('Tomate');

        $this->assertCount(2, $movimentacoes);

        foreach ($movimentacoes as $movimentacao) {
            $this->assertSame($tomateId, $movimentacao->productId);
            $this->assertSame('Tomate', $movimentacao->productName);
        }
    }

    /** Quem digita "tom" está procurando Tomate — e sem se importar com maiúscula. */
    public function test_filtro_aceita_parte_do_nome_e_ignora_maiusculas(): void
    {
        $tomateId = $this->criarProduto('Tomate italiano');

        $this->criarMovimentacao($tomateId, '2026-08-01 08:00:00');
        $this->criarMovimentacao($this->criarProduto('Cebola'), '2026-08-02 08:00:00');

        $repository = new StockMovementRepository($this->pdo);

        $this->assertCount(1, $repository->findPage('tom'));
        $this->assertCount(1, $repository->findPage('TOMATE'));
        $this->assertSame(1, $repository->countAll('tOmAtE iTaLiAnO'));
    }

    // ===== Tipo de movimento =====

    /**
     * A coluna "Tp. Movimento" mostra o `reference_type`, mas a direção da
     * linha sai do `type` — os dois têm que contar a mesma história: compra
     * ENTRA, venda SAI.
     */
    public function test_compra_aparece_como_entrada_e_venda_como_saida(): void
    {
        $tomateId = $this->criarProduto('Tomate');

        $this->criarMovimentacao($tomateId, '2026-08-02 08:00:00', tipo: 'in', origem: 'purchase');
        $this->criarMovimentacao($tomateId, '2026-08-01 08:00:00', tipo: 'out', origem: 'sale');

        [$compra, $venda] = new StockMovementRepository($this->pdo)->findPage();

        $this->assertSame('purchase', $compra->referenceType);
        $this->assertSame(StockMovement::ENTRADA, $compra->type);
        $this->assertTrue($compra->isEntrada());

        $this->assertSame('sale', $venda->referenceType);
        $this->assertSame(StockMovement::SAIDA, $venda->type);
        $this->assertFalse($venda->isEntrada());
    }

    // ===== Fornecedor =====

    /**
     * A coluna Supplier só tem dono quando a movimentação veio de um PEDIDO DE
     * COMPRA — é o único caso em que existe fornecedor do outro lado. Venda e
     * ajuste manual ficam em branco, e não com o nome de um fornecedor
     * qualquer.
     */
    public function test_fornecedor_so_aparece_em_movimentacao_de_compra(): void
    {
        $tomateId = $this->criarProduto('Tomate');
        $pedidoId = $this->criarPedidoDeCompra();

        $this->criarMovimentacao($tomateId, '2026-08-03 08:00:00', origem: 'purchase', referenciaId: $pedidoId);
        $this->criarMovimentacao($tomateId, '2026-08-02 08:00:00', tipo: 'out', origem: 'sale', referenciaId: 1);
        $this->criarMovimentacao($tomateId, '2026-08-01 08:00:00', tipo: 'adjustment', origem: 'manual_adjustment');

        [$compra, $venda, $ajuste] = new StockMovementRepository($this->pdo)->findPage();

        $this->assertSame('Hortifruti do Zé', $compra->supplierName);
        $this->assertNull($venda->supplierName);
        $this->assertNull($ajuste->supplierName);
    }

    // ===== Histórico =====

    public function test_traz_o_historico_gravado_na_movimentacao(): void
    {
        $tomateId = $this->criarProduto('Tomate');

        $this->criarMovimentacao($tomateId, '2026-08-01 08:00:00', historico: 'Entrada de produto pelo pedido 7');

        $this->assertSame(
            'Entrada de produto pelo pedido 7',
            new StockMovementRepository($this->pdo)->findPage()[0]->historical,
        );
    }

    /** A coluna é nullable — ajuste manual ainda não escreve nada nela. */
    public function test_aceita_movimentacao_sem_historico(): void
    {
        $tomateId = $this->criarProduto('Tomate');

        $this->criarMovimentacao($tomateId, '2026-08-01 08:00:00', historico: null);

        $this->assertNull(new StockMovementRepository($this->pdo)->findPage()[0]->historical);
    }

    // ===== Apoio =====

    /**
     * Percorre a lista inteira, página por página, até vir uma incompleta —
     * como quem clica em "Próxima" até o fim. Devolve os ids na ordem em que a
     * tela os mostraria.
     *
     * @return int[]
     */
    private function todasAsPaginas(
        StockMovementRepository $repository,
        ?string $searchProduct = null,
        int $limite = 2,
    ): array {
        $ids = [];
        $offset = 0;

        do {
            $pagina = $repository->findPage($searchProduct, $limite, $offset);

            foreach ($pagina as $movimentacao) {
                $ids[] = $movimentacao->id;
            }

            $offset += $limite;
        } while (count($pagina) === $limite);

        return $ids;
    }

    private function totalNoBanco(?int $produtoId = null): int
    {
        if ($produtoId === null) {
            return (int) $this->pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn();
        }

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM stock_movements WHERE product_id = :id');
        $statement->execute(['id' => $produtoId]);

        return (int) $statement->fetchColumn();
    }

    private function criarMovimentacao(
        int $produtoId,
        string $quando,
        string $tipo = 'in',
        string $origem = 'purchase',
        ?int $referenciaId = null,
        string $quantidade = '3',
        ?string $historico = 'Movimentação de teste',
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO stock_movements
                (product_id, type, quantity, reference_type, reference_id, moved_at, historical)
             VALUES (:produto, :tipo, :quantidade, :origem, :referencia, :quando, :historico)'
        );

        $statement->execute([
            'produto'    => $produtoId,
            'tipo'       => $tipo,
            'quantidade' => $quantidade,
            'origem'     => $origem,
            'referencia' => $referenciaId,
            'quando'     => $quando,
            'historico'  => $historico,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * N movimentações do mesmo produto, uma por dia, para encher mais de uma
     * página. Devolve os ids NA ORDEM DA TELA (a mais recente primeiro), que é
     * contra ela que os testes de paginação comparam.
     *
     * @return int[]
     */
    private function criarMovimentacoes(int $produtoId, int $quantas): array
    {
        $ids = [];

        for ($dia = 1; $dia <= $quantas; $dia++) {
            $ids[] = $this->criarMovimentacao($produtoId, sprintf('2026-08-%02d 08:00:00', $dia));
        }

        return array_reverse($ids);
    }

    private function criarPedidoDeCompra(): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO purchases (supplier_id, user_id, purchased_at, total_value, status_id)
             VALUES (:fornecedor, :usuario, :quando, :total, 1)'
        );

        $statement->execute([
            'fornecedor' => $this->fornecedorId,
            'usuario'    => $this->adminId,
            'quando'     => '2026-08-03 08:00:00',
            'total'      => '30.00',
        ]);

        return (int) $this->pdo->lastInsertId();
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

    private function criarProduto(string $nome): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO products (name, category_id, unit, sale_price, cost_price, stock_quantity, active, created_at)
             VALUES (:nome, 1, :unidade, :preco, :custo, :estoque, 1, :criado_em)'
        );

        $statement->execute([
            'nome'      => $nome,
            'unidade'   => 'kg',
            'preco'     => '7.90',
            'custo'     => '4.00',
            'estoque'   => '10',
            'criado_em' => '2026-08-15 10:00:00',
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
