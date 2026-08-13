<?php
// $valores é o que o admin já tinha digitado, devolvido pelo controller quando
// a foto é recusada — vazio no primeiro carregamento. O campo de arquivo é a
// única exceção: o navegador não deixa nenhuma página preencher um <input
// type="file">, então a foto precisa ser escolhida de novo.
$primeiraVez = $valores === [];
?>
<div class="gg-page">

    <div class="gg-page__head">
        <div>
            <h1 class="gg-page__title"><?= htmlspecialchars($title) ?></h1>
            <p class="gg-page__sub">Produto novo entra ativo — já aparece na vitrine assim que salvar.</p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <p class="gg-alert" role="alert"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <!-- enctype multipart: sem ele o navegador manda só o NOME do arquivo, e o
         $_FILES chega vazio no servidor. -->
    <form class="gg-card-form" method="post" action="/admin/products/create" enctype="multipart/form-data">
        <div class="gg-form-grid">
            <div class="gg-field gg-field--wide">
                <label class="gg-field__label" for="name">Nome</label>
                <input class="gg-input" type="text" id="name" name="name" value="<?= htmlspecialchars($valores['name'] ?? '') ?>" required>
            </div>

            <div class="gg-field">
                <label class="gg-field__label" for="categoryId">Categoria</label>
                <select class="gg-select" id="categoryId" name="categoryId">
                    <?php foreach ($categorias as $id => $nome): ?>
                        <option value="<?= $id ?>"<?= (int) ($valores['categoryId'] ?? 0) === $id ? ' selected' : '' ?>><?= htmlspecialchars($nome) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="gg-field">
                <label class="gg-field__label" for="unit">Unidade</label>
                <input class="gg-input" type="text" id="unit" name="unit" value="<?= htmlspecialchars($valores['unit'] ?? '') ?>" placeholder="kg, un, maço" required>
            </div>

            <div class="gg-field">
                <label class="gg-field__label" for="salePrice">Preço de venda (R$)</label>
                <input class="gg-input" type="text" id="salePrice" name="salePrice" value="<?= htmlspecialchars($valores['salePrice'] ?? '') ?>" placeholder="7.90" required>
            </div>

            <div class="gg-field">
                <label class="gg-field__label" for="stockQuantity">Estoque</label>
                <input class="gg-input" type="text" id="stockQuantity" name="stockQuantity" value="<?= htmlspecialchars($valores['stockQuantity'] ?? '') ?>" placeholder="10.5" required>
            </div>

            <div class="gg-field gg-field--wide">
                <label class="gg-field__label" for="image">Foto</label>
                <input class="gg-file" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
                <p class="gg-field__hint">JPG, PNG ou WebP, até 2 MB. Sem foto, a vitrine mostra a moldura vazia.</p>
            </div>
        </div>

        <div class="gg-form-actions">
            <label class="gg-check">
                <input type="checkbox" name="active" value="1"<?= $primeiraVez || isset($valores['active']) ? ' checked' : '' ?>>
                Ativo
            </label>
            <button class="gg-btn gg-btn--primary" type="submit">Salvar</button>
            <a class="gg-btn gg-btn--ghost" href="/admin/products">Voltar</a>
        </div>
    </form>

</div>
