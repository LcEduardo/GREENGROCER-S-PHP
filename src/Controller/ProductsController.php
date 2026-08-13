<?php

declare(strict_types=1);

namespace User\Greengrocers\Controller;

use User\Greengrocers\View\View;
use User\Greengrocers\Model\Product;
use User\Greengrocers\Repository\ProductRepository;

class ProductsController
{
    private View $view;

    /**
     * As categorias da vitrine são um conjunto fixo: "Todos | Frutas | Legumes |
     * Verduras". Ficam aqui, e não numa consulta, porque são um punhado estável —
     * ir ao banco a cada request só pra montar 3 links seria peso sem retorno.
     *
     * ⚠️ Os ids têm que bater com as linhas de `categories` no banco: é o
     * `category_id` que vai pro filtro do repositório.
     */
    private const CATEGORIAS = [
        1 => 'Frutas',
        2 => 'Legumes',
        3 => 'Verduras',
    ];

    /**
     * 2 MB. Casa com o `upload_max_filesize` do PHP do Herd, mas não depende
     * dele: o ini corta antes e a gente ainda confere depois — as duas checagens
     * dizem a mesma coisa por caminhos diferentes, e é assim que tem que ser.
     */
    private const IMAGEM_MAXIMA = 2 * 1024 * 1024;

    /**
     * O tipo REAL do arquivo decide a extensão. Formato fora desta lista não
     * entra: é o que impede um .php de virar arquivo servido dentro de public/.
     *
     * @var array<string, string>
     */
    private const EXTENSOES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    // O repositório chega pronto de fora (montado em public/index.php). Assim
    // este controller não sabe que existe um banco: se amanhã os produtos
    // vierem de outra fonte, nada aqui muda.
    public function __construct(
        private readonly ProductRepository $products,
    ) {
        $this->view = new View();
    }

    public function index(): void
    {
        // ?category vem da URL — sempre string, e editável por quem quiser. O
        // findActive() espera ?int, então convertemos: 1/2/3 passam; ausente ou
        // lixo ("abc") vira null, que a vitrine lê como "Todos".
        $categoryId = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: null;
        $busca = trim((string) filter_input(INPUT_GET, 'q')) ?: null;
        $produtos = $this->products->findActive($categoryId, $busca);

        $this->view->render('Products/index', [
            'title'                => 'Produtos',
            'produtos'             => $produtos,
            'categorias'           => self::CATEGORIAS,
            'categoriaSelecionada' => $categoryId,
            'countItems'           => count($produtos),
            'busca'                => $busca,
        ]);
    }
    
    public function admin(): void
    {
        $this->view->render('Products/admin', [
            'title'      => 'Produtos',
            'produtos'   => $this->products->findAllProducts(),
            'categorias' => self::CATEGORIAS,
        ]);
    }

    public function edit(): void
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
        $produto = $id !== null ? $this->products->findById($id) : null;

        if ($produto === null) {
            http_response_code(404);
            echo 'Produto não encontrado';
            return;
        }

        $this->view->render('Products/edit', [
            'title'      => 'Editar produto',
            'produto'    => $produto,
            'categorias' => self::CATEGORIAS,
        ]);
    }

    public function update(): void
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;

        // Carregado antes de escrever porque o UPDATE precisa saber qual foto
        // estava lá: é ela que fica sem dono se o admin mandar outra.
        $produto = $id !== null ? $this->products->findById($id) : null;

        if ($produto === null) {
            http_response_code(404);
            echo 'Produto não encontrado';
            return;
        }

        $telaDeErro = fn (string $aviso) => $this->view->render('Products/edit', [
            'title'      => 'Editar produto',
            'produto'    => $produto,
            'categorias' => self::CATEGORIAS,
            'error'      => $aviso,
        ]);

        if ($this->postVeioVazioDemais()) {
            $telaDeErro('O envio passou do limite do servidor. Mande uma imagem menor.');
            return;
        }

        [$enviada, $falha] = $this->salvaImagem();

        if ($falha !== null) {
            // Nada foi gravado: a tela volta com o aviso e os valores do banco,
            // que continuam sendo os verdadeiros.
            $telaDeErro($falha);
            return;
        }

        // Três caminhos: veio foto nova, pediram pra tirar a que tinha, ou nem
        // um nem outro — e aí a atual segue como está.
        $removeu = filter_input(INPUT_POST, 'removeImage') !== null;
        $imagem = $enviada ?? ($removeu ? null : $produto->image);

        $this->products->update(
            id: $produto->id,
            name: (string) filter_input(INPUT_POST, 'name'),
            categoryId: (int) filter_input(INPUT_POST, 'categoryId', FILTER_VALIDATE_INT),
            unit: (string) filter_input(INPUT_POST, 'unit'),
            salePrice: (string) filter_input(INPUT_POST, 'salePrice'),
            stockQuantity: (string) filter_input(INPUT_POST, 'stockQuantity'),
            // Checkbox desmarcado nem entra no POST — a presença da chave é o sinal.
            active: filter_input(INPUT_POST, 'active') !== null,
            image: $imagem,
        );

        // Só DEPOIS do UPDATE passar: apagar antes deixaria a linha apontando
        // para um arquivo que não existe mais, se a escrita falhasse.
        if ($imagem !== $produto->image) {
            $this->apagaImagem($produto->image);
        }

        header('Location: /admin/products');
    }

    public function create(): void
    {
        $this->view->render('Products/create', [
            'title'      => 'Novo produto',
            'categorias' => self::CATEGORIAS,
            // Formulário em branco. Na volta de um erro, aqui vem o que o admin
            // já tinha digitado — ver store().
            'valores'    => [],
        ]);
    }

    public function store(): void
    {
        $telaDeErro = fn (string $aviso) => $this->view->render('Products/create', [
            'title'      => 'Novo produto',
            'categorias' => self::CATEGORIAS,
            'error'      => $aviso,
            // Devolve o que foi digitado: recusar a foto não pode custar o
            // formulário inteiro preenchido de novo.
            'valores'    => $_POST,
        ]);

        if ($this->postVeioVazioDemais()) {
            $telaDeErro('O envio passou do limite do servidor. Mande uma imagem menor.');
            return;
        }

        [$imagem, $falha] = $this->salvaImagem();

        if ($falha !== null) {
            $telaDeErro($falha);
            return;
        }

        $this->products->create(
            name: (string) filter_input(INPUT_POST, 'name'),
            categoryId: (int) filter_input(INPUT_POST, 'categoryId', FILTER_VALIDATE_INT),
            unit: (string) filter_input(INPUT_POST, 'unit'),
            salePrice: (string) filter_input(INPUT_POST, 'salePrice'),
            stockQuantity: (string) filter_input(INPUT_POST, 'stockQuantity'),
            // Checkbox desmarcado nem entra no POST — a presença da chave é o sinal.
            active: filter_input(INPUT_POST, 'active') !== null,
            image: $imagem,
        );

        header('Location: /admin/products');
    }

    /**
     * Pegadinha do multipart: um POST maior que o `post_max_size` do PHP chega
     * com $_POST E $_FILES VAZIOS — sem aviso, sem código de erro. Sem esta
     * checagem o formulário seguiria em frente e salvaria um produto de nome
     * vazio, que é como isso aparece na prática.
     *
     * O Content-Length é o que denuncia: o navegador mandou corpo, o PHP jogou
     * fora.
     */
    private function postVeioVazioDemais(): bool
    {
        return $_POST === []
            && $_FILES === []
            && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
    }

    /**
     * A foto do produto: do temporário do PHP para dentro de public/.
     *
     * É o único ponto do sistema que toca em arquivo vindo de fora, e por isso
     * concentra as três desconfianças que um upload merece: o nome é do
     * servidor, o formato sai dos BYTES e o tamanho tem teto.
     *
     * Devolve `[nome, erro]` em vez de lançar exceção: quem chama já está no
     * meio de um formulário, onde "deu errado" é uma mensagem na tela e não um
     * acidente. Os dois vêm null quando o campo veio vazio — na edição é assim
     * que se diz "não mexe na foto que já está lá".
     *
     * Guarda só o NOME do arquivo; quem transforma nome em URL é o
     * Product::imageUrl().
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function salvaImagem(): array
    {
        $arquivo = $_FILES['image'] ?? [];
        $erro = $arquivo['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($erro === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }

        if ($erro === UPLOAD_ERR_INI_SIZE || $erro === UPLOAD_ERR_FORM_SIZE) {
            return [null, 'A imagem passa do limite de 2 MB.'];
        }

        if ($erro !== UPLOAD_ERR_OK) {
            return [null, 'O envio da imagem falhou no meio. Tente de novo.'];
        }

        $temporario = (string) ($arquivo['tmp_name'] ?? '');

        // filesize() no arquivo recebido, e não o $arquivo['size']: o tamanho é
        // o dos bytes que chegaram, não um número que veio junto do pedido.
        if (filesize($temporario) > self::IMAGEM_MAXIMA) {
            return [null, 'A imagem passa do limite de 2 MB.'];
        }

        // O tipo lido dos BYTES, não o $arquivo['type'] — aquele é texto que o
        // navegador mandou, e dá pra forjar.
        $extensao = self::EXTENSOES[mime_content_type($temporario) ?: ''] ?? null;

        if ($extensao === null) {
            return [null, 'Formato não aceito — mande JPG, PNG ou WebP.'];
        }

        // O nome NASCE aqui. O $arquivo['name'] é texto que veio do navegador:
        // se virasse nome de arquivo, dois "foto.png" se sobrescreveriam, e um
        // nome com ../ escreveria fora da pasta.
        $nome = bin2hex(random_bytes(16)) . '.' . $extensao;

        if (!move_uploaded_file($temporario, $this->pastaDasFotos() . '/' . $nome)) {
            return [null, 'Não foi possível guardar a imagem no servidor.'];
        }

        return [$nome, null];
    }

    /**
     * Apaga a foto que ninguém mais usa — quando o admin troca ou tira a
     * imagem do produto.
     *
     * Só aceita nome gerado pelo salvaImagem(): 32 hex e uma extensão conhecida.
     * A coluna `image` é mais velha que isto e já guardou URL digitada à mão, de
     * quando o campo era texto livre; o que não tem a nossa cara fica onde está,
     * em vez de virar unlink() num caminho que não é nosso.
     */
    private function apagaImagem(?string $nome): void
    {
        if ($nome === null || preg_match('/^[0-9a-f]{32}\.(jpg|png|webp)$/', $nome) !== 1) {
            return;
        }

        $caminho = $this->pastaDasFotos() . '/' . $nome;

        if (is_file($caminho)) {
            unlink($caminho);
        }
    }

    /**
     * A pasta em disco sai da MESMA constante que o Product::imageUrl() publica:
     * daqui, `dirname(__DIR__, 2)` é a raiz do projeto. Derivar em vez de
     * repetir a string é o que impede o upload de cair numa pasta e o <img> de
     * apontar para outra.
     */
    private function pastaDasFotos(): string
    {
        return dirname(__DIR__, 2) . '/public' . rtrim(Product::CAMINHO_PUBLICO, '/');
    }
}