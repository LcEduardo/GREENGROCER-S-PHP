<?php

declare(strict_types=1);

namespace User\Greengrocers\Model;

use DateTimeImmutable;

/**
 * Uma linha do livro de movimentações de estoque.
 *
 * É um objeto de LEITURA: nasce da consulta da tela de auditoria e ninguém o
 * grava de volta. Quem escreve em `stock_movements` hoje é o
 * `PurchaseRepository::finalize()`, ao lançar a entrada de um pedido de compra.
 *
 * Traz `productName` e `supplierName` junto, que não são colunas da tabela e
 * sim o resultado dos JOINs da consulta. Eles vêm de carona de propósito: a
 * tela mostra o nome, e sem isso cada linha da lista precisaria de uma ida ao
 * banco só para traduzir o id — o N+1 que o `findPage()` existe para evitar.
 *
 * `type` e `referenceType` andam juntos mas não são a mesma coisa:
 *
 * - `referenceType` é a ORIGEM (de onde a movimentação veio: compra, venda,
 *   ajuste manual). É o que a coluna "Tp. Movimento" da tela mostra.
 * - `type` é a DIREÇÃO (entrou ou saiu do estoque). É o que decide a classe
 *   `--in`/`--out` da linha na tabela.
 *
 * Compra entra, venda sai — mas quem garante essa correspondência é quem
 * ESCREVE a movimentação, não este objeto. Aqui os dois são lidos como vieram
 * do banco: a tela de auditoria mostra o que está gravado, e não uma versão
 * corrigida do que deveria estar.
 */
class StockMovement
{
    /** Direções — os valores do CHECK de `stock_movements.type`. */
    public const ENTRADA = 'in';
    public const SAIDA = 'out';
    public const AJUSTE = 'adjustment';

    /** Origens — os valores do CHECK de `stock_movements.reference_type`. */
    public const ORIGEM_COMPRA = 'purchase';
    public const ORIGEM_VENDA = 'sale';
    public const ORIGEM_AJUSTE = 'manual_adjustment';

    /**
     * O nome de cada origem em português, para a coluna "Tp. Movimento".
     *
     * Mora aqui, e não no template, pelo mesmo motivo do
     * `Product::formattedPrice()`: a mesma origem vai aparecer na lista, no
     * filtro e no que vier depois — com o texto solto no HTML, mudar "Compra"
     * amanhã é caçar arquivo.
     *
     * @var array<string, string>
     */
    private const ROTULOS = [
        self::ORIGEM_COMPRA => 'Compra',
        self::ORIGEM_VENDA  => 'Venda',
        self::ORIGEM_AJUSTE => 'Ajuste manual',
    ];

    public function __construct(
        public readonly int $id,
        public readonly int $productId,
        public readonly string $productName,
        public readonly string $type,

        // QUANTIDADE como STRING, não float — a mesma decisão que a migration
        // documenta e que o Product repete. É DECIMAL(10,3) no banco, e o
        // (float) só acontece na hora de formatar para a tela.
        public readonly string $quantity,

        public readonly string $referenceType,

        // Nulo no ajuste manual: não há pedido nem venda do outro lado.
        public readonly ?int $referenceId,

        public readonly DateTimeImmutable $movedAt,

        // Nullable porque a coluna é nullable: venda e ajuste manual ainda não
        // escrevem histórico nenhum.
        public readonly ?string $historical = null,

        // Só a movimentação de COMPRA tem fornecedor do outro lado. Nas demais
        // fica nulo e a coluna da tela sai em branco — ver findPage().
        public readonly ?string $supplierName = null,
    ) {
    }

    public function isEntrada(): bool
    {
        return $this->type === self::ENTRADA;
    }

    /** "21/08/2026 14:30" — o mesmo formato do Purchase::formattedDate(). */
    public function formattedDate(): string
    {
        return $this->movedAt->format('d/m/Y H:i');
    }

    /**
     * Quantidade pronta para a tela: "7,50".
     *
     * Duas casas é decisão de EXIBIÇÃO, igual ao Product::formattedStock(): a
     * coluna é DECIMAL(10,3) e continua guardando a grama.
     */
    public function formattedQuantity(): string
    {
        return number_format((float) $this->quantity, 2, ',', '.');
    }

    /**
     * A origem em português. Um valor que não conhecemos volta como veio, em
     * vez de virar "—": numa tela de auditoria, esconder o que está gravado é
     * pior do que mostrar um rótulo feio.
     */
    public function referenceLabel(): string
    {
        return self::ROTULOS[$this->referenceType] ?? $this->referenceType;
    }
}
