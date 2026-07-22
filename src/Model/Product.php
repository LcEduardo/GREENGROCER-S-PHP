<?php

declare(strict_types=1);

namespace User\Greengrocers\Model;

/**
 * Um produto como a vitrine precisa dele.
 *
 * Não tem `cost_price` de propósito: é o que se paga ao fornecedor. Se entrasse
 * aqui, chegaria na View e a margem de lucro sairia impressa no HTML da loja.
 *
 * `readonly` porque isto é leitura: o objeto nasce do banco já pronto e nada
 * depois deveria alterá-lo em memória sem passar por uma escrita de verdade.
 */
class Product
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $categoryId,

        // Unidade de medida: 'kg', 'un', 'mc' (maço)...
        public readonly string $unit,

        // DINHEIRO e QUANTIDADE vêm como STRING, não float — a mesma decisão que
        // a migration documenta. O PDO já os entrega assim (DECIMAL), e converter
        // para float aqui jogaria fora a precisão que o schema foi feito para
        // proteger. Quem formata para exibição é a View.
        public readonly string $salePrice,
        public readonly string $stockQuantity,

        public readonly ?string $image = null,
    ) {
    }

    /**
     * Preço pronto para a tela: "R$ 7,90".
     *
     * Mora aqui e não no template porque o mesmo produto vai aparecer na
     * vitrine, no carrinho e na tela do admin — com a formatação solta, o dia
     * que mudar são três arquivos.
     *
     * O cast para float acontece UMA vez, neste ponto. É o que permite os
     * templates continuarem sem declare(strict_types=1) sem espalhar (float).
     */
    public function formattedPrice(): string
    {
        return 'R$ ' . number_format((float) $this->salePrice, 2, ',', '.');
    }

    /**
     * Estoque pronto para a tela: "10,50".
     *
     * Sem a unidade junto — quem exibe decide se mostra "kg" numa coluna
     * separada ou colado no número.
     *
     * Duas casas aqui é decisão de EXIBIÇÃO. A coluna é DECIMAL(10,3) e
     * continua guardando a grama; isto só arredonda o que aparece.
     */
    public function formattedStock(): string
    {
        return number_format((float) $this->stockQuantity, 2, ',', '.');
    }
}
