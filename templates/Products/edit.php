<h1><?= htmlspecialchars($title) ?></h1>

<form method="post" action="/admin/products/edit?id=<?= $produto->id ?>">
    <label>
        Nome
        <input type="text" name="name" value="<?= htmlspecialchars($produto->name) ?>" required>
    </label>

    <label>
        Categoria
        <select name="categoryId">
            <?php foreach ($categorias as $id => $nome): ?>
                <option value="<?= $id ?>"<?= $produto->categoryId === $id ? ' selected' : '' ?>><?= htmlspecialchars($nome) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        Unidade
        <input type="text" name="unit" value="<?= htmlspecialchars($produto->unit) ?>" required>
    </label>

    <label>
        Preço de venda
        <input type="text" name="salePrice" value="<?= htmlspecialchars($produto->salePrice) ?>" required>
    </label>

    <label>
        Estoque
        <input type="text" name="stockQuantity" value="<?= htmlspecialchars($produto->stockQuantity) ?>" required>
    </label>

    <label>
        <input type="checkbox" name="active" value="1"<?= $produto->active ? ' checked' : '' ?>>
        Ativo
    </label>

    <label>
        Imagem
        <input type="text" name="image" value="<?= htmlspecialchars($produto->image ?? '') ?>">
    </label>

    <button type="submit">Salvar</button>
</form>

<a href="/admin/products">Voltar</a>
