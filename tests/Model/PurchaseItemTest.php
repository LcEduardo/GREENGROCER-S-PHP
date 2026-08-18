<?php

declare(strict_types=1);

namespace User\Greengrocers\Tests\Model;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use User\Greengrocers\Model\PurchaseItem;

class PurchaseItemTest extends TestCase
{
    /**
     * Quantidade zero não é uma entrada de estoque — é uma linha que não faz
     * nada. O item recusa no construtor, e não em quem chama, para que não
     * exista item inválido em memória esperando alguém lembrar de conferir.
     */
    public function test_nao_aceita_quantidade_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PurchaseItem(productId: 1, quantity: '0');
    }

    /**
     * Quantidade negativa seria uma SAÍDA disfarçada de entrada: o finalize()
     * soma no estoque, então -5 tiraria produto do estoque por uma tela de
     * compra. Baixa de estoque tem outro caminho (venda, ajuste).
     */
    public function test_nao_aceita_quantidade_negativa(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PurchaseItem(productId: 1, quantity: '-5');
    }

    /** Texto que não é número vira 0.0 no cast — cai na mesma regra. */
    public function test_nao_aceita_quantidade_que_nao_e_numero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PurchaseItem(productId: 1, quantity: 'dez quilos');
    }

    /**
     * Custo zerado é caso de USO, não erro: é assim que o pedido de compra
     * vira reajuste de estoque, quando se está só acertando a contagem e não
     * houve dinheiro envolvido.
     */
    public function test_aceita_custo_zerado(): void
    {
        $item = new PurchaseItem(productId: 1, quantity: '3', costValue: '0');

        $this->assertSame(0.0, (float) $item->costValue);
        $this->assertSame(0.0, (float) $item->totalValue());
    }

    /** Sem informar custo nenhum dá no mesmo que informar zero. */
    public function test_custo_e_opcional(): void
    {
        $item = new PurchaseItem(productId: 1, quantity: '3');

        $this->assertSame(0.0, (float) $item->costValue);
    }

    public function test_nao_aceita_custo_negativo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PurchaseItem(productId: 1, quantity: '3', costValue: '-1');
    }

    public function test_total_do_item_e_quantidade_vezes_custo(): void
    {
        $item = new PurchaseItem(productId: 1, quantity: '2.5', costValue: '4.00');

        $this->assertSame(10.0, (float) $item->totalValue());
    }
}
