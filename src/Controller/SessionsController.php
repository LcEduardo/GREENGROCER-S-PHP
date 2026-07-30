<?php

declare(strict_types=1);

namespace User\Greengrocers\Controller;

use User\Greengrocers\Repository\UserRepository;
use User\Greengrocers\View\View;

class SessionsController
{
    private View $view;

    public function __construct(private readonly UserRepository $users)
    {
        $this->view = new View();
    }

    public function create(): void
    {
        $this->view->render('Sessions/create', [
            'title' => 'Entrar',
        ]);
    }

    public function store(): void
    {
        $email = (string) filter_input(INPUT_POST, 'email');
        $password = (string) filter_input(INPUT_POST, 'password');

        $user = $this->users->findByEmail($email);

        if ($user === null || !$user->verifyPassword($password)) {
            $this->view->render('Sessions/create', [
                'title' => 'Entrar',
                'error' => 'E-mail ou senha inválidos.',
            ]);
            return;
        }

        // Regenera o id da sessão no login: evita fixation (o id de antes do
        // login não pode continuar valendo depois que o usuário é conhecido).
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user->id;
        $_SESSION['admin'] = $user->admin;

        header('Location: ' . ($user->admin ? '/admin/products' : '/'));
    }

    public function destroy(): void
    {
        $_SESSION = [];
        session_destroy();

        header('Location: /');
    }
}
