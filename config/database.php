<?php

declare(strict_types=1);

// Mapeia as variáveis do .env para a configuração da conexão.
// Sem segredos aqui — este arquivo VAI para o Git. Os valores reais moram no .env.
return [
    'host'     => $_ENV['DB_HOST']    ?? '127.0.0.1',
    'port'     => (int) ($_ENV['DB_PORT'] ?? 5432),
    'database' => $_ENV['DB_NAME']    ?? 'greengrocers',
    'username' => $_ENV['DB_USER']    ?? 'postgres',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset'  => $_ENV['DB_CHARSET'] ?? 'utf8',
];
