<?php
/**
 * Formulário do pedido de compra — serve criar e editar.
 *
 * Três fontes possíveis para o que aparece na tela, nesta ordem de prioridade:
 *
 * 1. $valores — o POST que acabou de ser recusado (só existe com $error);
 * 2. $pedido  — o que está gravado, na edição;
 * 3. vazio    — pedido novo.
 */

use User\Greengrocers\Model\Purchase;

/** @var ?Purchase $pedido */
$bloqueado = $pedido?->isFinalizado() ?? false;
$voltouComErro = $valores !== [];

if ($voltouComErro) {
    $fornecedorEscolhido = (int) ($valores['supplierId'] ?? 0);
    $observacoes = (string) ($valores['notes'] ?? '');
    $linhas = array_values(array_filter(
        (array) ($valores['items'] ?? []),
        static fn ($linha) => ($linha['productId'] ?? '') !== '',
    ));
} else {
    $fornecedorEscolhido = $pedido?->supplierId ?? 0;
    $observacoes = $pedido?->notes ?? '';
    $linhas = array_map(static fn ($item) => [
        'productId' => $item->productId,
        'quantity'  => $item->quantity,
        'costValue' => $item->costValue,
    ], $pedido?->items ?? []);
}

$destino = $pedido === null ? '/admin/purchases/create' : '/admin/purchases/edit?id=' . $pedido->id;

/**
 * As <option> do seletor de produto de uma linha. Só produtos ATIVOS chegam
 * aqui — ver PurchasesController::dadosDoFormulario().
 */
$opcoesDeProduto = function (int $selecionado) use ($produtos): string {
    $html = '<option value="">Escolha o produto…</option>';

    foreach ($produtos as $produto) {
        $html .= sprintf(
            '<option value="%d"%s>%s (%s)</option>',
            $produto->id,
            $produto->id === $selecionado ? ' selected' : '',
            htmlspecialchars($produto->name),
            htmlspecialchars($produto->unit),
        );
    }

    return $html;
};

/** Desenha uma linha de item. $indice entra nos names: items[0][quantity]. */
$linhaDeItem = function (int $indice, array $dados, bool $bloqueado) use ($opcoesDeProduto): string {
    $desabilitado = $bloqueado ? ' disabled' : '';
    $opcoes = $opcoesDeProduto((int) ($dados['productId'] ?? 0));

    return sprintf(
        '<div class="gg-item-row">
            <div class="gg-field gg-item-row__product">
                <label class="gg-field__label">Produto</label>
                <select class="gg-select" name="items[%1$d][productId]"%3$s>%2$s</select>
            </div>
            <div class="gg-field">
                <label class="gg-field__label">Quantidade</label>
                <input class="gg-input gg-js-quantity" type="text" inputmode="decimal" name="items[%1$d][quantity]" value="%4$s" placeholder="0,000"%3$s>
            </div>
            <div class="gg-field">
                <label class="gg-field__label">Custo unitário (R$)</label>
                <input class="gg-input gg-js-cost" type="text" inputmode="decimal" name="items[%1$d][costValue]" value="%5$s" placeholder="opcional"%3$s>
            </div>
            <div class="gg-field gg-item-row__subtotal">
                <label class="gg-field__label">Subtotal</label>
                <span class="gg-item-row__total gg-js-subtotal">R$ 0,00</span>
            </div>
            <button class="gg-item-row__remove gg-js-remove" type="button" aria-label="Remover item"%3$s>✕</button>
        </div>',
        $indice,
        $opcoes,
        $desabilitado,
        htmlspecialchars((string) ($dados['quantity'] ?? '')),
        htmlspecialchars((string) ($dados['costValue'] ?? '')),
    );
};
?>
<div class="gg-page">

    <div class="gg-page__head">
        <div>
            <h1 class="gg-page__title"><?= htmlspecialchars($title) ?></h1>
            <p class="gg-page__sub">
                <?php if ($bloqueado): ?>
                    Pedido finalizado — o estoque já recebeu estas entradas, então nada mais muda por aqui.
                <?php else: ?>
                    Salvar guarda o pedido em aberto. Finalizar é o que soma no estoque, e não tem volta.
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <p class="gg-alert" role="alert"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form class="gg-card-form" method="post" action="<?= $destino ?>" id="gg-purchase-form">
        <div class="gg-form-grid">
            <div class="gg-field gg-field--wide">
                <label class="gg-field__label" for="supplierId">Fornecedor</label>
                <select class="gg-select" id="supplierId" name="supplierId"<?= $bloqueado ? ' disabled' : '' ?>>
                    <option value="">Escolha o fornecedor…</option>
                    <?php foreach ($fornecedores as $fornecedor): ?>
                        <option value="<?= $fornecedor->id ?>"<?= $fornecedor->id === $fornecedorEscolhido ? ' selected' : '' ?>>
                            <?= htmlspecialchars($fornecedor->name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="gg-items">
            <div class="gg-items__head">
                <h2 class="gg-items__title">Itens</h2>
                <?php if (!$bloqueado): ?>
                    <button class="gg-btn gg-btn--ghost gg-btn--sm" type="button" id="gg-add-item">+ Adicionar item</button>
                <?php endif; ?>
            </div>

            <div id="gg-items-list">
                <?php foreach ($linhas as $indice => $dados): ?>
                    <?= $linhaDeItem($indice, $dados, $bloqueado) ?>
                <?php endforeach; ?>
            </div>

            <!-- Sem item nenhum não há pedido; a frase some assim que a
                 primeira linha entra (o JS cuida disso). -->
            <p class="gg-items__empty" id="gg-items-empty" style="<?= $linhas === [] ? '' : 'display:none' ?>">
                Nenhum item ainda. Adicione pelo menos um para salvar o pedido.
            </p>

            <div class="gg-items__total">
                <span class="gg-items__total-label">Total do pedido</span>
                <span class="gg-items__total-value" id="gg-items-total">R$ 0,00</span>
            </div>
        </div>

        <div class="gg-field gg-field--wide">
            <label class="gg-field__label" for="notes">Observações</label>
            <input class="gg-input" type="text" id="notes" name="notes" value="<?= htmlspecialchars($observacoes) ?>" placeholder="Opcional"<?= $bloqueado ? ' disabled' : '' ?>>
        </div>

        <div class="gg-form-actions">
            <?php if ($bloqueado): ?>
                <a class="gg-btn gg-btn--ghost" href="/admin/purchases">Voltar</a>
            <?php else: ?>
                <!-- Mesmo POST para os dois; o `acao` diz qual botão foi
                     apertado. "Finalizar" grava e lança o estoque na sequência. -->
                <button class="gg-btn gg-btn--ghost" type="submit" name="acao" value="salvar">Salvar</button>
                <button class="gg-btn gg-btn--primary" type="submit" name="acao" value="finalizar">Finalizar</button>
                <a class="gg-btn gg-btn--ghost" href="/admin/purchases">Voltar</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!$bloqueado): ?>
        <!-- A linha em branco que o JS clona a cada "+ Adicionar item". Mora
             num <template> para o navegador não mandar estes campos vazios
             junto no POST. O índice sai zerado aqui e é reescrito pelo JS na
             hora de inserir, para não colidir com as linhas já na tela. -->
        <template id="gg-item-template">
            <?= $linhaDeItem(0, [], false) ?>
        </template>
    <?php endif; ?>

</div>

<!-- Carregado só aqui, e não no layout: é a única tela do site com linhas de
     item. O arquivo sai fora na primeira linha se não achar o formulário. -->
<script src="/js/purchases.js" defer></script>
