<?php

declare(strict_types=1);

namespace User\Greengrocers\Tests\Model;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use User\Greengrocers\Model\Purchase;
use User\Greengrocers\Model\PurchaseItem;

class PurchaseTest extends TestCase
{
    /**
     * O fornecedor é obrigatório. Chega como `?int` porque é isso que o
     * formulário manda quando ninguém escolheu nada no <select> — e é
     * justamente esse caso que precisa ser recusado, não um TypeError.
     */
    public function test_nao_aceita_fornecedor_null(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Purchase(supplierId: null, userId: 1, items: [$this->item()]);
    }

    public function test_nao_aceita_fornecedor_zerado(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Purchase(supplierId: 0, userId: 1, items: [$this->item()]);
    }

    /**
     * Rascunho vazio é estado LEGÍTIMO: o admin escolhe o fornecedor, salva e
     * volta depois para montar a lista.
     *
     * Quem exige item é o finalize(), porque a regra é do documento e não do
     * rascunho — é lá que o estoque é lançado, e é lá que o teste mora
     * (PurchaseRepositoryTest::test_finalize_recusa_pedido_sem_item).
     */
    public function test_aceita_pedido_sem_item_enquanto_rascunho(): void
    {
        $pedido = new Purchase(supplierId: 1, userId: 1, items: []);

        $this->assertSame([], $pedido->items);
        $this->assertFalse($pedido->isFinalizado());
    }

    public function test_total_de_pedido_sem_item_e_zero(): void
    {
        $this->assertSame(
            0.0,
            (float) new Purchase(supplierId: 1, userId: 1, items: [])->totalValue(),
        );
    }

    public function test_nasce_aberto(): void
    {
        $pedido = new Purchase(supplierId: 1, userId: 1, items: [$this->item()]);

        $this->assertSame(Purchase::ABERTO, $pedido->statusId);
        $this->assertFalse($pedido->isFinalizado());
    }

    public function test_total_do_pedido_soma_o_total_dos_itens(): void
    {
        $pedido = new Purchase(
            supplierId: 1,
            userId: 1,
            items: [
                new PurchaseItem(productId: 1, quantity: '2', costValue: '3.00'), // 6,00
                new PurchaseItem(productId: 2, quantity: '1.5', costValue: '4.00'), // 6,00
            ],
        );

        $this->assertSame(12.0, (float) $pedido->totalValue());
    }

    public function test_nao_aceita_status_fora_de_aberto_ou_finalizado(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Purchase(supplierId: 1, userId: 1, items: [$this->item()], statusId: 7);
    }

    private function item(): PurchaseItem
    {
        return new PurchaseItem(productId: 1, quantity: '1');
    }
}
