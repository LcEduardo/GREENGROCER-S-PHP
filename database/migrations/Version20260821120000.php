<?php

declare(strict_types=1);

namespace User\Greengrocers\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renomeia `stock_movements.description` para `stock_movements.historical`.
 *
 * A coluna sempre guardou a frase pronta do lançamento ("Entrada de produto
 * pelo pedido 12"). `description` sugeria um texto livre, descritivo do
 * produto; o que ela guarda é o HISTÓRICO da movimentação — o registro de por
 * que aquela linha existe. A tela de movimentações mostra a coluna com esse
 * nome, e o cabeçalho dizendo uma coisa e o banco outra é dívida de leitura em
 * toda consulta futura.
 *
 * ⚠️ SQL CRU, e de propósito — esta é a única migration do projeto que não usa
 * o Schema Builder. O `Table::renameColumn()` do DBAL não altera a coluna no
 * lugar: no SQLite ele RECONSTRÓI a tabela (cria a nova, copia, dropa a velha)
 * a partir do que o DBAL consegue introspectar. E o que ele não introspecta
 * some no caminho — foi medido nesta tabela:
 *
 *   - os CHECK de `type` e de `reference_type`, escritos como `columnDefinition`
 *     na migration inicial, que são o que impede um quarto valor de entrar;
 *   - o `ON DELETE RESTRICT` da FK de `products`, que é o que impede a auditoria
 *     de estoque de evaporar junto com o produto.
 *
 * Sairia uma tabela com o nome certo e sem nenhuma das travas — em silêncio.
 * O `ALTER TABLE ... RENAME COLUMN` é in-place: renomeia e não encosta no
 * resto. Existe no SQLite desde a 3.25 e no Postgres desde a 9.2, que são os
 * dois bancos que este projeto roda.
 *
 * Nenhum dado é transformado na ida — só o nome muda —, então a volta é o mesmo
 * comando ao contrário. Ver `Docs/Code-Review/stock-movements.md` para o plano
 * de rollback completo (banco + código, na ordem).
 */
final class Version20260821120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomeia stock_movements.description para stock_movements.historical.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_movements RENAME COLUMN description TO historical');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_movements RENAME COLUMN historical TO description');
    }
}
