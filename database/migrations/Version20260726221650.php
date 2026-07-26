<?php

declare(strict_types=1);

namespace User\Greengrocers\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Troca `users.type` (SMALLINT + CHECK 0/1) por `users.admin` (BOOLEAN).
 *
 * O CHECK já garantia só 0 ou 1, mas BOOLEAN deixa isso explícito no schema
 * e casa com o `bool $admin` do Model, sem precisar de CHECK nenhum.
 */
final class Version20260726221650 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Troca users.type (SMALLINT + CHECK) por users.admin (BOOLEAN).';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('users');

        // 0 = admin, 1 = cliente no schema antigo. DEFAULT false preserva o
        // comportamento anterior: por padrão o usuário nasce cliente.
        $table->dropColumn('type');
        $table->addColumn('admin', Types::BOOLEAN, ['default' => false]);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('users');

        $table->dropColumn('admin');
        $table->addColumn('type', Types::SMALLINT, [
            'columnDefinition' => 'SMALLINT NOT NULL DEFAULT 1 CHECK (type IN (0, 1))',
        ]);
    }
}
