<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Greengrocers') ?></title>
</head>
<body>
    <nav>
        <a href="/"><h2>Greengrocers.</h2></a>
        <a href="/admin/products">Products</a>
        <?php if (\User\Greengrocers\Auth\Guard::isLoggedIn()): ?>
            <a href="/logout">Sair</a>
        <?php else: ?>
            <a href="/login">Entrar</a>
        <?php endif; ?>
    </nav>
    <?= $content ?>
</body>
</html>
