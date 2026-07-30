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
        <form action="/" method="get" role="search">
            <?php if (!empty($categoriaSelecionada)): ?>
                <input type="hidden" name="category" value="<?= (int) $categoriaSelecionada ?>">
            <?php endif; ?>
            <input type="search" name="q" value="<?= htmlspecialchars($busca ?? '') ?>" placeholder="Buscar produto...">
            <button type="submit">Buscar</button>
        </form>
        <?php if (\User\Greengrocers\Auth\Guard::isAdmin()): ?>
            <a href="/admin/products">Products</a>
        <?php endif; ?>
        <?php if (\User\Greengrocers\Auth\Guard::isLoggedIn()): ?>
            <a href="/logout">Sair</a>
        <?php else: ?>
            <a href="/login">Entrar</a>
        <?php endif; ?>
    </nav>
    <?= $content ?>
</body>
</html>
