<?php

declare(strict_types=1);

namespace User\Greengrocers\Model;

use InvalidArgumentException;

/**
 * Uma linha do pedido de compra: qual produto, quanto entrou e a que custo.
 *
 * As regras moram no construtor, não em quem chama: se o objeto existe, ele já
 * é uma linha lançável. Assim nem o Controller nem o Repository precisam
 * lembrar de conferir quantidade antes de gravar.
 *
 * QUANTIDADE e DINHEIRO vêm como STRING, nunca float — a mesma decisão que a
 * migration documenta. É hortifruti: 0,5 kg é a venda mais comum, e float não
 * representa 0,10 exatamente.
 */
class PurchaseItem
{
    public function __construct(
        public readonly int $productId,
        public readonly string $quantity,

        // Custo OPCIONAL. Sem valor, o pedido vira reajuste de estoque: entra
        // mercadoria sem ter havido dinheiro no meio (acerto de contagem,
        // bonificação do fornecedor).
        public readonly string $costValue = '0',

        public readonly ?int $id = null,
    ) {
        // Zero não movimentaria nada; negativo seria uma SAÍDA disfarçada, já
        // que o finalize() SOMA no estoque. Baixa tem outro caminho.
        if ((float) $quantity <= 0) {
            throw new InvalidArgumentException('A quantidade do item precisa ser maior que zero.');
        }

        if ((float) $costValue < 0) {
            throw new InvalidArgumentException('O custo do item não pode ser negativo.');
        }
    }

    /**
     * O que esta linha custou: quantidade × custo unitário.
     *
     * Calculado, e não recebido pronto, para que a coluna `total_value` do
     * banco não possa divergir da conta que a tela mostra.
     */
    public function totalValue(): string
    {
        return number_format((float) $this->quantity * (float) $this->costValue, 2, '.', '');
    }

    /** "2,500" — a quantidade como o admin escreveu, com as 3 casas do schema. */
    public function formattedQuantity(): string
    {
        return number_format((float) $this->quantity, 3, ',', '.');
    }

    public function formattedCost(): string
    {
        return 'R$ ' . number_format((float) $this->costValue, 2, ',', '.');
    }

    public function formattedTotal(): string
    {
        return 'R$ ' . number_format((float) $this->totalValue(), 2, ',', '.');
    }
}
