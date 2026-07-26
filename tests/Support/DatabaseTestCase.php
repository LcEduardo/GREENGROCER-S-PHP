<?php

declare(strict_types=1);

namespace User\Greengrocers\Tests\Support;

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base para os testes que precisam de banco.
 *
 * Cada teste recebe um SQLite :memory: recém-nascido, com o schema criado pela
 * MESMA migration que roda em produção. Escrever os CREATE TABLE à mão aqui
 * seria mais curto, mas criaria uma segunda fonte de verdade do schema: no dia
 * em que uma migration mudasse uma coluna, o teste continuaria verde contra a
 * tabela velha.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        // O :memory: vive DENTRO desta conexão. Um segundo PDO apontando para
        // ':memory:' abriria outro banco, vazio — e o teste falharia com zero
        // linhas em vez de um erro. Por isso o PDO sai daqui, e não da Connection.
        $dbal = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $this->migrate($dbal);

        $pdo = $dbal->getNativeConnection();
        assert($pdo instanceof PDO);

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // O pragma vale por CONEXÃO, e esta não passou pela Connection::sqlite().
        // Sem repetir aqui, o teste aceitaria category_id apontando para o nada.
        $pdo->exec('PRAGMA foreign_keys = ON');

        $this->pdo = $pdo;
    }

    private function migrate(DbalConnection $dbal): void
    {
        $factory = DependencyFactory::fromConnection(
            new ConfigurationArray(require dirname(__DIR__, 2) . '/migrations.php'),
            new ExistingConnection($dbal),
        );

        // O Doctrine grava o histórico em doctrine_migration_versions, que não
        // existe num banco recém-criado. A CLI cria essa tabela sozinha antes de
        // migrar; pela API programática, é a gente que pede.
        $factory->getMetadataStorage()->ensureInitialized();

        $plan = $factory->getMigrationPlanCalculator()->getPlanUntilVersion(
            $factory->getVersionAliasResolver()->resolveVersionAlias('latest'),
        );

        $factory->getMigrator()->migrate($plan, new MigratorConfiguration());
    }
}
