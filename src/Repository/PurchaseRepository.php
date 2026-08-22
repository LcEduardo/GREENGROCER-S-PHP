<?php

declare(strict_types=1);

namespace User\Greengrocers\Repository;

use DateTimeImmutable;
use DomainException;
use PDO;
use Throwable;
use User\Greengrocers\Model\Purchase;
use User\Greengrocers\Model\PurchaseItem;

/**
 * Persistência do pedido de compra.
 *
 * Duas escritas, com pesos bem diferentes:
 *
 * - `save()` grava o pedido e seus itens. Enquanto ABERTO, é só uma lista: não
 *   encosta em `products` nem em `stock_movements`.
 * - `finalize()` é o que MOVIMENTA o estoque. Para um pedido de N itens são N
 *   UPDATEs em `products` e N INSERTs em `stock_movements`, mais o UPDATE do
 *   status — e ou tudo isso acontece, ou nada acontece.
 *
 * ⚠️ REQUISITO TÉCNICO: `finalize()` é atômico. Uma falha na quinta linha de um
 * pedido de dez não pode deixar cinco produtos com estoque somado, o livro de
 * movimentações pela metade e o pedido ainda aberto — seria um estoque errado
 * que ninguém consegue reconstruir depois. Por isso toda a operação roda dentro
 * de uma transação, e qualquer exceção derruba o conjunto inteiro.
 */
class PurchaseRepository
{
    /** A frase que fica no `historical` do livro de movimentações. O `%d` é o id do pedido. */
    private const DESCRICAO_ENTRADA = 'Entrada de produto pelo pedido %d';

    public function __construct(private readonly PDO $pdo)
    {
    }

    // ===== Leitura =====

    /**
     * A tela inicial: todos os pedidos, do mais novo para o mais velho.
     *
     * @return Purchase[]
     */
    public function findAll(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, supplier_id, user_id, purchased_at, notes, status_id'
            . ' FROM purchases'
            . ' ORDER BY purchased_at DESC, id DESC'
        );

        $linhas = $statement->fetchAll();

        if ($linhas === []) {
            return [];
        }

        // Uma consulta só para os itens de TODOS os pedidos, agrupada em
        // memória: com uma consulta por pedido, a lista faria N+1 idas ao banco
        // para desenhar uma tela que cabe numa rolagem.
        $itensPorPedido = $this->findItemsGroupedByPurchase(
            array_map(static fn (array $linha) => (int) $linha['id'], $linhas),
        );

        return array_map(
            fn (array $linha) => $this->hydrate($linha, $itensPorPedido[(int) $linha['id']] ?? []),
            $linhas,
        );
    }

    public function findById(int $id): ?Purchase
    {
        $statement = $this->pdo->prepare(
            'SELECT id, supplier_id, user_id, purchased_at, notes, status_id'
            . ' FROM purchases WHERE id = :id'
        );
        $statement->execute(['id' => $id]);

        $linha = $statement->fetch();

        if ($linha === false) {
            return null;
        }

        return $this->hydrate($linha, $this->findItemsGroupedByPurchase([$id])[$id] ?? []);
    }

    // ===== Escrita =====

    /**
     * Grava o pedido: INSERT quando não tem id, UPDATE quando tem.
     *
     * Os itens são REESCRITOS por inteiro (apaga os antigos, insere os que
     * chegaram). É o que faz uma única tela dar conta de adicionar, remover e
     * alterar item de uma vez — o formulário manda a lista final, e não um
     * diário de alterações.
     *
     * @return int O id do pedido, gerado ou o mesmo de entrada.
     */
    public function save(Purchase $purchase): int
    {
        // Status FINALIZADO só é escrito por finalize(), que é quem também
        // lança o estoque. Sem esta trava, dava para gravar um pedido
        // "finalizado" cujas entradas nunca aconteceram.
        if ($purchase->isFinalizado()) {
            throw new DomainException(
                'Um pedido só chega a finalizado pelo finalize(), que é quem lança o estoque.'
            );
        }

        return $this->transaction(function () use ($purchase): int {
            $this->garanteProdutosAtivos($purchase->items);

            $id = $purchase->id === null
                ? $this->insere($purchase)
                : $this->atualiza($purchase);

            $this->gravaItens($id, $purchase->items);

            return $id;
        });
    }

    /**
     * Fecha o pedido e lança o estoque — o único caminho para o status 1.
     *
     * Para CADA item: soma a quantidade no estoque do produto e escreve uma
     * linha no livro de movimentações. Item repetido não é agrupado de
     * propósito: o estoque acaba somando os dois (é o mesmo UPDATE relativo
     * rodando duas vezes) e a auditoria mostra o pedido como ele foi lançado,
     * lote por lote.
     *
     * Tudo dentro de uma transação — ver o aviso no topo da classe.
     */
    public function finalize(int $id): void
    {
        $this->transaction(function () use ($id): void {
            $pedido = $this->findById($id);

            if ($pedido === null) {
                throw new DomainException('Pedido de compra não encontrado.');
            }

            if ($pedido->isFinalizado()) {
                throw new DomainException('Este pedido já foi finalizado.');
            }

            // A regra que NÃO mora no Purchase: o rascunho pode estar vazio, o
            // documento não. Finalizar um pedido sem item não lançaria nada no
            // estoque e ainda o tornaria intocável — um documento vazio e
            // permanente, que é o pior dos dois mundos.
            if ($pedido->items === []) {
                throw new DomainException('Um pedido sem item não pode ser finalizado.');
            }

            $descricao = sprintf(self::DESCRICAO_ENTRADA, $id);
            $agora = new DateTimeImmutable()->format('Y-m-d H:i:s');

            foreach ($pedido->items as $item) {
                $this->lancaNoEstoque($item);
                $this->registraMovimentacao($id, $item, $descricao, $agora);
            }

            $statement = $this->pdo->prepare('UPDATE purchases SET status_id = :status WHERE id = :id');
            $statement->execute(['status' => Purchase::FINALIZADO, 'id' => $id]);
        });
    }

    // ===== Bastidores da escrita =====

    private function insere(Purchase $purchase): int
    {
        $sql = 'INSERT INTO purchases (supplier_id, user_id, purchased_at, total_value, notes, status_id)'
             . ' VALUES (:supplierId, :userId, :purchasedAt, :totalValue, :notes, :statusId)';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'supplierId'  => $purchase->supplierId,
            'userId'      => $purchase->userId,
            'purchasedAt' => ($purchase->purchasedAt ?? new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'totalValue'  => $purchase->totalValue(),
            'notes'       => $purchase->notes,
            'statusId'    => Purchase::ABERTO,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * `user_id` e `purchased_at` ficam de fora: são o registro de quem abriu o
     * pedido e quando — editar o conteúdo não reescreve essa história.
     */
    private function atualiza(Purchase $purchase): int
    {
        $armazenado = $this->findById($purchase->id);

        if ($armazenado === null) {
            throw new DomainException('Pedido de compra não encontrado.');
        }

        if ($armazenado->isFinalizado()) {
            throw new DomainException('Pedido finalizado não pode ser editado.');
        }

        $sql = 'UPDATE purchases SET supplier_id = :supplierId, total_value = :totalValue, notes = :notes'
             . ' WHERE id = :id';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'supplierId' => $purchase->supplierId,
            'totalValue' => $purchase->totalValue(),
            'notes'      => $purchase->notes,
            'id'         => $purchase->id,
        ]);

        return $purchase->id;
    }

    /** @param PurchaseItem[] $items */
    private function gravaItens(int $purchaseId, array $items): void
    {
        $this->pdo->prepare('DELETE FROM purchase_items WHERE purchase_id = :purchaseId')
            ->execute(['purchaseId' => $purchaseId]);

        $sql = 'INSERT INTO purchase_items (purchase_id, product_id, quantity, cost_value, total_value)'
             . ' VALUES (:purchaseId, :productId, :quantity, :costValue, :totalValue)';

        // Preparado UMA vez e reexecutado por item: é o mesmo SQL variando só
        // os valores.
        $statement = $this->pdo->prepare($sql);

        foreach ($items as $item) {
            $statement->execute([
                'purchaseId' => $purchaseId,
                'productId'  => $item->productId,
                'quantity'   => $item->quantity,
                'costValue'  => $item->costValue,
                'totalValue' => $item->totalValue(),
            ]);
        }
    }

    /**
     * Soma a quantidade no estoque do produto.
     *
     * `stock_quantity + :quantidade` no próprio SQL, e não lido-somado-gravado
     * no PHP: a conta acontece no banco, sobre o valor do momento da escrita.
     *
     * O custo só é gravado quando o item TEM custo. Item sem valor é reajuste
     * de estoque (acerto de contagem, bonificação) — gravar zero por cima
     * apagaria o custo real do produto e estragaria a margem de lucro. Quando
     * há custo, ele substitui o anterior: ainda não trabalhamos com custo médio.
     */
    private function lancaNoEstoque(PurchaseItem $item): void
    {
        $trocaOCusto = (float) $item->costValue > 0;

        $sql = 'UPDATE products SET stock_quantity = stock_quantity + :quantidade'
             . ($trocaOCusto ? ', cost_price = :custo' : '')
             . ' WHERE id = :produtoId';

        $parametros = ['quantidade' => $item->quantity, 'produtoId' => $item->productId];

        if ($trocaOCusto) {
            $parametros['custo'] = $item->costValue;
        }

        $this->pdo->prepare($sql)->execute($parametros);
    }

    /** Uma linha no livro de auditoria por item lançado. */
    private function registraMovimentacao(
        int $purchaseId,
        PurchaseItem $item,
        string $descricao,
        string $quando,
    ): void {
        $sql = 'INSERT INTO stock_movements'
             . ' (product_id, type, quantity, reference_type, reference_id, moved_at, historical)'
             . " VALUES (:productId, 'in', :quantity, 'purchase', :referenceId, :movedAt, :historical)";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'productId'   => $item->productId,
            'quantity'    => $item->quantity,
            'referenceId' => $purchaseId,
            'movedAt'     => $quando,
            'historical'  => $descricao,
        ]);
    }

    /**
     * Produto inativo não entra no pedido, tenha o estoque que tiver.
     *
     * A checagem é aqui, e não no Model, porque "estar ativo" é estado do
     * BANCO: o PurchaseItem não tem como saber disso sozinho. O <select> da
     * tela já só oferece ativos — isto é a trava que vale mesmo quando o POST
     * não veio da nossa tela.
     *
     * @param PurchaseItem[] $items
     */
    private function garanteProdutosAtivos(array $items): void
    {
        // Rascunho vazio não tem produto para conferir — e `IN ()` sem nada
        // dentro é SQL inválido, não uma consulta que devolve zero linhas.
        if ($items === []) {
            return;
        }

        $ids = array_values(array_unique(array_map(
            static fn (PurchaseItem $item) => $item->productId,
            $items,
        )));

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        // `active = TRUE` funciona nos dois drivers; `active = 1` o Postgres recusa.
        $statement = $this->pdo->prepare(
            "SELECT id FROM products WHERE id IN ({$placeholders}) AND active = TRUE"
        );
        $statement->execute($ids);

        $ativos = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        $recusados = array_diff($ids, $ativos);

        if ($recusados !== []) {
            throw new DomainException(
                'Só produtos ativos podem entrar no pedido (ids recusados: '
                . implode(', ', $recusados) . ').'
            );
        }
    }

    // ===== Hidratação =====

    /**
     * @param array<string, mixed> $row
     * @param PurchaseItem[] $items
     */
    private function hydrate(array $row, array $items): Purchase
    {
        return new Purchase(
            supplierId:  (int) $row['supplier_id'],
            userId:      (int) $row['user_id'],
            items:       $items,
            statusId:    (int) $row['status_id'],
            id:          (int) $row['id'],
            purchasedAt: new DateTimeImmutable($row['purchased_at']),
            notes:       $row['notes'],
        );
    }

    /**
     * Os itens dos pedidos pedidos, agrupados por `purchase_id`.
     *
     * @param int[] $purchaseIds
     * @return array<int, PurchaseItem[]>
     */
    private function findItemsGroupedByPurchase(array $purchaseIds): array
    {
        if ($purchaseIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($purchaseIds), '?'));

        $statement = $this->pdo->prepare(
            'SELECT id, purchase_id, product_id, quantity, cost_value'
            . " FROM purchase_items WHERE purchase_id IN ({$placeholders})"
            . ' ORDER BY id'
        );
        $statement->execute($purchaseIds);

        $porPedido = [];

        foreach ($statement as $row) {
            $porPedido[(int) $row['purchase_id']][] = new PurchaseItem(
                productId: (int) $row['product_id'],
                quantity:  (string) $row['quantity'],
                costValue: (string) $row['cost_value'],
                id:        (int) $row['id'],
            );
        }

        return $porPedido;
    }

    /**
     * Roda a operação dentro de uma transação.
     *
     * O `inTransaction()` existe porque o PDO não aninha: se `save()` fosse
     * chamado de dentro de uma transação já aberta, um segundo
     * `beginTransaction()` lançaria exceção. Nesse caso a operação PARTICIPA da
     * transação de fora — quem abriu é quem decide o commit.
     *
     * @template T
     * @param callable(): T $operacao
     * @return T
     */
    private function transaction(callable $operacao): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $operacao();
        }

        $this->pdo->beginTransaction();

        try {
            $resultado = $operacao();
            $this->pdo->commit();

            return $resultado;
        } catch (Throwable $erro) {
            $this->pdo->rollBack();

            throw $erro;
        }
    }
}
