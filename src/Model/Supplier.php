<?php

declare(strict_types=1);

namespace User\Greengrocers\Model;

/**
 * Fornecedor de quem se compra o estoque.
 *
 * Ainda não há tela de cadastro: os fornecedores são criados direto no banco e
 * aqui só são LIDOS, para o <select> do pedido de compra. Por isso o Model é
 * puramente de leitura, sem regra de validação — quando existir o cadastro, é
 * aqui que as regras dele vão morar.
 */
class Supplier
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $cnpj = null,
        public readonly ?string $address = null,
        public readonly ?string $phone = null,
    ) {
    }

    /**
     * "12.345.678/9012-34". A coluna guarda só os 14 dígitos — a pontuação é
     * decisão de exibição, igual ao preço formatado do Product.
     */
    public function formattedCnpj(): ?string
    {
        if ($this->cnpj === null || strlen($this->cnpj) !== 14) {
            return $this->cnpj;
        }

        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $this->cnpj);
    }
}
