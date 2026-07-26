<?php

declare(strict_types=1);

namespace User\Greengrocers\Model;

/**
 * `readonly` porque isto é leitura: o objeto nasce do banco já pronto e nada
 * depois deveria alterá-lo em memória sem passar por uma escrita de verdade.
 */
class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly bool $admin,
        // public readonly string $address,
        // public readonly string $phone,
    ) {
    }

    /**
     * Estático porque não há objeto ainda: isto roda ANTES do INSERT, pra gerar
     * o valor que vai no construtor. O Model é `readonly` e nasce hidratado do
     * banco, então o hash não pode ser efeito colateral do construtor.
     */
    public static function hashPassword(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_ARGON2ID);
    }

    /** Confere a senha digitada no login contra o hash já carregado do banco. */
    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->password);
    }
}