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
     * Pedido sem item não é rascunho, é documento vazio: não teria o que
     * lançar no estoque ao finalizar.
     */
    public function test_nao_aceita_pedido_sem_item(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Purchase(supplierId: 1, userId: 1, items: []);
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
