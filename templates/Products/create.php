<h1><?= htmlspecialchars($title) ?></h1>

<form method="post" action="/admin/products/create">
    <label>
        Nome
        <input type="text" name="name" required>
    </label>

    <label>
        Categoria
        <select name="categoryId">
            <?php foreach ($categorias as $id => $nome): ?>
                <option value="<?= $id ?>"><?= htmlspecialchars($nome) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        Unidade
        <input type="text" name="unit" required>
    </label>

    <label>
        Preço de venda
        <input type="text" name="salePrice" required>
    </label>

    <label>
        Estoque
        <input type="text" name="stockQuantity" required>
    </label>

    <label>
        <input type="checkbox" name="active" value="1" checked>
        Ativo
    </label>

    <label>
        Imagem
        <input type="text" name="image">
    </label>

    <button type="submit">Salvar</button>
</form>

<a href="/admin/products">Voltar</a>
