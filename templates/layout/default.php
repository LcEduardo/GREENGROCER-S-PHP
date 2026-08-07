<?php
// Carrinho é dado de LAYOUT (aparece em toda página, igual o Guard::isAdmin()
// aqui embaixo), não de um Controller específico — por isso é buscado direto
// aqui, e não passado no $data de cada render().
$carrinhoLogado = \User\Greengrocers\Auth\Guard::isLoggedIn();
$itensCarrinho = [];
$qtdCarrinho = 0.0;
$totalCarrinhoFormatado = 'R$ 0,00';

if ($carrinhoLogado) {
    $cartRepository = new \User\Greengrocers\Repository\CartRepository(\User\Greengrocers\Database\Connection::get());
    $productRepository = new \User\Greengrocers\Repository\ProductRepository(\User\Greengrocers\Database\Connection::get());

    // resumo() junta carrinho + produtos e já soma o total — é o mesmo
    // método que CartController::cartData() chama pra responder o AJAX,
    // então a fórmula do total existe num lugar só.
    $resumo = $cartRepository->resumo((int) $_SESSION['user_id'], $productRepository);

    $itensCarrinho = $resumo['items'];
    $qtdCarrinho = $resumo['count'];
    $totalCarrinhoFormatado = 'R$ ' . number_format($resumo['total'], 2, ',', '.');
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Greengrocers') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="gg-header">
        <div class="gg-header__bar">
            <a href="/" class="gg-logo">
                <span class="gg-logo__mark"><span class="gg-logo__leaf"></span></span>
                <span class="gg-logo__word">greengrocers<span>.</span></span>
            </a>

            <form action="/" method="get" role="search" class="gg-search">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <?php if (!empty($categoriaSelecionada)): ?>
                    <input type="hidden" name="category" value="<?= (int) $categoriaSelecionada ?>">
                <?php endif; ?>
                <input type="search" name="q" value="<?= htmlspecialchars($busca ?? '') ?>" placeholder="Buscar produto...">
                <button type="submit">Buscar</button>
            </form>

            <nav class="gg-nav">
                <?php if (\User\Greengrocers\Auth\Guard::isAdmin()): ?>
                    <a href="/admin/products">Products</a>
                <?php endif; ?>
                <?php if (\User\Greengrocers\Auth\Guard::isLoggedIn()): ?>
                    <a href="/logout">Sair</a>
                <?php else: ?>
                    <a href="/login">Entrar</a>
                <?php endif; ?>
            </nav>

            <?php if ($carrinhoLogado): ?>
                <label for="cart-toggle" class="gg-cart-button">
                    <span class="gg-cart-icon">
                        <span class="gg-cart-icon__body"></span>
                        <span class="gg-cart-icon__handle"></span>
                        <span id="cart-count" class="gg-cart-badge"><?= $qtdCarrinho ?></span>
                    </span>
                    <span id="cart-total"><?= $totalCarrinhoFormatado ?></span>
                </label>
            <?php else: ?>
                <a href="/login" class="gg-cart-button">
                    <span class="gg-cart-icon">
                        <span class="gg-cart-icon__body"></span>
                        <span class="gg-cart-icon__handle"></span>
                    </span>
                    <span>Carrinho</span>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <?= $content ?>

    <input type="checkbox" id="cart-toggle" class="cart-toggle-input">

    <aside class="cart-panel">
        <label for="cart-toggle">Fechar ✕</label>
        <h2>Seu carrinho</h2>

        <!-- cart.js reescreve só o miolo abaixo (empty state ou <ul>) a cada
             add/decrease via fetch — por isso precisa de um id fixo pra achar
             onde entrar, sem depender da estrutura interna. -->
        <div id="cart-panel-body">
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
                            <form action="/cart/add" method="post" style="display:inline">
                                <input type="hidden" name="productId" value="<?= $linha['item']->productId ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit">+</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </aside>

    <label for="cart-toggle" class="cart-panel__overlay"></label>

    <script src="/js/cart.js" defer></script>
</body>
</html>
