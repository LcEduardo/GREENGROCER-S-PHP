<?php

declare(strict_types=1);

namespace User\Greengrocers\Database;

use Dotenv\Dotenv;
use PDO;

final class Connection
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            $raiz = dirname(__DIR__, 2);

            // safeLoad não explode se o .env não existir (ex.: CI usando env vars reais)
            Dotenv::createImmutable($raiz)->safeLoad();

            $config = require $raiz . '/config/database.php';

            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $config['host'],
                $config['port'],
                $config['database'],
            );

            self::$pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Prepared statements de verdade no Postgres, não simulados pelo PDO
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            self::$pdo->exec(sprintf("SET NAMES '%s'", $config['charset']));
        }

        return self::$pdo;
    }
}
