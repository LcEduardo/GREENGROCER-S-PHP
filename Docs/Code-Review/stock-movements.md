# Movimentações de estoque

## Contexto

O sistema já guardava, desde o começo, um **livro de auditoria do estoque**: a tabela `stock_movements`. Toda vez que um pedido de compra é finalizado, uma linha nova é escrita ali dizendo qual produto entrou, quanto entrou e por causa de quê.

O problema é que esse livro **não tinha tela**. Para saber "quando entrou tomate?" ou "quanto de cebola saiu esse mês?", só abrindo o banco na mão.

Esta feature dá uma tela a ele: `/admin/stock-movements`.

O acesso fica ao lado do botão **+ Novo produto**, na tela de produtos:

```php
// templates/Products/admin.php:12-15
<div class="gg-page__actions">
    <a class="gg-btn gg-btn--ghost" href="/admin/stock-movements">Movimentações</a>
    <a class="gg-btn gg-btn--primary" href="/admin/products/create">+ Novo produto</a>
</div>
```

- O link fica ali porque é sobre os **mesmos produtos**, vistos pelo outro lado: na tela de produtos você vê o saldo de **agora**; na de movimentações, **como ele chegou nesse número**.
- Ele usa `gg-btn--ghost` (botão discreto, sem preenchimento) e não `gg-btn--primary` (o verde cheio) porque a ação principal daquela tela continua sendo cadastrar produto. Dois botões verdes brigariam pela atenção.
- Os dois links foram embrulhados numa `<div class="gg-page__actions">`. Sem essa div, o cabeçalho — que separa "título à esquerda, ações à direita" — trataria os dois botões como elementos soltos e os espalharia pela linha inteira, um em cada canto.

---

## A tela

É uma tela de **consulta pura**: não tem botão de criar, editar nem apagar. E isso é de propósito — movimentação não se digita. Ela **nasce** de um pedido de compra finalizado. Se desse para criar uma movimentação na mão, o livro de auditoria e o estoque de verdade passariam a ser duas verdades diferentes.

Os controles são dois: a busca por nome de produto e o rodapé de paginação, que mostra 7 movimentações por vez.

As colunas são:

| Coluna | De onde vem |
|---|---|
| Dt. Moved | `moved_at` — quando a movimentação aconteceu |
| Supplier | O fornecedor, **só quando** a movimentação veio de uma compra |
| Produto | O nome do produto |
| Tp. Movimento | `reference_type` — a origem (Compra / Venda / Ajuste manual) |
| Qtd | `quantity` — a quantidade movimentada, sem sinal nem seta |
| Histórico | `historical` — a frase pronta do lançamento |

---

## Rota `/admin/stock-movements`

> *"Se quem está pedindo é admin, monta o repositório e chama a tela. Se não é, manda embora."*

```php
// public/index.php:135-143
case '/admin/stock-movements':
    if (!Guard::isAdmin()) {
        header('Location: ' . (Guard::isLoggedIn() ? '/' : '/login'));
        break;
    }

    $repo = new StockMovementRepository(Connection::get());
    (new StockMovementsController($repo))->index();
    break;
```

`if (!Guard::isAdmin())`
- É a **mesma trava** das outras rotas `/admin/*`. O livro de auditoria mostra custo de fornecedor e histórico de entrada — não é informação de cliente.
- O redirecionamento é esperto: quem **está logado** mas não é admin volta para a vitrine (`/`); quem **não está logado** vai para o `/login`. Mandar um deslogado para a vitrine faria ele achar que não tem permissão, quando na verdade ele só precisava entrar.

`$repo = new StockMovementRepository(Connection::get());`
- Monta o objeto que sabe conversar com a tabela `stock_movements` e entrega a conexão do banco pronta para ele.
- O controller nunca monta a própria conexão: ele recebe o repositório já pronto. É o mesmo desenho do resto do projeto — assim o controller não sabe que existe um banco embaixo.

---

## Action `index()`

> *"Descobre o que estão buscando e em que página a pessoa está, e pede ao banco só aquela fatia."*

```php
// src/Controller/StockMovementsController.php:20-41
public function index(): void
{
    $searchProduct = trim((string) filter_input(INPUT_GET, 'q')) ?: null;

    $total = $this->movements->countAll($searchProduct);
    $totalPaginas = StockMovementRepository::totalDePaginas($total);
    $pagina = min(
        max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT)),
        $totalPaginas,
    );

    $movimentacoes = $this->movements->findPage(
        $searchProduct,
        StockMovementRepository::POR_PAGINA,
        ($pagina - 1) * StockMovementRepository::POR_PAGINA,
    );
```

`$searchProduct = trim((string) filter_input(INPUT_GET, 'q')) ?: null;`
- Lê o `?q=` da URL — o que a pessoa digitou na busca.
- `trim(...)` tira os espaços das pontas. Se alguém digitar só espaços e apertar buscar, sobra texto vazio.
- O `?: null` transforma esse texto vazio em `null`, que mais adiante significa "sem filtro, traz tudo". Sem isso, buscar espaço em branco procuraria por um produto de nome vazio e a tela viria vazia sem motivo.

`$total = $this->movements->countAll($searchProduct);`
- Quantas movimentações existem **com aquele filtro**. Esse número aparece duas vezes na tela: no "de 74 movimentações" do cabeçalho e, dividido, na quantidade de páginas do rodapé.
- É lido **antes** de decidir qual página mostrar, porque é ele que limita o pedido.

`$totalPaginas = StockMovementRepository::totalDePaginas($total);`
- A divisão do total pelo tamanho da página, arredondando para cima — a conta está no repositório, junto da constante que ela usa. Ver a seção seguinte.

`$pagina = min(max(1, ...), $totalPaginas);`
- O `?page=` vem da URL, e URL é texto que qualquer um edita. Este par de funções fecha os dois lados do intervalo:

| O que vem na URL | Vira | Por quê |
|---|---|---|
| `?page=abc` | 1 | `FILTER_VALIDATE_INT` devolve `null`, que vira `0`, e o `max(1, …)` sobe para 1 |
| `?page=-4` ou `?page=0` | 1 | Página zero não existe; um offset negativo faria o banco reclamar |
| `?page=999` (numa lista de 3) | 3 | O `min(…, $totalPaginas)` recorta para a última **que existe** |

- Recortar para a última página em vez de mostrar uma tabela vazia importa: quem chega em `?page=999` por um link velho vê o fim da lista, e não uma tela em branco que parece defeito.

`($pagina - 1) * StockMovementRepository::POR_PAGINA`
- É a tradução de **número de página** para **quantas linhas pular**, que é o que o banco entende. A página 1 pula 0 linhas; a 2 pula 7; a 3 pula 14.
- Só o controller fala em "página"; o repositório continua falando em `LIMIT`/`OFFSET`, que é a linguagem do banco.

---

## Sete por página, num lugar só

O número 7 aparece em **dois** lugares que precisam concordar: o `LIMIT` da consulta e a divisão que numera o rodapé. Se discordassem, o rodapé ofereceria uma página que a consulta devolveria vazia. Por isso ele mora numa constante:

```php
// src/Repository/StockMovementRepository.php:13
public const POR_PAGINA = 7;
```

E a divisão mora ao lado dela:

```php
// src/Repository/StockMovementRepository.php:113-116
public static function totalDePaginas(int $total): int
{
    return max(1, (int) ceil($total / self::POR_PAGINA));
}
```

- `ceil` arredonda **para cima**: 8 movimentações em páginas de 7 são **duas** páginas, não uma e um resto que ninguém alcança.
- `max(1, ...)` impede o zero. Lista vazia continua sendo "página 1 de 1" — com zero páginas, a página atual não caberia dentro do próprio total.
- Ela recebe o total **pronto** em vez de consultar o banco de novo. O `countAll()` já foi chamado uma vez para escrever o cabeçalho; repetir a consulta seria uma ida a mais ao banco e uma chance a mais de os dois números discordarem.
- É `static` porque não toca no banco: é aritmética em cima de um número que já veio de lá. Mora no repositório só porque a constante que ela divide mora lá.

| Total | Páginas |
|---|---|
| 0 | 1 (vazia) |
| 7 | 1 |
| 8 | 2 |
| 74 | 11 |

## O repositório: uma consulta, três tabelas

> *"Traz uma página do livro, da movimentação mais nova para a mais antiga, já com o nome do produto e o nome do fornecedor colados."*

A tela precisa mostrar o **nome** do produto e do fornecedor, mas a tabela `stock_movements` só guarda números de identificação. O caminho ingênuo seria buscar as 7 movimentações da página e, para cada uma, ir ao banco perguntar "qual é o nome do produto 12?" — o problema N+1 já explicado em [purchases-controller.md](purchases-controller.md).

Aqui tudo vem numa consulta só:

```php
// src/Repository/StockMovementRepository.php:54-61
$sql = 'SELECT m.id, m.product_id, m.type, m.quantity, m.reference_type,'
     . ' m.reference_id, m.moved_at, m.historical,'
     . ' p.name AS product_name,'
     . ' s.name AS supplier_name'
     . ' FROM stock_movements m'
     . ' INNER JOIN products p ON p.id = m.product_id'
     . " LEFT JOIN purchases c ON m.reference_type = 'purchase' AND c.id = m.reference_id"
     . ' LEFT JOIN suppliers s ON s.id = c.supplier_id'
```

`INNER JOIN products` (produto)
- "Junte cada movimentação com o produto dela." `INNER` significa: se não achar o produto, a movimentação **não aparece**.
- Parece arriscado, mas é o correto aqui: a chave estrangeira do produto é `RESTRICT`, ou seja, o banco **proíbe** apagar um produto que tenha movimentação. Movimentação sem produto não existe — e se um dia existir, é defeito, não uma linha para esconder atrás de um join permissivo.

`LEFT JOIN purchases` (o pedido de compra)
- `LEFT` significa: "junte se houver; se não houver, tudo bem, a linha continua aparecendo". É o oposto do anterior, e também está certo — venda e ajuste manual **não têm** pedido de compra do outro lado, e mesmo assim precisam aparecer na lista.

**A parte mais delicada da consulta inteira** é a condição `m.reference_type = 'purchase'` estar **dentro** do `ON`:

- A coluna `reference_id` é uma **referência polimórfica**: dependendo do `reference_type`, aquele número aponta para tabelas diferentes. Um `reference_id = 12` numa linha de compra é o pedido 12; numa linha de venda, é a venda 12.
- Sem esse recorte, o banco juntaria a **venda 12** com o **pedido de compra 12** — e a tela mostraria, na linha de uma venda, o nome do fornecedor de um pedido que não tem nada a ver com ela. Um erro silencioso: o dado apareceria bonito e estaria errado.
- E ela precisa estar no `ON`, e **não** num `WHERE`. Se fosse no `WHERE`, o filtro seria aplicado depois da junção e descartaria toda linha sem fornecedor — o `LEFT JOIN` viraria um `INNER JOIN` na prática, e vendas e ajustes sumiriam da lista.

---

## A ordem é o contrato da paginação

```php
// src/Repository/StockMovementRepository.php:62-64
     . $filtro
     . ' ORDER BY m.moved_at DESC, m.id DESC'
     . " LIMIT {$limite} OFFSET {$deslocamento}";
```

`ORDER BY m.moved_at DESC` — o pedido da tela: mais recente em cima.

`, m.id DESC` — este segundo critério **não é enfeite**, é o que faz a paginação funcionar.

- Quando um pedido de compra é finalizado, **todos os seus itens** são gravados com o mesmo `moved_at` (é um único horário, criado uma vez só no `finalize()`). Um pedido de 10 itens gera 10 movimentações no mesmo instante.
- Com o horário empatado e nada para desempatar, o banco fica **livre** para devolver essas linhas em qualquer ordem — e ele pode devolver numa ordem na primeira consulta e noutra na segunda.
- Numa lista paginada, isso é desastre: a página 1 pede "as 7 primeiras" e a página 2 pede "da 8 em diante". Se a ordem mudou entre um clique e outro, uma linha que estava na posição 7 escorrega para a 8 e **aparece duas vezes**; outra pula da 8 para a 7 e **nunca aparece** — e ninguém desconfia, porque as duas páginas parecem certas separadamente.
- Como o `id` nunca se repete, acrescentá-lo torna a ordem **total** — sempre a mesma, em toda consulta.

**Sobre o `LIMIT` e `OFFSET` colados no texto do SQL:** normalmente valores nunca são colados assim (é a porta de entrada de SQL injection). A exceção aqui é segura porque as duas variáveis passaram por `(int)` e `max()` antes — depois de uma conversão para inteiro, não existe mais texto, só número. Foi preciso fazer assim porque o PHP manda esses valores como texto (`'7'`) quando são parâmetros, e o PostgreSQL recusa texto onde espera número.

---

## Por que a busca usa `LOWER()` e não `ILIKE`

```php
// src/Repository/StockMovementRepository.php:138-141
return [
    ' WHERE LOWER(p.name) LIKE LOWER(:search)',
    ['search' => '%' . $searchProduct . '%'],
];
```

- `LIKE` com `%` dos dois lados quer dizer "contém". Quem digita `tom` acha `Tomate` e `Tomate italiano`.
- `LOWER()` nos **dois lados** derruba tudo para minúsculo antes de comparar, então `TOMATE`, `tomate` e `ToMaTe` dão no mesmo.
- O PostgreSQL tem um comando pronto para isso (`ILIKE`), que seria mais curto — mas o SQLite, usado nos testes, **não tem**. Usar `ILIKE` faria os testes rodarem contra um comportamento diferente do de produção, que é justamente o que o projeto evita ao rodar os testes pela mesma migration de produção.

---

## O rodapé de paginação

O rodapé é feito de **links de verdade**, um endereço por página:

```php
// templates/StockMovements/index.php:25-32
$urlDaPagina = static function (int $numero) use ($searchProduct): string {
    $parametros = array_filter(
        ['q' => $searchProduct, 'page' => $numero > 1 ? $numero : null],
        static fn ($valor) => $valor !== null,
    );

    return '/admin/stock-movements' . ($parametros === [] ? '' : '?' . http_build_query($parametros));
};
```

- **A busca viaja junto no link.** Sem o `q`, clicar em "2" durante uma busca por tomate devolveria a página 2 da lista **inteira** — e a tela mostraria movimentações de outro produto embaixo das filtradas.
- **A página 1 não escreve `page` na URL.** `?q=tomate` é o endereço limpo do primeiro resultado; `?q=tomate&page=1` seria a mesma tela com um endereço mais feio.
- `http_build_query` é quem escapa os valores: um produto chamado `Alface & cia` vira `q=Alface+%26+cia` em vez de quebrar a URL.

**Quais números aparecem:** sempre a primeira, a última e a vizinhança da atual, com `…` nos buracos.

```php
// templates/StockMovements/index.php:39-44
$numerosDaPaginacao = array_values(array_filter(
    range(1, $totalPaginas),
    static fn (int $numero) => $numero === 1
        || $numero === $totalPaginas
        || abs($numero - $pagina) <= 1,
));
```

Numerar todas as páginas funciona enquanto são cinco; com 40, o rodapé viraria uma parede de números. Mostrar as pontas é o que dá **noção de tamanho**: "estou na 3 de 40" diz de cara o tamanho do livro.

**A página atual não é link.** Ela sai como `<span>`, e "Anterior" na primeira página (como "Próxima" na última) também:

```php
// templates/StockMovements/index.php:129-132
<?php else: ?>
    <!-- Desabilitado vira <span>: um link que não leva a lugar
         nenhum ainda seria clicável e focável pelo teclado. -->
    <span class="gg-pager__step gg-pager__step--off">← Anterior</span>
<?php endif; ?>
```

- Um `<a>` "apagado" no CSS continua sendo um link: recebe foco pelo Tab e navega ao Enter. O `<span>` não — o desabilitado é de verdade, e não só na aparência.
- Ele continua **ocupando o lugar** em vez de sumir, para o rodapé não pular de posição a cada página virada.

**O formulário de busca não leva `page` escondido.** Buscar é começar de novo: uma busca nova abre na página 1, e não na 4ª página da busca anterior.

**Sem JavaScript, a tela é a mesma.** Não há JS nenhum nesta tela — busca e paginação são um formulário GET e links comuns, que é o que o navegador já sabe fazer sozinho. Como efeito colateral da troca, o `View::partial()`, criado para responder o pedaço de HTML de cada rolagem, ficou **sem uso**.

# A migration: `description` → `historical`

## Por que renomear

A coluna sempre guardou a **frase pronta do lançamento** — "Entrada de produto pelo pedido 12". O nome `description` sugeria outra coisa: um texto livre, descritivo do produto. O que ela guarda é o **histórico** daquela movimentação: o registro de por que aquela linha existe.

Como a tela nova mostra essa coluna com o cabeçalho "Histórico", o nome no banco e o nome na tela precisavam concordar.

## Levantamento: onde `description` era usado

Antes de mexer, foi feita uma varredura no projeto inteiro (fora `vendor/`) atrás de tudo que citava a coluna. O resultado:

| Onde | O que era | O que foi feito |
|---|---|---|
| `src/Repository/PurchaseRepository.php:282-291` | O `INSERT` que grava a movimentação ao finalizar um pedido | ✅ Atualizado para `historical` |
| `tests/Repository/PurchaseRepositoryTest.php:249` | O teste que confere a frase gravada | ✅ Atualizado para `historical` |
| `Docs/Code-Review/db-structure.md:158` | A documentação do schema | ✅ Atualizada, com nota do nome antigo |
| `database/migrations/Version20260815120500.php` | A migration que **criou** a coluna | ⛔ Não tocada — de propósito |
| `Docs/Prompt/purchases-prompt.md` | O prompt arquivado da feature anterior | ⛔ Não tocado — registro histórico |

**Por que as migrations antigas não foram alteradas:** uma migration é o registro do que aconteceu naquela data. Se a `Version20260815120500` fosse editada para dizer `historical`, ela passaria a mentir — e num banco que ainda não rodou a renomeação, ela criaria a coluna já com o nome novo, fazendo a renomeação seguinte falhar por não encontrar `description`.

Não havia **nenhum** outro ponto: nem serializer, nem template, nem JavaScript. A coluna era escrita num lugar só e nunca tinha sido lida por tela nenhuma — porque tela nenhuma existia.

## O comando: SQL cru, e por quê

```php
// database/migrations/Version20260821120000.php:47-55
public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE stock_movements RENAME COLUMN description TO historical');
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE stock_movements RENAME COLUMN historical TO description');
}
```

⚠️ Esta é a **única migration do projeto que não usa o Schema Builder**, e a razão foi medida, não suposta.

O jeito idiomático seria `$table->renameColumn('description', 'historical')`. Mas esse método **não renomeia a coluna no lugar**: no SQLite, ele **reconstrói a tabela inteira** (cria uma nova, copia os dados, apaga a velha) a partir do que a ferramenta consegue ler do schema. E o que ela não consegue ler **some no caminho**.

Testando os dois jeitos na tabela real, o resultado foi este:

| Trava do schema | Com `renameColumn()` | Com `ALTER TABLE ... RENAME COLUMN` |
|---|---|---|
| `CHECK` de `type` (`in`/`out`/`adjustment`) | ❌ perdido | ✅ mantido |
| `CHECK` de `reference_type` | ❌ perdido | ✅ mantido |
| `ON DELETE RESTRICT` do produto | ❌ perdido | ✅ mantido |

Sairia uma tabela com o nome certo e **sem nenhuma das travas** — em silêncio, sem erro nenhum. O `CHECK` é o que impede um quarto valor de entrar na coluna; o `RESTRICT` é o que impede a auditoria de estoque de evaporar junto com o produto apagado.

O `ALTER TABLE ... RENAME COLUMN` é **in-place**: renomeia e não encosta em mais nada. Existe no SQLite desde a versão 3.25 e no PostgreSQL desde a 9.2 — os dois bancos que este projeto roda.

Um ganho de tabela: como nada é copiado, **nenhum dado se perde**. Um `dropColumn()` + `addColumn()` daria o mesmo resultado ao schema e apagaria todo o histórico já escrito.

---

## Plano de rollback

A ida não transforma dado nenhum — só o nome muda. Por isso a volta é o mesmo comando ao contrário, sem nada para reconstruir.

**⚠️ A ordem importa.** O banco e o código nomeiam a mesma coluna; um sem o outro derruba a gravação de movimentação ao finalizar um pedido de compra.

**1. Reverter o código primeiro** (ou junto, no mesmo deploy):

- `src/Repository/StockMovementRepository.php` — a consulta que lê `m.historical`
- `src/Repository/PurchaseRepository.php:282-291` — o `INSERT` que grava a coluna
- `src/Model/StockMovement.php` — o campo `historical`
- `templates/StockMovements/index.php` — a célula que exibe

**2. Reverter o banco:**

```
& "C:\Users\USER\.config\herd\bin\php.bat" vendor\bin\doctrine-migrations migrations:execute --down "User\Greengrocers\Migrations\Version20260821120000"
```

**3. Conferir:**

```
& "C:\Users\USER\.config\herd\bin\php.bat" vendor\bin\phpunit
```

**Se só o banco for revertido e o código não**, o sintoma é claro e imediato: finalizar um pedido de compra falha com `table stock_movements has no column named historical`. Como o `finalize()` roda inteiro dentro de uma transação, a falha **desfaz tudo** — nenhum estoque entra pela metade. O pedido continua aberto e pode ser finalizado de novo depois da correção.

---

# Testes

```
& "C:\Users\USER\.config\herd\bin\php.bat" vendor\bin\phpunit tests\Repository\StockMovementRepositoryTest.php
```

Os testes de contagem comparam o que a tela mostra contra um `COUNT(*)` feito direto no banco, em vez de contra um número escrito à mão no teste. A diferença importa: o número escrito à mão envelhece junto com o teste, enquanto o `COUNT(*)` continua verdadeiro no dia em que a consulta ganhar mais um join.

| Teste | O que ele defende |
|---|---|
| `test_total_sem_filtro_bate_com_a_contagem_no_banco` | Percorre **todas** as páginas somando as linhas: repetir uma infla o total, pular uma o encolhe |
| `test_total_com_filtro_bate_com_a_contagem_daquele_produto` | O total filtrado conta o produto, não a tabela inteira |
| `test_pagina_traz_no_maximo_sete_movimentacoes` | O `LIMIT` da consulta é o mesmo 7 que o rodapé usa para dividir |
| `test_ultima_pagina_traz_o_que_sobrou` | A última página vem incompleta — e o resto não pode sumir |
| `test_total_de_paginas_arredonda_para_cima` | 7 movimentações são 1 página; a oitava abre a segunda |
| `test_total_de_paginas_e_um_quando_nao_ha_movimentacao` | Lista vazia ainda é "página 1 de 1", nunca "1 de 0" |
| `test_total_de_paginas_respeita_o_filtro` | Filtrou, a conta de páginas muda junto — sem links para páginas vazias |
| `test_paginas_nao_repetem_nem_pulam_movimentacao` | A página 2 começa exatamente onde a 1 parou |
| `test_lista_vem_ordenada_por_moved_at_decrescente` | As linhas são inseridas fora de ordem de propósito — sem `ORDER BY`, o teste pega |
| `test_movimentacoes_no_mesmo_instante_tem_ordem_estavel` | O desempate por `id`: três movimentações no mesmo horário, paginadas de 2 em 2, sem repetir |
| `test_lista_filtrada_traz_so_movimentacoes_daquele_produto` | O filtro não deixa passar movimentação de outro produto |
| `test_filtro_aceita_parte_do_nome_e_ignora_maiusculas` | `tom`, `TOMATE` e `tOmAtE iTaLiAnO` acham a mesma coisa |
| `test_compra_aparece_como_entrada_e_venda_como_saida` | Compra vem como `in`, venda como `out` — a direção que a classe da linha usa |
| `test_fornecedor_so_aparece_em_movimentacao_de_compra` | Venda e ajuste manual ficam em branco, e não com um fornecedor qualquer |

---
