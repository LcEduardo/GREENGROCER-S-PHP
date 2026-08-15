<?php

declare(strict_types=1);

namespace User\Greengrocers\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adiciona `purchases.status_id`: 0 = não finalizado, 1 = finalizado.
 *
 * O status é o que separa RASCUNHO de DOCUMENTO. Enquanto 0, o pedido é só uma
 * lista editável e o estoque não sabe que ele existe; ao virar 1, as entradas
 * são lançadas em `products` e em `stock_movements`. Sem essa coluna não havia
 * como abrir um pedido, guardar e voltar depois — salvar já era mexer no
 * estoque.
 *
 * SMALLINT com CHECK, no mesmo espírito do antigo `users.type`: o conjunto de
 * valores é fechado e um terceiro número entrando por engano deixaria pedidos
 * num estado que nenhum código sabe ler.
 */
final class Version20260815120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adiciona purchases.status_id (0 = não finalizado, 1 = finalizado).';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('purchases');

        // DEFAULT 0 vale para o pedido NOVO, que nasce rascunho. As linhas que
        // já existiam recebem 1 no postUp() — ver o porquê lá embaixo.
        $table->addColumn('status_id', Types::SMALLINT, [
            'columnDefinition' => 'SMALLINT NOT NULL DEFAULT 0 CHECK (status_id IN (0, 1))',
        ]);
    }

    /**
     * Compra lançada ANTES desta coluna existir já baixou estoque no momento em
     * que foi criada — era o único comportamento que havia. Deixá-las com o
     * DEFAULT 0 diria que estão abertas, e o admin poderia "finalizar" de novo,
     * somando a mesma entrada duas vezes no estoque.
     *
     * Roda aqui, e não com addSql() no up(), porque o Doctrine executa o SQL
     * avulso ANTES do diff de schema: no up() a coluna ainda não existiria.
     */
    public function postUp(Schema $schema): void
    {
        $this->connection->executeStatement('UPDATE purchases SET status_id = 1');
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('purchases')->dropColumn('status_id');
    }
}
