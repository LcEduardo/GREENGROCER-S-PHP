<h1><?= htmlspecialchars($title) ?></h1>

<nav aria-label="Filtrar por categoria">
    <a href="/"<?= $categoriaSelecionada === null ? ' aria-current="page"' : '' ?>>Todos</a>
    <?php foreach ($categorias as $id => $nome): ?>
        <a href="/?category=<?= $id ?>"<?= $categoriaSelecionada === $id ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($nome) ?></a>
    <?php endforeach; ?>
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
            </tr>
        </thead>
        <tbody>
            <?php foreach ($produtos as $produto): ?>
                <tr>
                    <td><?= htmlspecialchars($produto->name) ?></td>
                    <td><?= htmlspecialchars($produto->unit) ?></td>
                    <td><?= $produto->formattedPrice() ?></td>
                    <td><?= $produto->formattedStock() ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
