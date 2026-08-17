<?php

declare(strict_types=1);

namespace User\Greengrocers\Controller;

use DomainException;
use InvalidArgumentException;
use User\Greengrocers\Model\Purchase;
use User\Greengrocers\Model\PurchaseItem;
use User\Greengrocers\Repository\ProductRepository;
use User\Greengrocers\Repository\PurchaseRepository;
use User\Greengrocers\Repository\SupplierRepository;
use User\Greengrocers\View\View;

/**
 * As telas do pedido de compra.
 *
 * O formulário é UM só (templates/Purchases/form.php), usado para criar e para
 * editar: enquanto o pedido está aberto, a tela manda a lista FINAL de itens, e
 * o Repository reescreve tudo. Assim adicionar, remover e alterar item são a
 * mesma operação — não há endpoint por item, nem estado meio-salvo no servidor
 * enquanto o admin monta o pedido.
 *
 * Salvar e finalizar são o mesmo POST, separados pelo botão apertado (`acao`).
 * "Finalizar" grava e lança o estoque na sequência, que é o "finalizar direto,
 * sem salvar antes" da tela.
 */
class PurchasesController
{
    private View $view;

    public function __construct(
        private readonly PurchaseRepository $purchases,
        private readonly SupplierRepository $suppliers,
        private readonly ProductRepository $products,
    ) {
        $this->view = new View();
    }

    public function index(): void
    {
        $this->view->render('Purchases/index', [
            'title'        => 'Pedidos de compra',
            'pedidos'      => $this->purchases->findAll(),
            'fornecedores' => $this->suppliers->findAllKeyedById(),
        ]);
    }

    public function create(): void
    {
        $this->view->render('Purchases/form', $this->dadosDoFormulario(
            titulo: 'Novo pedido',
            pedido: null,
        ));
    }

    public function store(): void
    {
        $this->grava(pedidoExistente: null);
    }

    public function edit(): void
    {
        $pedido = $this->pedidoDaUrl();

        if ($pedido === null) {
            $this->naoEncontrado();
            return;
        }

        $this->view->render('Purchases/form', $this->dadosDoFormulario(
            // Finalizado não se edita: a mesma tela vira consulta, e o
            // template desabilita os campos.
            titulo: $pedido->isFinalizado() ? 'Pedido #' . $pedido->id : 'Editar pedido',
            pedido: $pedido,
        ));
    }

    public function update(): void
    {
        $pedido = $this->pedidoDaUrl();

        if ($pedido === null) {
            $this->naoEncontrado();
            return;
        }

        $this->grava(pedidoExistente: $pedido);
    }

    /**
     * O caminho de escrita das duas telas.
     *
     * Monta o pedido a partir do POST, grava e — se o botão foi "Finalizar" —
     * lança o estoque. Qualquer regra violada (fornecedor em branco, item sem
     * quantidade, produto inativo, pedido já finalizado) volta como aviso na
     * própria tela, com o que o admin já tinha digitado.
     */
    private function grava(?Purchase $pedidoExistente): void
    {
        $finalizar = filter_input(INPUT_POST, 'acao') === 'finalizar';

        try {
            if ($pedidoExistente?->isFinalizado()) {
                throw new DomainException('Pedido finalizado não pode ser editado.');
            }

            $pedido = new Purchase(
                supplierId: filter_input(INPUT_POST, 'supplierId', FILTER_VALIDATE_INT) ?: null,
                // Na edição, quem lançou continua sendo quem abriu o pedido.
                userId: $pedidoExistente?->userId ?? (int) ($_SESSION['user_id'] ?? 0),
                items: $this->itensDoPost(),
                id: $pedidoExistente?->id,
                notes: trim((string) filter_input(INPUT_POST, 'notes')) ?: null,
            );

            // A mesma regra do finalize(), conferida ANTES de gravar. Não é
            // desconfiança do Repository — lá é onde ela vale de verdade. É que
            // salvar e finalizar são duas chamadas, e não uma transação: sem
            // esta trava, "Finalizar" com a lista vazia gravaria o rascunho,
            // falharia no passo seguinte e redesenharia a tela de CRIAÇÃO. O
            // rascunho ficaria no banco sem aparecer na tela, e o segundo clique
            // criaria outro.
            if ($finalizar && $pedido->items === []) {
                throw new DomainException('Um pedido sem item não pode ser finalizado.');
            }

            $id = $this->purchases->save($pedido);

            if ($finalizar) {
                $this->purchases->finalize($id);
            }
        } catch (InvalidArgumentException | DomainException $erro) {
            $this->telaDeErro($erro->getMessage(), $pedidoExistente);
            return;
        }

        header('Location: /admin/purchases');
    }

    /**
     * As linhas de item que a tela mandou.
     *
     * O formulário manda `items[0][productId]`, `items[0][quantity]`... — linha
     * sem produto escolhido é descartada em silêncio: é a linha em branco que o
     * JS deixa pronta para a próxima digitação, não um erro do admin.
     *
     * @return PurchaseItem[]
     */
    private function itensDoPost(): array
    {
        $itens = [];

        foreach ((array) ($_POST['items'] ?? []) as $linha) {
            $produtoId = (int) ($linha['productId'] ?? 0);

            if ($produtoId <= 0) {
                continue;
            }

            $itens[] = new PurchaseItem(
                productId: $produtoId,
                quantity:  $this->decimal($linha['quantity'] ?? ''),
                costValue: $this->decimal($linha['costValue'] ?? ''),
            );
        }

        return $itens;
    }

    /**
     * "2,5" digitado no teclado brasileiro vira "2.5", que é o que o banco
     * entende. Campo em branco vira "0" — no custo isso é o reajuste de
     * estoque; na quantidade, o PurchaseItem recusa e a tela avisa.
     */
    private function decimal(string $valor): string
    {
        $limpo = str_replace(',', '.', trim($valor));

        return $limpo === '' ? '0' : $limpo;
    }

    private function pedidoDaUrl(): ?Purchase
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;

        return $id !== null ? $this->purchases->findById($id) : null;
    }

    /**
     * Redesenha o formulário com o aviso e com o que veio no POST — recusar uma
     * linha não pode custar o pedido inteiro digitado de novo.
     */
    private function telaDeErro(string $aviso, ?Purchase $pedido): void
    {
        $this->view->render('Purchases/form', $this->dadosDoFormulario(
            titulo: $pedido === null ? 'Novo pedido' : 'Editar pedido',
            pedido: $pedido,
            erro: $aviso,
            // O POST cru, e não o $pedido: o que o admin acabou de digitar é
            // mais recente que o que está gravado.
            valores: $_POST,
        ));
    }

    /**
     * O que as duas telas de formulário precisam.
     *
     * `produtos` vem do findActive(): só produto ATIVO pode ser escolhido, sem
     * olhar o estoque — pedido de compra é justamente o que repõe o que está
     * zerado.
     *
     * `valores` só é preenchido quando a tela volta com aviso — aí ele tem a
     * última palavra sobre o que aparece nos campos.
     *
     * @param array<string, mixed> $valores
     * @return array<string, mixed>
     */
    private function dadosDoFormulario(
        string $titulo,
        ?Purchase $pedido,
        ?string $erro = null,
        array $valores = [],
    ): array {
        return [
            'title'        => $titulo,
            'pedido'       => $pedido,
            'fornecedores' => $this->suppliers->findAll(),
            'produtos'     => $this->products->findActive(),
            'error'        => $erro,
            'valores'      => $valores,
        ];
    }

    private function naoEncontrado(): void
    {
        http_response_code(404);
        echo 'Pedido de compra não encontrado';
    }
}
