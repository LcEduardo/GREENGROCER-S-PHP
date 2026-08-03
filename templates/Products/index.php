<h1><?= htmlspecialchars($title) ?></h1>

<nav aria-label="Filtrar por categoria">
    <?php $sufixoBusca = $busca !== null ? '&q=' . urlencode($busca) : ''; ?>
    <a href="/<?= $busca !== null ? '?q=' . urlencode($busca) : '' ?>"<?= $categoriaSelecionada === null ? ' aria-current="page"' : '' ?>>Todos</a>
    <?php foreach ($categorias as $id => $nome): ?>
        <a href="/?category=<?= $id . $sufixoBusca ?>"<?= $categoriaSelecionada === $id ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($nome) ?></a>
    <?php endforeach; ?>
    <p><?= $countItems ?></p>
</nav>

<?php if (empty($produtos)): ?>
    <p>Nenhum produto disponível no momento.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Produto</th>
                <th>Unidade</th>
                <th>Preço</th>
                <th>Estoque</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($produtos as $produto): ?>
                <tr>
                    <td><?= htmlspecialchars($produto->name) ?></td>
                    <td><?= htmlspecialchars($produto->unit) ?></td>
                    <td><?= $produto->formattedPrice() ?></td>
                    <td><?= $produto->formattedStock() ?></td>
                    <td>
                        <form action="/cart/add" method="post">
                            <input type="hidden" name="productId" value="<?= $produto->id ?>">
                            <input type="hidden" name="quantity" value="1">
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?>">
                            <button type="submit">+</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
