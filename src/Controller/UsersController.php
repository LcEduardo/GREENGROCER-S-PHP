<?php

declare(strict_types=1);

namespace User\Greengrocers\Controller;

use User\Greengrocers\Model\User;
use User\Greengrocers\View\View;

class UsersController
{
    private View $view;

    public function __construct()
    {
        $this->view = new View();
    }

    /**
     * GET /users — lista os usuários.
     * (Sem banco ainda, então a lista vem vazia por enquanto.)
     */
    public function index(): void
    {
        $this->view->render('Users/index', [
            'title' => 'Usuários',
            'users' => [],
        ]);
    }

    /**
     * GET  /users/add — mostra o formulário de cadastro.
     * POST /users/add — cria o usuário com os dados enviados.
     */
    public function add(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // O User já faz o hash da senha e valida o nome
                $user = new User(
                    name: $_POST['name'] ?? '',
                    password: $_POST['password'] ?? '',
                );

                // Sucesso: mostra os dados do usuário recém-criado
                $this->view->render('Users/view', [
                    'title' => 'Usuário Cadastrado',
                    'user'  => $user,
                ]);
                return;
            } catch (\InvalidArgumentException $e) {
                // Entrada inválida: cai para o form de novo, com a mensagem
                $error = $e->getMessage();
            }
        }

        $this->view->render('Users/add', [
            'title' => 'Cadastrar Usuário',
            'error' => $error ?? null,
        ]);
    }
}
