<div class="gg-page">

    <div class="gg-page__head">
        <div>
            <h1 class="gg-page__title"><?= htmlspecialchars($title) ?></h1>
            <p class="gg-page__sub">Entradas de estoque. Em aberto ainda dá para mexer; finalizado já entrou no estoque.</p>
        </div>
        <a class="gg-btn gg-btn--primary" href="/admin/purchases/create">+ Novo pedido</a>
    </div>

    <?php if (empty($pedidos)): ?>
        <p class="gg-admin-empty">Nenhum pedido de compra lançado ainda.</p>
    <?php else: ?>
        <div class="gg-admin-list">
            <?php foreach ($pedidos as $pedido): ?>
                <div class="gg-admin-row gg-purchase-row<?= $pedido->isFinalizado() ? ' gg-purchase-row--done' : '' ?>">
                    <span class="gg-purchase-row__id">#<?= $pedido->id ?></span>

                    <span class="gg-admin-row__name">
                        <?= htmlspecialchars($fornecedores[$pedido->supplierId]->name ?? 'Fornecedor removido') ?>
                    </span>

                    <span class="gg-admin-row__pill"><?= count($pedido->items) ?> <?= count($pedido->items) === 1 ? 'item' : 'itens' ?></span>

                    <span class="gg-admin-row__field">
                        <span class="gg-admin-row__value"><?= htmlspecialchars($pedido->formattedDate()) ?></span>
                    </span>

                    <span class="gg-admin-row__field">
                        <span class="gg-admin-row__value gg-admin-row__value--price"><?= $pedido->formattedTotal() ?></span>
                    </span>

                    <span class="gg-admin-row__status gg-admin-row__status--<?= $pedido->isFinalizado() ? 'on' : 'off' ?>">
                        <?= $pedido->statusLabel() ?>
                    </span>

                    <!-- Finalizado não abre para edição: a mesma tela vira
                         consulta, com os campos desabilitados. -->
                    <a class="gg-btn gg-btn--ghost gg-btn--sm" href="/admin/purchases/edit?id=<?= $pedido->id ?>">
                        <?= $pedido->isFinalizado() ? 'Ver' : 'Editar' ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
