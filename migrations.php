<?php

declare(strict_types=1);

// Configuração do doctrine/migrations (standalone, sem ORM).
// Só diz ONDE ficam as migrations e ONDE o histórico é gravado — a conexão
// em si mora em migrations-db.php.
return [
    'table_storage' => [
        'table_name' => 'doctrine_migration_versions',
    ],

    'migrations_paths' => [
        'User\Greengrocers\Migrations' => __DIR__ . '/database/migrations',
    ],

    // Uma migration que falha no meio deixaria o banco pela metade. SQLite e
    // Postgres aceitam DDL dentro de transação, então dá para exigir tudo-ou-nada.
    'all_or_nothing' => true,
    'transactional'  => true,
];
