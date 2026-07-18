<?php

declare(strict_types=1);

namespace User\Greengrocers\Database;

use Dotenv\Dotenv;
use InvalidArgumentException;
use PDO;

/**
 * Único ponto do projeto que sabe qual banco está rodando. Todo o resto recebe um
 * PDO pronto (ou, melhor ainda, um repositório) e não faz ideia do driver.
 */
final class Connection
{
    private static ?PDO $pdo = null;

    private const OPTIONS = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            $raiz = dirname(__DIR__, 2);

            // safeLoad não explode se o .env não existir (ex.: CI usando env vars reais)
            Dotenv::createImmutable($raiz)->safeLoad();

            self::$pdo = self::make(require $raiz . '/config/database.php');
        }

        return self::$pdo;
    }

    /**
     * Monta o PDO da conexão apontada por 'default'. Recebe a config por parâmetro
     * para que os testes possam montar uma conexão própria sem passar pelo .env.
     *
     * @param array<string, mixed> $config
     */
    public static function make(array $config): PDO
    {
        $nome = $config['default'];

        $conexao = $config['connections'][$nome] ?? throw new InvalidArgumentException(
            sprintf('A conexão "%s" não existe em config/database.php.', $nome)
        );

        return match ($conexao['driver']) {
            'sqlite' => self::sqlite($conexao),
            'pgsql'  => self::pgsql($conexao),
            default  => throw new InvalidArgumentException(
                sprintf('Driver "%s" não suportado.', $conexao['driver'])
            ),
        };
    }

    /** @param array<string, mixed> $conexao */
    private static function sqlite(array $conexao): PDO
    {
        $caminho = $conexao['database'];

        // :memory: não tem diretório; um arquivo pode apontar para pasta ainda inexistente
        if ($caminho !== ':memory:' && !is_dir(dirname($caminho))) {
            mkdir(dirname($caminho), recursive: true);
        }

        $pdo = new PDO('sqlite:' . $caminho, options: self::OPTIONS);

        // No SQLite as foreign keys vêm DESLIGADAS por padrão e o pragma vale só para
        // esta conexão — sem isso o banco aceita referências quebradas caladamente.
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    /** @param array<string, mixed> $conexao */
    private static function pgsql(array $conexao): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $conexao['host'],
            $conexao['port'],
            $conexao['database'],
        );

        $pdo = new PDO($dsn, $conexao['username'], $conexao['password'], self::OPTIONS + [
            // Prepared statements de verdade no Postgres, não simulados pelo PDO.
            // Fica só aqui: o driver do SQLite não aceita esse atributo.
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $pdo->exec(sprintf("SET NAMES '%s'", $conexao['charset']));

        return $pdo;
    }
}
