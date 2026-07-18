<?php

declare(strict_types=1);

// Mapeia as variáveis do .env para a configuração das conexões.
// Sem segredos aqui — este arquivo VAI para o Git. Os valores reais moram no .env.
//
// Trocar de banco é trocar DB_DRIVER no .env: nenhum outro arquivo do projeto
// precisa saber qual banco está rodando.
return [
    'default' => $_ENV['DB_DRIVER'] ?? 'sqlite',

    'connections' => [
        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => $_ENV['DB_DATABASE'] ?? dirname(__DIR__) . '/database/greengrocers.sqlite',
        ],

        'pgsql' => [
            'driver'   => 'pgsql',
            'host'     => $_ENV['DB_HOST']     ?? '127.0.0.1',
            'port'     => (int) ($_ENV['DB_PORT'] ?? 5432),
            'database' => $_ENV['DB_NAME']     ?? 'greengrocers',
            'username' => $_ENV['DB_USER']     ?? 'postgres',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset'  => $_ENV['DB_CHARSET']  ?? 'utf8',
        ],
    ],
];
