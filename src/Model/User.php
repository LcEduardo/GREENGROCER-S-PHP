<?php

declare(strict_types=1);

namespace User\Greengrocers\Model;

class User
{
    public const MAX_NAME_LENGTH = 50;

    private string $passwordHash;

    public function __construct(
        public string $name,
        string $password,
        public string $email = '',
    ) {
        // O nome não pode passar do limite de caracteres
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('O nome não pode ter mais de %d caracteres.', self::MAX_NAME_LENGTH)
            );
        }

        // Guarda apenas o HASH da senha — nunca o texto puro
        $this->passwordHash = password_hash($password, PASSWORD_ARGON2ID);
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function verifyPassword(string $password): bool
    {
        // Verifica se a senha digitada confere com o hash armazenado
        return password_verify($password, $this->passwordHash);
    }
}
