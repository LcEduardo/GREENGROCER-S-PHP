<?php

declare(strict_types=1);

namespace User\Greengrocers\Tests\Model;

use PHPUnit\Framework\TestCase;
use User\Greengrocers\Model\Product;

class ProductTest extends TestCase
{
    public function test_imageUrl_monta_o_caminho_publico_a_partir_do_nome_do_arquivo(): void
    {
        $produto = $this->produto(imagem: '9f2c1a4b8e7d6c5b4a3f2e1d0c9b8a77.webp');

        $this->assertSame(
            '/images/products/9f2c1a4b8e7d6c5b4a3f2e1d0c9b8a77.webp',
            $produto->imageUrl(),
        );
    }

    public function test_imageUrl_e_null_quando_o_produto_nao_tem_foto(): void
    {
        $this->assertNull($this->produto(imagem: null)->imageUrl());

        // String vazia chega de cadastro antigo, feito quando o campo era texto
        // livre. Vale o mesmo que sem foto — senão a vitrine renderiza <img src="">.
        $this->assertNull($this->produto(imagem: '')->imageUrl());
    }

    /**
     * Enquanto a imagem era um <input type="text">, dava pra colar a URL de um
     * site qualquer, e há linhas assim no banco. Elas continuam abrindo: prefixar
     * /images/products/ numa URL inteira quebraria a foto desses produtos.
     */
    public function test_imageUrl_deixa_passar_a_url_inteira_dos_cadastros_antigos(): void
    {
        $produto = $this->produto(imagem: 'https://exemplo.com/fotos/tomate.jpg');

        $this->assertSame('https://exemplo.com/fotos/tomate.jpg', $produto->imageUrl());
    }

    private function produto(?string $imagem): Product
    {
        return new Product(
            id: 1,
            name: 'Tomate',
            categoryId: 1,
            unit: 'kg',
            salePrice: '7.90',
            stockQuantity: '10.000',
            active: true,
            image: $imagem,
        );
    }
}
