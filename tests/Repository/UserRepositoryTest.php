<?php

declare(strict_types=1);

namespace User\Greengrocers\Tests\Repository;

use User\Greengrocers\Model\User;
use User\Greengrocers\Repository\UserRepository;
use User\Greengrocers\Tests\Support\DatabaseTestCase;

class UserRepositoryTest extends DatabaseTestCase
{
    public function test_create_insere_o_usuario_e_devolve_o_id_gerado(): void
    {
        $repository = new UserRepository($this->pdo);

        $id = $repository->create(
            name: 'Ana',
            email: 'ana@example.com',
            password: User::hashPassword('senha123'),
            admin: false,
        );

        $usuario = $repository->findById($id);

        $this->assertNotNull($usuario);
        $this->assertSame('Ana', $usuario->name);
        $this->assertSame('ana@example.com', $usuario->email);
        $this->assertFalse($usuario->admin);
    }

    /**
     * O hash é o que vai pro banco, nunca a senha em texto puro — se um dia
     * create() passar a gravar o valor cru, é este teste que denuncia.
     */
    public function test_create_guarda_o_hash_e_nao_a_senha_em_texto_puro(): void
    {
        $repository = new UserRepository($this->pdo);

        $id = $repository->create(
            name: 'Ana',
            email: 'ana@example.com',
            password: User::hashPassword('senha123'),
            admin: false,
        );

        $usuario = $repository->findById($id);

        $this->assertNotSame('senha123', $usuario->password);
        $this->assertTrue($usuario->verifyPassword('senha123'));
    }

    /**
     * A tela pública de cadastro nunca vai passar admin: true — isso fica pra
     * criação manual, por ora. Mas o Repository é a camada de dado, não de
     * política: continua aceitando os dois valores, igual o `active` do
     * ProductRepository.
     */
    public function test_create_permite_cadastrar_admin(): void
    {
        $repository = new UserRepository($this->pdo);

        $id = $repository->create(
            name: 'Admin',
            email: 'admin@example.com',
            password: User::hashPassword('senha123'),
            admin: true,
        );

        $usuario = $repository->findById($id);

        $this->assertTrue($usuario->admin);
    }

    public function test_findById_retorna_null_quando_o_id_nao_existe(): void
    {
        $repository = new UserRepository($this->pdo);

        $this->assertNull($repository->findById(999));
    }

    public function test_findByEmail_encontra_usuario_pelo_email(): void
    {
        $repository = new UserRepository($this->pdo);

        $repository->create(
            name: 'Ana',
            email: 'ana@example.com',
            password: User::hashPassword('senha123'),
            admin: false,
        );

        $usuario = $repository->findByEmail('ana@example.com');

        $this->assertNotNull($usuario);
        $this->assertSame('Ana', $usuario->name);
    }

    public function test_findByEmail_retorna_null_quando_email_nao_existe(): void
    {
        $repository = new UserRepository($this->pdo);

        $this->assertNull($repository->findByEmail('ninguem@example.com'));
    }
}
