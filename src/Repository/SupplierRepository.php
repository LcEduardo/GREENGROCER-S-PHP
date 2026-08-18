<?php

declare(strict_types=1);

namespace User\Greengrocers\Repository;

use PDO;
use User\Greengrocers\Model\Supplier;

class SupplierRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Supplier
    {
        return new Supplier(
            id:      (int) $row['id'],
            name:    $row['name'],
            cnpj:    $row['cnpj'],
            address: $row['address'],
            phone:   $row['phone'],
        );
    }

    /**
     * Todos os fornecedores, para o <select> do pedido de compra.
     *
     * Sem recorte por "ativo" porque a tabela não tem essa coluna — quando
     * existir tela de cadastro, é aqui que o filtro entra.
     *
     * @return Supplier[]
     */
    public function findAll(): array
    {
        $statement = $this->pdo->query('SELECT id, name, cnpj, address, phone FROM suppliers ORDER BY name');

        $fornecedores = [];

        foreach ($statement as $row) {
            $fornecedores[] = $this->hydrate($row);
        }

        return $fornecedores;
    }

    /**
     * Os mesmos fornecedores, indexados pelo id.
     *
     * A tela inicial de pedidos precisa trocar `supplier_id` por nome em cada
     * linha da lista; com o array indexado isso é uma busca em memória em vez
     * de uma consulta por pedido.
     *
     * @return array<int, Supplier>
     */
    public function findAllKeyedById(): array
    {
        $porId = [];

        foreach ($this->findAll() as $fornecedor) {
            $porId[$fornecedor->id] = $fornecedor;
        }

        return $porId;
    }
}
