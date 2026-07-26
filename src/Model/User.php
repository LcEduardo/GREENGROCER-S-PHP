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
    ) {
    }