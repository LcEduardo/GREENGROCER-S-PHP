## Goals
- [ ] Implementar migrations para versionamento do banco de dados. (Leia: [`Docs/Migrations/`](Docs/Migrations/):)

- [ ] Implementar o padrão TDD (Test Driven Development) no projeto.
- [ ] Implementar o padrão MVC (Model-View-Controller) no projeto.

## Requisitos

**Ambiente:**
- PHP 8.2 ou superior
- Composer

> Recomendo o uso do [Herd](https://herdphp.com/) para gerenciar o ambiente PHP. Ele já engloba o Composer e facilita a instalação de dependências + o servidor. (Projeto foi construído e testado no Herd);

**Rode:** 

```bash
# Clone o repositório do projeto
git clone https://github.com/LcEduardo/GREENGROCER-S-PHP.git

# Instale as dependências do projeto
composer install
```
Caso não queira o ambiente DEV com PHPUnit, rode:

```bash
composer install --no-dev
```

---

## Banco de Dados

### Configuração da conexão

Copie o `.env.example` para `.env` e ajuste. O `.env` 

```bash
cp .env.example .env
```

O projeto suporta **SQLite** (padrão) e **Postgres**. Trocar de banco é trocar uma linha:

```dotenv
DB_DRIVER=sqlite   # ou pgsql
```

Com `sqlite`, o banco é um arquivo em `database/greengrocers.sqlite`, criado sozinho no primeiro uso — não precisa instalar nem subir serviço nenhum. Com `pgsql`, valem as variáveis `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASSWORD`.

O mapeamento das variáveis mora em `config/database.php`, e a `src/Database/Connection.php` é o **único** ponto do projeto que sabe qual banco está rodando. Todo o resto recebe um PDO pronto — na prática, um Repository (ver [Arquitetura](#arquitetura)) — e não faz ideia do driver. Por isso trocar de banco não exige mexer em mais nenhum arquivo.

### Migrations

O schema do banco não é criado na mão: ele é versionado em **migrations**, usando o `doctrine/migrations` (standalone, sem o ORM do Doctrine).

A ideia é que o banco seja reproduzível. Em vez de alguém rodar um `.sql` solto e o banco de cada dev acabar diferente, cada mudança de schema vira um arquivo versionado no Git. Quem clona o projeto roda um comando e chega exatamente no mesmo estado.

**Criar uma migration:**

```bash
php vendor/bin/doctrine-migrations generate
```

Isso gera uma classe em `database/migrations/` com dois métodos: `up()` (aplica a mudança) e `down()` (desfaz).

**Aplicar as migrations pendentes:**

```bash
php vendor/bin/doctrine-migrations migrate
```

**Ver o que já rodou e o que está pendente:**

```bash
php vendor/bin/doctrine-migrations status
```

O Doctrine guarda o histórico numa tabela `doctrine_migration_versions` dentro do próprio banco, então ele sabe quais migrations já foram aplicadas e nunca roda a mesma duas vezes.

**Arquivos de configuração** (na raiz):

| Arquivo | Papel |
|---|---|
| `migrations.php` | Onde ficam as migrations e onde o histórico é gravado |
| `migrations-db.php` | Traduz o `config/database.php` para o formato do DBAL |

O `migrations-db.php` lê a **mesma** configuração que a aplicação e segue o `DB_DRIVER`. Isso é proposital: sem isso, as migrations poderiam rodar num banco diferente do que a app usa — um erro chato de diagnosticar.

> ⚠️ **Escreva migrations que funcionem nos dois bancos.** Como o projeto roda SQLite e Postgres, evite SQL cru específico de um deles (`SERIAL`, `JSONB` e `UUID` não existem no SQLite; o `ALTER TABLE` do SQLite é bem limitado). Prefira o *Schema Builder* do DBAL (`$schema->createTable(...)`), que gera o SQL certo para cada driver.

> ⚠️ **Herd:** rode sempre com o PHP do Herd, que é o runtime real do projeto e tem o driver `pdo_pgsql` habilitado. Se o `php` do seu PATH for outra instalação, chame pelo caminho completo:
> ```powershell
> & "C:\Users\SEU_USUARIO\.config\herd\bin\php.bat" vendor\bin\doctrine-migrations status
> ```

---

## Testes

Os testes usam **PHPUnit** (incluído nas dependências de DEV). Para rodá-los, instale o ambiente completo com `composer install` (sem o `--no-dev`).

> ⚠️ O PHPUnit 13 exige **PHP >= 8.4**. Se você usa o Herd, o PHP dele já atende. Ao rodar por fora, garanta que o `php` no PATH seja o 8.4+.

Rode todos os testes com:

```bash
php vendor/bin/phpunit
```

O arquivo `phpunit.xml` já configura tudo (bootstrap do autoload e a pasta `tests/`), então não é preciso passar argumentos. Saída esperada:

```
OK (1 test, 2 assertions)
```

### Banco de testes

Testes que tocam o banco estendem `tests/Support/DatabaseTestCase.php`, que entrega
um `$this->pdo` pronto. Cada teste recebe um **SQLite `:memory:` novo**, criado no
`setUp()` e destruído no fim — seu banco de desenvolvimento nunca é tocado, e nenhum
teste consegue sujar o próximo.

O schema não é escrito à mão lá: o próprio `doctrine/migrations` roda contra esse
banco em memória. Assim o teste sempre exercita o schema de verdade, e não uma cópia
que envelhece calada a cada migration nova.

> ⚠️ O `:memory:` existe **dentro de uma conexão**. Um segundo PDO apontando para
> `:memory:` abre outro banco, vazio — e o teste falha com zero linhas em vez de dar
> erro. Por isso o repositório precisa receber o mesmo PDO que o `DatabaseTestCase`
> montou, e não um vindo da `Connection`.

Outros comandos úteis:

```bash
# Rodar um arquivo de teste específico
php vendor/bin/phpunit tests/Repository/ProductRepositoryTest.php

# Rodar apenas um método, filtrando pelo nome
php vendor/bin/phpunit --filter test_findActive_nao_traz_produto_inativo
```

---

## Ponto de Entrada e Rotas
Utilizamos o padrão MVC para organizar o projeto. As rotas são definidas no arquivo `public/index.php`. O motivo de existir a pasta public/ é: só ela deve ser a raiz que o navegador enxerga.

Imagine que o document root fosse a raiz do projeto. Aí qualquer pessoa poderia digitar na URL:

- http://greengrocers.test/composer.json → veria suas dependências
- http://greengrocers.test/src/Database/Connection.php → veria seu código-fonte
- http://greengrocers.test/.env → veria senhas de banco (no futuro)

Colocando só o index.php dentro de public/ e apontando o servidor para lá, o navegador não consegue subir para ../src, ../vendor, ../.env. Tudo que é sensível fica fora do alcance. Isso é uma boa prática de qualquer app PHP sério (Laravel, Symfony, todos fazem assim)

Outro ponto: o index.php é o único arquivo que o navegador acessa. Ele é nosso Front Controller. Ele vai receber todas as requisições e decidir qual Controller deve ser chamado, de acordo com a rota (o switch do roteamento). Vantagens:

- Um lugar central para carregar o autoload, iniciar sessão, tratar erros, etc.
- URLs limpas (/produtos em vez de /produtos.php).
- Controle total do fluxo.

> Para quem usar o HERD. Por padrão, o Herd já aponta o document root para public/. Então você só precisa acessar http://greengrocers.test/ e tudo vai funcionar.

---

## Arquitetura

```
HTTP → Controller → Repository → PDO → banco
          │             │
          │         devolve Model[]
          ↓
        View → HTML
```

A dependência anda numa direção só: o Controller conhece o Repository, o Repository conhece o Model. O Model não conhece nenhum dos dois, e o Repository não sabe o que é uma requisição HTTP.

### Controller

Controller não sabe se o banco existe, seu papel é o HTTP. Ler a requisição (`$_GET`, `$_POST`), decidir o que precisa ser feito e pedir para quem sabe fazer, e escolher o template + status code.

Se amanhã os produtos viessem de uma API externa em vez do banco, o Controller ficaria idêntico — quem mudaria era o Repository.

### Repository

É quem gera o SQL e monta o Model a partir do resultado.

- é o **único** lugar do projeto com SQL — é isso que dá a ele um endereço fixo
- **recebe** o PDO pronto, não chama a `Connection` por dentro — é o que torna ele testável com um SQLite `:memory:`
- devolve `Model[]`, não o array cru do PDO — a conversão é trabalho dele
- é onde mora a portabilidade dos dois drivers (ex.: `WHERE active = TRUE`, que funciona nos dois; `active = 1` o Postgres recusa)

### Model

Conhece os dados **e as regras que andam junto com eles**. Não é um saco de campos: se um objeto existe, ele está num estado válido — a validação mora no construtor, não em quem chama. 

---

## Documentação

Explicações mais profundas — o código lido método a método, não só a decisão — ficam fora do README, em [`Docs/Code-Review/`](Docs/Code-Review):

- [`auth.md`](Docs/Code-Review/auth.md) — sessão, `Guard`, login, logout e cadastro.
- [`purchases-controller.md`](Docs/Code-Review/purchases-controller.md) — o fluxo do pedido de compra: o problema N+1 da listagem (e como foi resolvido) e os métodos `create()`/`store()`/`edit()`/`update()`.
- [`db-structure.md`](Docs/Code-Review/db-structure.md) — o schema do banco, tabela a tabela.
- [`cart.md`](Docs/Code-Review/cart.md) — a implementação do carrinho de compras.
- [`stock-movements.md`](Docs/Code-Review/stock-movements.md) — a tela do livro de movimentações de estoque: os joins da consulta, a paginação de 7 em 7 e a migration que renomeou `description` para `historical` (com o plano de rollback).

---

### Trade-offs
Não escolhi transformar Categoria em um objeto, visto que ela inicialmente é usada apenas para filtrar produtos. Não se segue uma outra regra de negócio, então não há necessidade de criar uma classe para ela. Caso no futuro seja necessário, e se tiver regras de negócio, que serão chamadas em algum ponto do projeto, então será necessário criar uma classe para ela.

#### Admin/Products
Optei por mostrar os produtos ativos e inativos na mesma tela, visto que o usuário administrador pode querer ver os produtos inativos para reativá-los. Caso o usuário admin queira alterar o status de um produto, ele acessa a tela de edição do produto e altera o status. Nessa tela, ele conseguirá visualizar a movimentação do produto, para poder tomar uma decisão.

#### Users
Optei por criar usuários administradores na mão. Basicamente, cria um usuário normal pelo cadastro de usuário, e depois altera o campo `admin` para `true` no banco de dados. A tela de cadastro do usuário é por padrão para *clientes*. Inicialmente, só precisa de senha, nome e email. Caso ele vá fazer uma compra ai será solicitado o endereço e o telefone.

#### Purchases
O pedido de compra tem dois estados, e a diferença entre eles é o que a tela toda gira em torno: **em aberto** (`status_id = 0`) ele é uma lista editável, e o estoque não sabe que ele existe; **finalizado** (`status_id = 1`) as entradas já foram lançadas e ele vira documento — não se edita mais.

Optei por **um formulário só** para criar e editar, em vez de endpoints por item (adicionar item, remover item, mudar quantidade). A tela monta a lista no navegador e manda a lista FINAL num POST; o repositório apaga os itens antigos e grava os novos. Assim não existe pedido meio-salvo no servidor enquanto o admin digita, e "adicionar", "remover" e "alterar" são a mesma operação — uma regra de validação só, num lugar só.

Para mais detalhes, a documentação está em [Docs/Code-Review/db-structure.md](Docs/Code-Review/db-structure.md#requisitos-técnicos).

#### Cart
A ideia é que o carrinho não seja uma outra tela, mas sim um modal que fica na parte direita da tela. Onde os intens serão adicionanos pela vitrine. No carrinho teremos as seguintes opções:

- Adicionar item
- Alterar quantidade do item
- Checkout (finalizar compra)

Para mais detalhes, a documentação está em [Docs/Code-Review/cart.md](Docs/Code-Review/cart.md).