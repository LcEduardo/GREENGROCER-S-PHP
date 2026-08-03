<?php
// Carrinho é dado de LAYOUT (aparece em toda página, igual o Guard::isAdmin()
// aqui embaixo), não de um Controller específico — por isso é buscado direto
// aqui, e não passado no $data de cada render().
$carrinhoLogado = \User\Greengrocers\Auth\Guard::isLoggedIn();
$itensCarrinho = [];

if ($carrinhoLogado) {
    $cartRepository = new \User\Greengrocers\Repository\CartRepository(\User\Greengrocers\Database\Connection::get());
    $productRepository = new \User\Greengrocers\Repository\ProductRepository(\User\Greengrocers\Database\Connection::get());

    foreach ($cartRepository->findByUser((int) $_SESSION['user_id']) as $itemCarrinho) {
        $itensCarrinho[] = [
            'item'    => $itemCarrinho,
            'produto' => $productRepository->findById($itemCarrinho->productId),
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Greengrocers') ?></title>
    <style>
        .cart-toggle-input { display: none; }

        .cart-panel {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: 320px;
            max-width: 90vw;
            background: #fff;
            border-left: 1px solid #ccc;
            padding: 1rem;
            overflow-y: auto;
            transform: translateX(100%);
            transition: transform 0.2s ease;
            z-index: 2;
        }

        .cart-toggle-input:checked ~ .cart-panel {
            transform: translateX(0);
        }

        .cart-panel__overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1;
        }

        .cart-toggle-input:checked ~ .cart-panel__overlay {
            display: block;
        }

        .cart-panel__empty {
            text-align: center;
            margin-top: 3rem;
        }
    </style>
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
        <?php if ($carrinhoLogado): ?>
            <label for="cart-toggle">Carrinho (<?= count($itensCarrinho) ?>)</label>
        <?php else: ?>
            <a href="/login">Carrinho</a>
        <?php endif; ?>
    </nav>

    <?= $content ?>

    <input type="checkbox" id="cart-toggle" class="cart-toggle-input">

    <aside class="cart-panel">
        <label for="cart-toggle">Fechar ✕</label>
        <h2>Seu carrinho</h2>

        <?php if (empty($itensCarrinho)): ?>
            <div class="cart-panel__empty">
                <p>🧺</p>
                <p>Your basket is empty</p>
                <p>Add some fresh produce to get started.</p>
            </div>
        <?php else: ?>
            <ul>
                <?php foreach ($itensCarrinho as $linha): ?>
                    <li>
                        <?= htmlspecialchars($linha['produto']?->name ?? 'Produto removido') ?>
                        — <?= htmlspecialchars($linha['item']->quantity) ?>
                        <form action="/cart/decrease" method="post" style="display:inline">
                            <input type="hidden" name="productId" value="<?= $linha['item']->productId ?>">
                            <button type="submit">-</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </aside>

    <label for="cart-toggle" class="cart-panel__overlay"></label>
</body>
</html>
