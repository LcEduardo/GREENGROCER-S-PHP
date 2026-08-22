<?php

declare(strict_types=1);

namespace User\Greengrocers\Repository;

use DateTimeImmutable;
use PDO;
use User\Greengrocers\Model\StockMovement;

class StockMovementRepository
{
    public const POR_PAGINA = 7;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Uma página do livro, da movimentação mais nova para a mais velha.
     *
     * ⚠️ A ORDEM É O CONTRATO DA PAGINAÇÃO. `moved_at DESC` sozinho não basta:
     * um pedido de compra finalizado grava TODOS os seus itens com o mesmo
     * `moved_at` (é um `DateTimeImmutable` só, criado uma vez no finalize()), e
     * empate sem desempate deixa o banco livre para devolver as linhas em
     * ordens diferentes a cada consulta. Numa tela paginada isso é uma linha
     * repetida na página 2 e outra que nunca aparece. O `id DESC` desempata, e
     * como o id é único a ordem passa a ser total — a mesma em toda chamada.
     *
     * @param ?string $search Parte do nome do produto; null traz tudo.
     * @return StockMovement[]
     */
    public function findPage(
        ?string $search = null,
        int $limit = self::POR_PAGINA,
        int $offset = 0,
    ): array {
        [$filtro, $parametros] = $this->filtro($search);

        $limite = max(1, $limit);
        $deslocamento = max(0, $offset);

        // O nome do produto e o do fornecedor vêm colados na MESMA consulta: a
        // tela mostra nome, e perguntar "quem é o produto 12?" linha a linha
        // seria o N+1 de volta.
        $sql = 'SELECT m.id, m.product_id, m.type, m.quantity, m.reference_type,'
             . ' m.reference_id, m.moved_at, m.historical,'
             . ' p.name AS product_name,'
             . ' s.name AS supplier_name'
             . ' FROM stock_movements m'
             . ' INNER JOIN products p ON p.id = m.product_id'
             . " LEFT JOIN purchases c ON m.reference_type = 'purchase' AND c.id = m.reference_id"
             . ' LEFT JOIN suppliers s ON s.id = c.supplier_id'
             . $filtro
             . ' ORDER BY m.moved_at DESC, m.id DESC'
             . " LIMIT {$limite} OFFSET {$deslocamento}";

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parametros);

        $movimentacoes = [];

        foreach ($statement as $row) {
            $movimentacoes[] = $this->hydrate($row);
        }

        return $movimentacoes;
    }

    public function countAll(?string $search = null): int
    {
        [$filtro, $parametros] = $this->filtro($search);

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM stock_movements m'
            . ' INNER JOIN products p ON p.id = m.product_id'
            . $filtro
        );
        $statement->execute($parametros);

        return (int) $statement->fetchColumn();
    }

    public static function totalDePaginas(int $total): int
    {
        return max(1, (int) ceil($total / self::POR_PAGINA));
    }

    private function filtro(?string $search): array
    {
        $searchProduct = trim((string) $search);

        if ($searchProduct === '') {
            return ['', []];
        }

        return [
            ' WHERE LOWER(p.name) LIKE LOWER(:search)',
            ['search' => '%' . $searchProduct . '%'],
        ];
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): StockMovement
    {
        return new StockMovement(
            id:            (int) $row['id'],
            productId:     (int) $row['product_id'],
            productName:   $row['product_name'],
            type:          $row['type'],
            quantity:      (string) $row['quantity'],
            referenceType: $row['reference_type'],

            // Continua nulo quando é ajuste manual — um (int) cru transformaria
            // "sem referência" em "referência 0", que aponta para nada.
            referenceId:   $row['reference_id'] === null ? null : (int) $row['reference_id'],

            movedAt:       new DateTimeImmutable($row['moved_at']),
            historical:    $row['historical'],
            supplierName:  $row['supplier_name'],
        );
    }
}
