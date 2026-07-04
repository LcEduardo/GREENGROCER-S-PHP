<?php

declare(strict_types=1);

namespace User\Greengrocers\Tests\Model;

use PHPUnit\Framework\TestCase;
use User\Greengrocers\Model\User;

class UserTest extends TestCase
{
    public function test_a_senha_e_guardada_como_hash_e_nao_em_texto_puro(): void
    {
        $senhaPura = 'minhaSenhaSecreta123';

        $user = new User(name: 'Lucas Eduardo', email: 'lucas@example.com', password: $senhaPura);

        // O que fica guardado no objeto NÃO pode ser a senha digitada
        $this->assertNotSame($senhaPura, $user->getPasswordHash());
    }

    public function test_a_senha_original_confere_contra_o_hash(): void
    {
        $senhaPura = 'minhaSenhaSecreta123';

        $user = new User(name: 'Lucas Eduardo', email: 'lucas@example.com', password: $senhaPura);

        // A senha certa valida; uma errada não
        $this->assertTrue($user->verifyPassword($senhaPura));
        $this->assertFalse($user->verifyPassword('senhaErrada'));
    }

    public function test_nome_com_mais_de_50_caracteres_e_rejeitado(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $nomeLongo = str_repeat('a', 51); // 51 caracteres, passa do limite

        new User(name: $nomeLongo, email: 'lucas@example.com', password: 'senha123');
    }
}
