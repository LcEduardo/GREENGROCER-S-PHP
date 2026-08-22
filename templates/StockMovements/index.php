<?php
/**
 * O livro de movimentações de estoque.
 *
 * Tela de CONSULTA: não há botão de criar, editar nem apagar, porque
 * movimentação não se digita — ela nasce de um pedido de compra finalizado. Os
 * dois controles são a busca por produto e a paginação do rodapé.
 *
 * @var \User\Greengrocers\Model\StockMovement[] $movimentacoes
 * @var ?string $searchProduct
 * @var int $total
 * @var int $pagina
 * @var int $totalPaginas
 * @var int $porPagina
 */

/**
 * O endereço de uma página, com a busca junto.
 *
 * A busca PRECISA viajar no link: sem ela, ir para a página 2 devolveria a
 * lista inteira e a tela mostraria movimentações de outro produto embaixo das
 * filtradas. A página 1 sai da URL de propósito — `?q=tomate` é o endereço
 * limpo e compartilhável do primeiro resultado.
 */
$urlDaPagina = static function (int $numero) use ($searchProduct): string {
    $parametros = array_filter(
        ['q' => $searchProduct, 'page' => $numero > 1 ? $numero : null],
        static fn ($valor) => $valor !== null,
    );

    return '/admin/stock-movements' . ($parametros === [] ? '' : '?' . http_build_query($parametros));
};

/**
 * Quais números aparecem no rodapé: sempre a primeira e a última, mais a
 * vizinhança da atual. Numerar todas quebraria a tela no dia em que a lista
 * passar de 40 páginas — e as extremidades são o que dá noção de tamanho.
 */
$numerosDaPaginacao = array_values(array_filter(
    range(1, $totalPaginas),
    static fn (int $numero) => $numero === 1
        || $numero === $totalPaginas
        || abs($numero - $pagina) <= 1,
));

// De onde até onde vai esta página, para o cabeçalho dizer em que ponto da
// lista o admin está. Sai da posição da página + o que ela realmente trouxe;
// a última vem incompleta e o número tem que acompanhar.
$primeiroDaPagina = ($pagina - 1) * $porPagina + 1;
$ultimoDaPagina = $primeiroDaPagina + count($movimentacoes) - 1;
?>
<div class="gg-page">

    <div class="gg-page__head">
        <div>
            <h1 class="gg-page__title"><?= htmlspecialchars($title) ?></h1>
            <p class="gg-page__sub">Toda entrada e saída de estoque, da mais recente para a mais antiga.</p>
        </div>
        <a class="gg-btn gg-btn--ghost" href="/admin/products">← Produtos</a>
    </div>

    <!-- Form GET comum: a busca funciona sem JavaScript, e a URL resultante
         (?q=tomate) é compartilhável e recarregável. Sem `page` escondido: uma
         busca nova começa da primeira página, e não da 4ª da busca anterior. -->
    <form class="gg-mov-search" action="/admin/stock-movements" method="get" role="search">
        <label class="gg-field gg-mov-search__field" for="gg-mov-q">
            <span class="gg-field__label">Buscar por produto</span>
            <input
                class="gg-input"
                type="search"
                id="gg-mov-q"
                name="q"
                value="<?= htmlspecialchars($searchProduct ?? '') ?>"
                placeholder="Nome do produto..."
                autocomplete="off"
            >
        </label>

        <button class="gg-btn gg-btn--primary" type="submit">Buscar</button>

        <?php if ($searchProduct !== null): ?>
            <a class="gg-btn gg-btn--ghost" href="/admin/stock-movements">Limpar</a>
        <?php endif; ?>
    </form>

    <?php if ($total === 0): ?>
        <p class="gg-admin-empty">
            <?= $searchProduct !== null
                ? 'Nenhuma movimentação para "' . htmlspecialchars($searchProduct) . '".'
                : 'Nenhuma movimentação de estoque registrada ainda.' ?>
        </p>
    <?php else: ?>
        <!-- O total sai do MESMO countAll() que divide as páginas: o número
             daqui e a quantidade de páginas do rodapé falam do mesmo conjunto,
             e é isso que os testes de contagem defendem. -->
        <p class="gg-mov-count">
            Mostrando <strong><?= $primeiroDaPagina ?>–<?= $ultimoDaPagina ?></strong>
            de <strong><?= $total ?></strong>
            <?= $total === 1 ? 'movimentação' : 'movimentações' ?>
            <?= $searchProduct !== null ? 'para "' . htmlspecialchars($searchProduct) . '"' : '' ?>
        </p>

        <div class="gg-mov-table-wrap">
            <table class="gg-mov-table">
                <thead>
                    <tr>
                        <th scope="col">Dt. Moved</th>
                        <th scope="col">Supplier</th>
                        <th scope="col">Produto</th>
                        <th scope="col">Tp. Movimento</th>
                        <th scope="col">Qtd</th>
                        <th scope="col">Histórico</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($movimentacoes as $movimentacao): ?>
                        <tr class="gg-mov-row gg-mov-row--<?= $movimentacao->isEntrada() ? 'in' : 'out' ?>">
                            <td class="gg-mov-row__date"><?= htmlspecialchars($movimentacao->formattedDate()) ?></td>

                            <!-- Fornecedor só existe do outro lado de uma COMPRA. Venda e
                                 ajuste manual ficam em branco mesmo — ver
                                 StockMovementRepository::findPage(). -->
                            <td class="gg-mov-row__supplier"><?= htmlspecialchars($movimentacao->supplierName ?? '') ?></td>

                            <td class="gg-mov-row__product"><?= htmlspecialchars($movimentacao->productName) ?></td>

                            <td>
                                <span class="gg-mov-tag gg-mov-tag--<?= htmlspecialchars($movimentacao->referenceType) ?>">
                                    <?= htmlspecialchars($movimentacao->referenceLabel()) ?>
                                </span>
                            </td>

                            <td class="gg-mov-row__qty"><?= htmlspecialchars($movimentacao->formattedQuantity()) ?></td>

                            <td class="gg-mov-row__historical"><?= htmlspecialchars($movimentacao->historical ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <!-- Links de verdade, um endereço por página: dá para abrir em
                 outra aba, voltar pelo botão do navegador e mandar a
                 página 3 para alguém. -->
            <nav class="gg-pager" aria-label="Paginação das movimentações">
                <?php if ($pagina > 1): ?>
                    <a class="gg-pager__step" href="<?= htmlspecialchars($urlDaPagina($pagina - 1)) ?>" rel="prev">← Anterior</a>
                <?php else: ?>
                    <!-- Desabilitado vira <span>: um link que não leva a lugar
                         nenhum ainda seria clicável e focável pelo teclado. -->
                    <span class="gg-pager__step gg-pager__step--off">← Anterior</span>
                <?php endif; ?>

                <ol class="gg-pager__pages">
                    <?php $anterior = 0; ?>
                    <?php foreach ($numerosDaPaginacao as $numero): ?>
                        <?php if ($numero - $anterior > 1): ?>
                            <li class="gg-pager__gap" aria-hidden="true">…</li>
                        <?php endif; ?>

                        <li>
                            <?php if ($numero === $pagina): ?>
                                <span class="gg-pager__page gg-pager__page--current" aria-current="page"><?= $numero ?></span>
                            <?php else: ?>
                                <a class="gg-pager__page" href="<?= htmlspecialchars($urlDaPagina($numero)) ?>" aria-label="Página <?= $numero ?>"><?= $numero ?></a>
                            <?php endif; ?>
                        </li>

                        <?php $anterior = $numero; ?>
                    <?php endforeach; ?>
                </ol>

                <?php if ($pagina < $totalPaginas): ?>
                    <a class="gg-pager__step" href="<?= htmlspecialchars($urlDaPagina($pagina + 1)) ?>" rel="next">Próxima →</a>
                <?php else: ?>
                    <span class="gg-pager__step gg-pager__step--off">Próxima →</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

</div>
