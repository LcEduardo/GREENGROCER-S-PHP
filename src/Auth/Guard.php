<?php

declare(strict_types=1);

namespace User\Greengrocers\Auth;

/**
 * Única fonte de verdade sobre "está logado" / "é admin". Fica fora dos
 * Controllers pra ser chamada pelo roteador antes de qualquer rota /admin/*,
 * num lugar só, em vez de repetir o `isset($_SESSION[...])` em cada action.
 */
class Guard
{
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function isAdmin(): bool
    {
        return self::isLoggedIn() && ($_SESSION['admin'] ?? false) === true;
    }
}
