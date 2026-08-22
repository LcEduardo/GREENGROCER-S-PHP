<div class="gg-page">

    <div class="gg-page__head">
        <div>
            <h1 class="gg-page__title"><?= htmlspecialchars($title) ?></h1>
            <p class="gg-page__sub">Todo o estoque, ativo e inativo. Só o que está ativo aparece na vitrine.</p>
        </div>
        <!-- Movimentações fica ao lado de "+ Novo produto" porque é sobre os
             MESMOS produtos, vistos pelo outro lado: aqui o saldo de agora, lá
             como ele chegou nesse número. Em ghost, e não em primary, para não
             disputar com a ação principal da tela. -->
        <div class="gg-page__actions">
            <a class="gg-btn gg-btn--ghost" href="/admin/stock-movements">Movimentações</a>
            <a class="gg-btn gg-btn--primary" href="/admin/products/create">+ Novo produto</a>
        </div>
    </div>

    <?php if (empty($produtos)): ?>
        <p class="gg-admin-empty">Nenhum produto cadastrado ainda.</p>
    <?php else: ?>
        <div class="gg-admin-list">
            <?php foreach ($produtos as $produto): ?>
                <div class="gg-admin-row gg-admin-row--cat-<?= (int) $produto->categoryId ?><?= $produto->active ? '' : ' gg-admin-row--inactive' ?>">
                    <span class="gg-admin-row__accent" aria-hidden="true"></span>

                    <span class="gg-admin-row__name"><?= htmlspecialchars($produto->name) ?></span>

                    <span class="gg-admin-row__pill"><?= htmlspecialchars($categorias[$produto->categoryId] ?? 'Sem categoria') ?></span>

                    <span class="gg-admin-row__field">
                        <span class="gg-admin-row__value gg-admin-row__value--price"><?= $produto->formattedPrice() ?></span>
                        <span class="gg-admin-row__unit">por <?= htmlspecialchars($produto->unit) ?></span>
                    </span>

                    <span class="gg-admin-row__field">
                        <span class="gg-admin-row__value"><?= $produto->formattedStock() ?></span>
                        <span class="gg-admin-row__unit"><?= htmlspecialchars($produto->unit) ?> em estoque</span>
                    </span>

                    <span class="gg-admin-row__status gg-admin-row__status--<?= $produto->active ? 'on' : 'off' ?>"><?= $produto->active ? 'Ativo' : 'Inativo' ?></span>

                    <a class="gg-btn gg-btn--ghost gg-btn--sm" href="/admin/products/edit?id=<?= $produto->id ?>">Editar</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
