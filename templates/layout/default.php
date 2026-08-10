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
    <link rel="stylesheet" href="/css/fonts.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/footer.css">
    <link rel="stylesheet" href="/css/cart-panel.css">
    <link rel="stylesheet" href="/css/shop.css">
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

    <main class="gg-main">
        <?= $content ?>
    </main>

    <footer class="gg-footer">
        <div class="gg-footer__bar">
            <div class="gg-footer__logo">greengrocers<span>.</span></div>
            <div class="gg-footer__hours">Todos os dias · 7h – 19h · Rua do Mercado, 24</div>
            <div class="gg-footer__tagline">Produtos frescos, preço justo.</div>
        </div>
    </footer>

    <input type="checkbox" id="cart-toggle" class="cart-toggle-input">

    <aside class="cart-panel">
        <div class="cart-panel__header">
            <span class="cart-panel__title">Seu carrinho</span>
            <label for="cart-toggle" class="cart-panel__close" aria-label="Fechar">✕</label>
        </div>

        <!-- cart.js reescreve só o miolo abaixo (empty state ou lista) a cada
             add/decrease via fetch — por isso precisa de um id fixo pra achar
             onde entrar, sem depender da estrutura interna. -->
        <div id="cart-panel-body" class="cart-panel__body">
            <?php if (empty($itensCarrinho)): ?>
                <div class="cart-panel__empty">
                    <p class="cart-panel__empty-icon">🧺</p>
                    <p class="cart-panel__empty-title">Your basket is empty</p>
                    <p class="cart-panel__empty-sub">Add some fresh produce to get started.</p>
                </div>
            <?php else: ?>
                <ul class="cart-panel__list">
                    <?php foreach ($itensCarrinho as $linha): ?>
                        <li class="cart-panel__item">
                            <div class="cart-panel__item-info">
                                <span class="cart-panel__item-name"><?= htmlspecialchars($linha['produto']?->name ?? 'Produto removido') ?></span>
                                <?php if ($linha['produto'] !== null): ?>
                                    <span class="cart-panel__item-price"><?= $linha['produto']->formattedPrice() ?> cada</span>
                                <?php endif; ?>
                            </div>
                            <div class="cart-panel__item-stepper">
                                <form action="/cart/decrease" method="post">
                                    <input type="hidden" name="productId" value="<?= $linha['item']->productId ?>">
                                    <button type="submit" aria-label="Diminuir">–</button>
                                </form>
                                <span class="cart-panel__item-qty"><?= htmlspecialchars($linha['item']->quantity) ?></span>
                                <form action="/cart/add" method="post">
                                    <input type="hidden" name="productId" value="<?= $linha['item']->productId ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" aria-label="Aumentar">+</button>
                                </form>
                            </div>
                            <?php if ($linha['produto'] !== null): ?>
                                <span class="cart-panel__item-total">R$ <?= number_format((float) $linha['produto']->salePrice * (float) $linha['item']->quantity, 2, ',', '.') ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Ainda não existe fluxo de checkout (sem rota, sem modelo de
             pedido) — por ora "Finalizar compra" só fecha o carrinho, igual
             o mock original faz enquanto não existe checkout de verdade pra
             ir. Some junto com o resto do rodapé quando o carrinho é vazio. -->
        <div id="cart-panel-footer" class="cart-panel__footer" style="<?= empty($itensCarrinho) ? 'display:none' : '' ?>">
            <div class="cart-panel__footer-total-row">
                <span class="cart-panel__footer-label">Total (<span id="cart-panel-footer-count"><?= $qtdCarrinho ?></span> itens)</span>
                <span id="cart-panel-footer-total" class="cart-panel__footer-total"><?= $totalCarrinhoFormatado ?></span>
            </div>
            <label for="cart-toggle" class="cart-panel__checkout">Finalizar compra →</label>
        </div>
    </aside>

    <label for="cart-toggle" class="cart-panel__overlay"></label>

    <script src="/js/cart.js" defer></script>
</body>
</html>
