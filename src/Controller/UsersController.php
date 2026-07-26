<?php

declare(strict_types=1);

namespace User\Greengrocers\Controller;

use User\Greengrocers\Model\User;
use User\Greengrocers\Repository\UserRepository;
use User\Greengrocers\View\View;

class UsersController
{
    private View $view;

    // O repositório chega pronto de fora, igual o ProductsController: este
    // controller não sabe que existe um banco.
    public function __construct(private readonly UserRepository $users)
    {
        $this->view = new View();
    }

    public function create(): void
    {
        $this->view->render('Users/create', [
            'title' => 'Criar conta',
        ]);
    }

    public function store(): void
    {
        $this->users->create(
            name: (string) filter_input(INPUT_POST, 'name'),
            email: (string) filter_input(INPUT_POST, 'email'),
            password: User::hashPassword((string) filter_input(INPUT_POST, 'password')),
            // Cadastro público nunca cria admin — por ora isso é feito na mão.
            admin: false,
        );

        header('Location: /');
    }
}
