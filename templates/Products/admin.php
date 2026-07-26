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
                <th>Ativo</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($produtos as $produto): ?>
                <tr>
                    <td><?= htmlspecialchars($produto->name) ?></td>
                    <td><?= htmlspecialchars($produto->unit) ?></td>
                    <td><?= $produto->formattedPrice() ?></td>
                    <td><?= $produto->formattedStock() ?></td>
                    <td><?= $produto->active ? 'Sim' : 'Não' ?></td>
                    <td><a href="/admin/products/edit?id=<?= $produto->id ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>