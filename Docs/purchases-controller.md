## Contexto:

Usuários administradores conseguem acessar pelo `nav` o link `/admin/purchases` que chama no controller o método `index()`. Essa tela precisa mostrar todos os pedidos de compra criados, se houver. Para isso, ele chama um no repository o método `findAll()`.

## Detalhe do método

Ele busca da tabela `purchases` todas as linhas presentes:

```php
$statement = $this->pdo->query(
	'SELECT id, supplier_id, user_id, purchased_at, notes, status_id'
	. ' FROM purchases'
	. ' ORDER BY purchased_at DESC, id DESC'
);

$linhas = $statement->fetchAll();
```

Ele retorna:

```php
$linhas = [
    ['id' => '7', 'supplier_id' => '3', 'user_id' => '1', ...],
    ['id' => '6', 'supplier_id' => '2', ...],
];
```

---
# Criando um Novo Pedido

Na tela `/admin/purchases` temos o botão **+ Novo Pedido**. Ao clicarmos nele, disparamos o método de request `GET`. Que será tratado no nosso router:

```php
case '/admin/purchases/create':
	if (!Guard::isAdmin()) {
		header('Location: ' . (Guard::isLoggedIn() ? '/' : '/login'));
		break;
	}
	
	$controller = purchasesController();
	
	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
		$controller->store();
	} else {
		$controller->create();
	}
	break;
```

Temos acesso à instância do `PurchasesController` através da variável `$controller` (montada pela função `purchasesController()`) — é ele quem guarda os repositories (`PurchaseRepository`, `SupplierRepository`, `ProductRepository`) internamente, recebidos no construtor. O router não fala com o banco em momento nenhum; ele só decide qual método do controller chamar. E fazemos uma verificação: 

 Usamos `?? 'GET'` (operador de coalescência nula) na ``superglobal`` do PHP  ``$_SERVER['REQUEST_METHOD']`` não for definido ou ser `null` atribuímos o valor `GET`, isso ocorre pois assim não é retornado um erro caso isso aconteça. 

> OBS: isso é útil se formos acessar via CLI. Como estamos usando agentes de IA eles muitas das vezes acessar por esse caminho, que não vai gerar uma requisição HTTP. Pois, não tem o servidor web intermediando isso. 

```txt
SE o método enviado for POST
	Acessamos o método STORE() do controller
CASO CONTRÁRIO
	Acessamos o método CREATE()
```

## Método ``create()``

Renderiza a tela que preenchemos para criar um pedido de compra

```php
public function create(): void
{
	$this->view->render('Purchases/form', $this->dadosDoFormulario(
		titulo: 'Novo pedido',
		pedido: null,
	));
}
```

Repare que `create()` não fala com o banco em nenhum momento — quem faz isso é a `dadosDoFormulario()`, chamada logo abaixo. O nome do método é sobre a TELA (montar o formulário vazio), não sobre gravar o pedido; isso só acontece em `store()`, no POST.

No controller como padrão do nosso código já instanciamos o objeto `View` que nos permite chamar o método `render()`. 

- Passamos qual o TEMPLATE ele deve utilizar e um array de dados que podem ser utilizados dentro desse template.
- Usamos a função `extract()` que pega esse array e cada chave dele vira uma variável. Assim podemos escrever `<?= $title ?>` ao invés `<?= $data['title'] ?>`. (Muitos frameworks não utilizam ele, mas como é um projeto feito ´a mão´) 
- Por trás desse `extract()`, o `render()` usa `ob_start()` / `ob_get_clean()` pra capturar o HTML que o template gera dentro de `$content`, e só depois faz um SEGUNDO `require`, agora de `templates/layout/default.php` — é esse arquivo que injeta `$content` dentro do `<body>` e desenha o que é comum a toda página (nav, head, etc.). O `create()` nunca vê esse layout; ele só entrega os dados pro `render()` cuidar do resto.

Os dados que enviamos vem através da função: `dadosDoFormulario()` que nesse caso precisamos enviar o título. E ele retorna para gente:

```php
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
```

O título = 'Novo Pedido', o pedido = `null` — e é esse `null` que sinaliza pra `Purchases/form.php` que estamos criando, não editando. O mesmo template é reaproveitado por `edit()`, passando um `Purchase` de verdade no lugar; é por isso que `dadosDoFormulario()` recebe `pedido` como parâmetro em vez de já vir fixo — ela serve as duas telas.

As informações dos produtos que podemos escolher vêm de `'produtos' => $this->products->findActive()`, que traz só produto **ATIVO**, sem olhar estoque — o pedido de compra é justamente quem repõe o que está zerado, então um produto sem estoque ainda precisa aparecer na lista; o que não pode aparecer é um produto desativado. O mesmo raciocínio, mais simples, vale para fornecedores: `'fornecedores' => $this->suppliers->findAll()`.

Com isso, temos a tela de formulário `Purchases/form` onde preencheremos as informações do pedido de compra. 

---

## Método ``store()``

Quando a tela de formulário é enviada via `POST`, o mesmo router chama `store()` em vez de `create()`.

```php
public function store(): void
{
	$this->grava(pedidoExistente: null);
}
```

`store()` sozinho quase não faz nada — só chama o método privado `grava()`, avisando que não existe pedido anterior (`null`). Esse mesmo `grava()` é reaproveitado depois por `update()`, passando o pedido que já existe no lugar do `null`. A diferença entre criar e editar é só essa: existe um pedido gravado por trás ou não.

### O que `grava()` faz

```php
private function grava(?Purchase $pedidoExistente): void
{
	$finalizar = filter_input(INPUT_POST, 'acao') === 'finalizar';

	try {
		if ($pedidoExistente?->isFinalizado()) {
			throw new DomainException('Pedido finalizado não pode ser editado.');
		}

		$pedido = new Purchase(
			supplierId: filter_input(INPUT_POST, 'supplierId', FILTER_VALIDATE_INT) ?: null,
			userId: $pedidoExistente?->userId ?? (int) ($_SESSION['user_id'] ?? 0),
			items: $this->itensDoPost(),
			id: $pedidoExistente?->id,
			notes: trim((string) filter_input(INPUT_POST, 'notes')) ?: null,
		);

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
```

Em ordem, é isto que acontece:

1. Descobre qual botão foi apertado — "Salvar" ou "Finalizar" — olhando o campo `acao` que o formulário manda. Os dois botões usam o mesmo `name`, só o valor muda.
2. Se o pedido já estava finalizado, lança um erro na hora: pedido finalizado não edita mais.
3. Monta o pedido com o que veio do formulário: fornecedor, itens, quem está lançando e as observações. Se faltar fornecedor, ou algum item tiver quantidade inválida, o próprio pedido já lança um erro sozinho, sem o controller precisar checar nada disso na mão.
4. Se o botão foi "Finalizar" e a lista de itens está vazia, lança outro erro: não dá pra finalizar um pedido sem nada dentro.
5. Chama `save()` do `PurchaseRepository` (é o que `$this->purchases` é), que salva o pedido no banco.
6. Se o botão foi "Finalizar", chama `finalize()`, também do `PurchaseRepository`, que soma no estoque e fecha o pedido de vez.
7. Qualquer erro nos passos acima cai no mesmo lugar: a tela volta mostrando o aviso, com o que a pessoa já tinha preenchido.
8. Se nada deu errado, volta pra lista de pedidos.

Repare que "Salvar" só passa pelos passos 1, 2, 3, 5 e 8. "Finalizar" passa por todos, inclusive o 4 e o 6 — é por isso que os dois botões chamam o mesmo método: a diferença é só até onde ele vai.

### `itensDoPost()` — de onde vêm os itens

```php
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
```

O formulário manda uma linha por item. Linha sem produto escolhido é ignorada — é a linha em branco que sobra pronta pro próximo clique em "+ Adicionar item", não um erro de quem está preenchendo. Cada linha válida vira um item do pedido, que também lança erro sozinho se a quantidade for inválida.

Os números vêm com vírgula, porque é assim que o teclado brasileiro digita ("2,5"); `decimal()` troca por ponto, que é o formato que o banco entende.

---

## Métodos ``edit()`` e ``update()``

Editar um pedido usa os MESMOS dois métodos que criar — só que com um pedido de verdade no lugar do `null`.

```php
public function edit(): void
{
	$pedido = $this->pedidoDaUrl();

	if ($pedido === null) {
		$this->naoEncontrado();
		return;
	}

	$this->view->render('Purchases/form', $this->dadosDoFormulario(
		titulo: $pedido->isFinalizado() ? 'Pedido #' . $pedido->id : 'Editar pedido',
		pedido: $pedido,
	));
}
```

`edit()` é o `create()` com um pedido real. Busca o pedido pelo `id` da URL (`pedidoDaUrl()`, que consulta o `PurchaseRepository`); se não achar, mostra 404 e para por aí. Se achar, renderiza o MESMO template `Purchases/form`, passando o pedido em vez de `null` — é esse pedido que faz o formulário aparecer preenchido em vez de vazio.

O título muda dependendo do estado do pedido: se já estiver finalizado, vira "Pedido #{id}" (a tela vira consulta, os campos ficam desabilitados no template); senão, "Editar pedido".

```php
public function update(): void
{
	$pedido = $this->pedidoDaUrl();

	if ($pedido === null) {
		$this->naoEncontrado();
		return;
	}

	$this->grava(pedidoExistente: $pedido);
}
```

`update()` é o `store()` com um pedido real. Busca o pedido do mesmo jeito e, se achar, chama o MESMO `grava()` que `store()` usa — só que agora `$pedidoExistente` não é mais `null`. É esse detalhe que muda o comportamento de `grava()` por dentro:

- a trava do passo 2 (pedido já finalizado) passa a valer de verdade;
- `userId` vira o de quem abriu o pedido, não o de quem está editando agora;
- o pedido montado carrega o `id` antigo, e é esse `id` que faz `save()` do `PurchaseRepository` fazer `UPDATE` em vez de `INSERT`.

Fora isso, é o mesmo caminho de sempre: salva, finaliza se for o botão apertado, erro volta pra tela, sucesso volta pra lista.
