<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require_once __DIR__ . '/vendor/autoload.php';

// Conexão usada pelo doctrine/migrations. Traduz config/database.php para o
// formato do DBAL — assim as migrations rodam SEMPRE no mesmo banco que a
// aplicação, sem uma segunda fonte de verdade para host/senha/caminho.
//
// safeLoad não explode se o .env não existir (ex.: CI usando env vars reais).
Dotenv::createImmutable(__DIR__)->safeLoad();

$config  = require __DIR__ . '/config/database.php';
$nome    = $config['default'];
$conexao = $config['connections'][$nome] ?? throw new InvalidArgumentException(
    sprintf('A conexão "%s" não existe em config/database.php.', $nome)
);

return match ($conexao['driver']) {
    // O DBAL distingue banco em arquivo (path) de banco em memória (memory).
    'sqlite' => $conexao['database'] === ':memory:'
        ? ['driver' => 'pdo_sqlite', 'memory' => true]
        : ['driver' => 'pdo_sqlite', 'path' => $conexao['database']],

    'pgsql' => [
        'driver'   => 'pdo_pgsql',
        'host'     => $conexao['host'],
        'port'     => $conexao['port'],
        'dbname'   => $conexao['database'],
        'user'     => $conexao['username'],
        'password' => $conexao['password'],
        'charset'  => $conexao['charset'],
    ],

    default => throw new InvalidArgumentException(
        sprintf('Driver "%s" não suportado.', $conexao['driver'])
    ),
};
