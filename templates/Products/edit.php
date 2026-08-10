<div class="gg-page">

    <div class="gg-page__head">
        <div>
            <h1 class="gg-page__title"><?= htmlspecialchars($title) ?></h1>
            <p class="gg-page__sub">Desmarcar "Ativo" tira o produto da vitrine sem apagar o cadastro.</p>
        </div>
    </div>

    <form class="gg-card-form" method="post" action="/admin/products/edit?id=<?= $produto->id ?>">
        <div class="gg-form-grid">
            <div class="gg-field gg-field--wide">
                <label class="gg-field__label" for="name">Nome</label>
                <input class="gg-input" type="text" id="name" name="name" value="<?= htmlspecialchars($produto->name) ?>" required>
            </div>

            <div class="gg-field">
                <label class="gg-field__label" for="categoryId">Categoria</label>
                <select class="gg-select" id="categoryId" name="categoryId">
                    <?php foreach ($categorias as $id => $nome): ?>
                        <option value="<?= $id ?>"<?= $produto->categoryId === $id ? ' selected' : '' ?>><?= htmlspecialchars($nome) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="gg-field">
                <label class="gg-field__label" for="unit">Unidade</label>
                <input class="gg-input" type="text" id="unit" name="unit" value="<?= htmlspecialchars($produto->unit) ?>" required>
            </div>

            <div class="gg-field">
                <label class="gg-field__label" for="salePrice">Preço de venda (R$)</label>
                <input class="gg-input" type="text" id="salePrice" name="salePrice" value="<?= htmlspecialchars($produto->salePrice) ?>" required>
            </div>

            <div class="gg-field">
                <label class="gg-field__label" for="stockQuantity">Estoque</label>
                <input class="gg-input" type="text" id="stockQuantity" name="stockQuantity" value="<?= htmlspecialchars($produto->stockQuantity) ?>" required>
            </div>

            <div class="gg-field gg-field--wide">
                <label class="gg-field__label" for="image">Imagem</label>
                <input class="gg-input" type="text" id="image" name="image" value="<?= htmlspecialchars($produto->image ?? '') ?>">
            </div>
        </div>

        <div class="gg-form-actions">
            <label class="gg-check">
                <input type="checkbox" name="active" value="1"<?= $produto->active ? ' checked' : '' ?>>
                Ativo
            </label>
            <button class="gg-btn gg-btn--primary" type="submit">Salvar</button>
            <a class="gg-btn gg-btn--ghost" href="/admin/products">Voltar</a>
        </div>
    </form>

</div>
