<div class="gg-page">

    <div class="gg-page__head">
        <div>
            <h1 class="gg-page__title"><?= htmlspecialchars($title) ?></h1>
            <p class="gg-page__sub">Desmarcar "Ativo" tira o produto da vitrine sem apagar o cadastro.</p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <p class="gg-alert" role="alert"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <!-- enctype multipart: sem ele o navegador manda só o NOME do arquivo, e o
         $_FILES chega vazio no servidor. -->
    <form class="gg-card-form" method="post" action="/admin/products/edit?id=<?= $produto->id ?>" enctype="multipart/form-data">
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
                <label class="gg-field__label" for="image">Foto</label>

                <div class="gg-photo-field">
                    <?php if ($produto->imageUrl() !== null): ?>
                        <img class="gg-photo-field__thumb" src="<?= htmlspecialchars($produto->imageUrl()) ?>" alt="Foto atual de <?= htmlspecialchars($produto->name) ?>">
                    <?php else: ?>
                        <div class="gg-photo-field__thumb gg-photo-field__thumb--empty" aria-hidden="true">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                <path d="m21 15-5-5L5 21"/>
                            </svg>
                        </div>
                    <?php endif; ?>

                    <div class="gg-photo-field__controls">
                        <input class="gg-file" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
                        <p class="gg-field__hint">
                            <!-- Campo de arquivo vazio = mantém a de agora. É por isso que
                                 tirar a foto precisa de um checkbox próprio: não existe
                                 "arquivo nenhum, de propósito". -->
                            Deixe em branco pra manter a foto atual. JPG, PNG ou WebP, até 2 MB.
                        </p>

                        <?php if ($produto->image !== null && $produto->image !== ''): ?>
                            <label class="gg-check gg-check--sm">
                                <input type="checkbox" name="removeImage" value="1">
                                Remover a foto atual
                            </label>
                        <?php endif; ?>
                    </div>
                </div>
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
