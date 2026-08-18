<?php

declare(strict_types=1);

namespace User\Greengrocers\Model;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Um pedido de compra ao fornecedor — a entrada de estoque.
 *
 * O `statusId` é o que separa RASCUNHO de DOCUMENTO:
 *
 * - ABERTO (0): lista editável. Fornecedor, itens, quantidades e custos podem
 *   mudar à vontade, e o estoque não sabe que este pedido existe.
 * - FINALIZADO (1): as entradas já foram lançadas em `products` e em
 *   `stock_movements`. Não se edita mais — reabrir teria que estornar o
 *   estoque, e isso ainda não existe (é feito pelo banco, na mão).
 *
 * A lista de itens entra pelo CONSTRUTOR, e não por um addItem() que deixaria o
 * objeto meio-montado entre uma chamada e outra: o pedido é sempre trocado por
 * inteiro, que é exatamente o que a tela faz.
 *
 * E ela pode vir VAZIA, de propósito. Enquanto aberto, o pedido é rascunho —
 * escolher o fornecedor, salvar e montar a lista depois é um jeito legítimo de
 * trabalhar. "Precisa ter pelo menos um item" é regra do DOCUMENTO, não do
 * rascunho: quem a aplica é o `finalize()` do Repository, que é quem lança o
 * estoque e para quem um pedido vazio realmente não teria o que fazer.
 */
class Purchase
{
    public const ABERTO = 0;
    public const FINALIZADO = 1;

    /** @param PurchaseItem[] $items */
    public function __construct(
        // `?int` porque é o que o <select> do formulário manda quando ninguém
        // escolheu fornecedor nenhum. Recusar aqui dá uma mensagem de tela; um
        // `int` daria TypeError.
        public readonly ?int $supplierId,

        public readonly int $userId,
        public readonly array $items,
        public readonly int $statusId = self::ABERTO,

        // Nulos enquanto o pedido não foi gravado: quem os preenche é o banco
        // (id) e o Repository (purchasedAt, no INSERT).
        public readonly ?int $id = null,
        public readonly ?DateTimeImmutable $purchasedAt = null,
        public readonly ?string $notes = null,
    ) {
        if ($supplierId === null || $supplierId <= 0) {
            throw new InvalidArgumentException('O pedido de compra precisa de um fornecedor.');
        }

        foreach ($items as $item) {
            if (!$item instanceof PurchaseItem) {
                throw new InvalidArgumentException('Os itens do pedido precisam ser PurchaseItem.');
            }
        }

        if (!in_array($statusId, [self::ABERTO, self::FINALIZADO], true)) {
            throw new InvalidArgumentException('Status de pedido desconhecido: ' . $statusId . '.');
        }
    }

    public function isFinalizado(): bool
    {
        return $this->statusId === self::FINALIZADO;
    }

    /** A soma dos itens — a mesma conta que alimenta `purchases.total_value`. */
    public function totalValue(): string
    {
        $total = array_sum(array_map(
            static fn (PurchaseItem $item) => (float) $item->totalValue(),
            $this->items,
        ));

        return number_format($total, 2, '.', '');
    }

    public function formattedTotal(): string
    {
        return 'R$ ' . number_format((float) $this->totalValue(), 2, ',', '.');
    }

    /** "15/08/2026 14:30" — vazio enquanto o pedido não foi gravado. */
    public function formattedDate(): string
    {
        return $this->purchasedAt?->format('d/m/Y H:i') ?? '—';
    }

    public function statusLabel(): string
    {
        return $this->isFinalizado() ? 'Finalizado' : 'Em aberto';
    }
}
