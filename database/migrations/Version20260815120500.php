<?php

declare(strict_types=1);

namespace User\Greengrocers\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adiciona `stock_movements.description`.
 *
 * O livro de movimentações já dizia QUANTO e DE ONDE veio (`reference_type` +
 * `reference_id`), mas quem abre a tabela para auditar precisava juntar as
 * peças de cabeça. A descrição guarda a frase pronta — "Entrada de produto pelo
 * pedido 12" — e é escrita no momento em que o pedido de compra é finalizado.
 *
 * Nullable de propósito: `sales` e ajuste manual ainda não passam por aqui, e
 * um NOT NULL exigiria inventar um DEFAULT que ficaria pendurado na coluna
 * servindo de descrição errada para todo INSERT que esquecesse o campo.
 */
final class Version20260815120500 extends AbstractMigration
{
    /** Para as linhas que nasceram antes da coluna existir — ver postUp(). */
    private const DESCRICAO_GENERICA = 'Movimentação registrada antes do controle de descrição';

    public function getDescription(): string
    {
        return 'Adiciona stock_movements.description e descreve as movimentações antigas.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('stock_movements');

        $table->addColumn('description', Types::STRING, ['length' => 255, 'notnull' => false]);
    }

    /**
     * As movimentações antigas não têm como recuperar a frase original, mas
     * ficar com a coluna vazia esconderia que elas são anteriores ao recurso.
     * A descrição genérica diz exatamente isso.
     *
     * Mesmo motivo do postUp() da migration do status: o SQL de addSql() rodaria
     * antes de a coluna existir.
     */
    public function postUp(Schema $schema): void
    {
        $this->connection->executeStatement(
            'UPDATE stock_movements SET description = :descricao WHERE description IS NULL',
            ['descricao' => self::DESCRICAO_GENERICA],
        );
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('stock_movements')->dropColumn('description');
    }
}
