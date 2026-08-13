<?php
// Vitrine — layout importado do Claude Design (Greengrocer Shop.dc.html,
// view `isShopView`). O estilo mora em public/css/shop.css.
//
// O mock pinta a "faixa de estoque" (bolinha + texto) em três níveis; aqui a
// regra é a mesma, só que lida da coluna DECIMAL do banco em vez de um int.
// O corte de 3 é o do mock.
$limiteEstoqueBaixo = 3.0;
?>
<div class="gg-shop">

    <!-- ===== BANNER / TOLDO ===== -->
    <section class="gg-hero">
        <div class="gg-hero__awning"></div>
        <div class="gg-hero__scallop"></div>
        <div class="gg-hero__content">
            <div class="gg-hero__text">
                <p class="gg-hero__kicker">colhido esta manhã</p>
                <h1 class="gg-hero__title">Frutas e verduras frescas,<br>direto do caixote.</h1>
            </div>
            <div class="gg-hero__stats">
                <div class="gg-hero__stat">
                    <strong>100%</strong>
                    <span>cultivo local</span>
                </div>
                <div class="gg-hero__stat">
                    <strong>No mesmo dia</strong>
                    <span>entrega</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== FILTROS ===== -->
    <div class="gg-toolbar">
        <nav class="gg-chips" aria-label="Filtrar por categoria">
            <?php $sufixoBusca = $busca !== null ? '&q=' . urlencode($busca) : ''; ?>
            <a class="gg-chip" href="/<?= $busca !== null ? '?q=' . urlencode($busca) : '' ?>"<?= $categoriaSelecionada === null ? ' aria-current="page"' : '' ?>>Todos</a>
            <?php foreach ($categorias as $id => $nome): ?>
                <a class="gg-chip" href="/?category=<?= $id . $sufixoBusca ?>"<?= $categoriaSelecionada === $id ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($nome) ?></a>
            <?php endforeach; ?>
        </nav>
        <p class="gg-toolbar__count"><?= $countItems ?> <?= $countItems === 1 ? 'item' : 'itens' ?></p>
    </div>

    <!-- ===== GRADE ===== -->
    <?php if (empty($produtos)): ?>
        <div class="gg-empty">
            <p class="gg-empty__icon">🧺</p>
            <p class="gg-empty__title">Nenhum produto por aqui.</p>
            <p class="gg-empty__sub">Tente outra busca ou categoria.</p>
        </div>
    <?php else: ?>
        <div class="gg-grid">
            <?php foreach ($produtos as $produto): ?>
                <?php
                $estoque = (float) $produto->stockQuantity;
                $esgotado = $estoque <= 0;
                $poucoEstoque = !$esgotado && $estoque <= $limiteEstoqueBaixo;

                if ($esgotado) {
                    $classeEstoque = 'gg-card--out';
                    $rotuloEstoque = 'Esgotado';
                } elseif ($poucoEstoque) {
                    $classeEstoque = 'gg-card--low';
                    $rotuloEstoque = 'Restam apenas ' . $produto->formattedStock() . ' ' . $produto->unit;
                } else {
                    $classeEstoque = 'gg-card--ok';
                    $rotuloEstoque = $produto->formattedStock() . ' ' . $produto->unit . ' em estoque';
                }
                ?>
                <article class="gg-card gg-card--cat-<?= (int) $produto->categoryId ?> <?= $classeEstoque ?>">
                    <div class="gg-card__awning"></div>
                    <div class="gg-card__scallop"></div>

                    <div class="gg-card__bin">
                        <span class="gg-card__tag"><?= htmlspecialchars(mb_strtoupper($categorias[$produto->categoryId] ?? 'Sem categoria')) ?></span>
                        <div class="gg-card__recess">
                            <?php if ($produto->imageUrl() !== null): ?>
                                <img class="gg-card__photo" src="<?= htmlspecialchars($produto->imageUrl()) ?>" alt="<?= htmlspecialchars($produto->name) ?>" loading="lazy">
                            <?php else: ?>
                                <!-- Sem foto: o mock usa o estado vazio do <image-slot> (moldura
                                     tracejada + ícone). Aqui é decorativo — o nome do produto já
                                     está logo abaixo, então não vira conteúdo pro leitor de tela. -->
                                <div class="gg-card__photo gg-card__photo--empty" aria-hidden="true">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                        <path d="m21 15-5-5L5 21"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="gg-card__info">
                        <div class="gg-card__head">
                            <h2 class="gg-card__name"><?= htmlspecialchars($produto->name) ?></h2>
                            <span class="gg-card__price"><?= $produto->formattedPrice() ?></span>
                        </div>

                        <p class="gg-card__stock"><?= htmlspecialchars($rotuloEstoque) ?></p>

                        <div class="gg-card__action">
                            <?php if ($esgotado): ?>
                                <button type="button" class="gg-card__sold" disabled>Esgotado</button>
                            <?php else: ?>
                                <!-- cart.js intercepta o submit e responde por AJAX; sem JS o
                                     form navega normal e o servidor redireciona. Os campos
                                     ocultos são os mesmos de antes — não mexer nos names. -->
                                <form action="/cart/add" method="post">
                                    <input type="hidden" name="productId" value="<?= $produto->id ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="gg-card__add">Adicionar ao carrinho</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
