<?php

declare(strict_types=1);

namespace User\Greengrocers\Repository;

use PDO;
use User\Greengrocers\Model\User;

class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            name: $row['name'],
            email: $row['email'],
            password: $row['password'],
            admin: in_array($row['admin'], [true, 1, '1', 't'], true),
        );
    }

    public function findById(int $id): ?User
    {
        $sql = 'SELECT id, name, email, password, admin'
             . ' FROM users'
             . ' WHERE id = :id';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * `password` chega já como HASH — quem gera é User::hashPassword(), não
     * o Repository. `admin` chega explícito de quem chama, igual o `active`
     * do ProductRepository: a política de "cadastro público nunca é admin"
     * é do Controller, não do Repository.
     *
     * @return int O id gerado.
     */
    public function create(
        string $name,
        string $email,
        string $password,
        bool $admin,
    ): int {
        $sql = 'INSERT INTO users (name, email, password, admin, created_at)'
             . ' VALUES (:name, :email, :password, :admin, :createdAt)';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'name'      => $name,
            'email'     => $email,
            'password'  => $password,
            'admin'     => $admin ? '1' : '0',
            'createdAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
