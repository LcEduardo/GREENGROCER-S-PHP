---
tags:
  - programming
  - database
atualizado: 2026-08-20
---

## O que é uma Migration?

**Em uma frase:** uma migration é um arquivo de código que descreve **uma mudança no banco de dados** (criar tabela, add coluna, etc.) de um jeito que pode ser aplicado (`up`) ou desfeito (`down`) de forma automática e versionada, em vez de eu digitar SQL na mão em cada ambiente.

É tipo um **"Git" para o schema do banco**: cada alteração vira um "commit" (a migration), com histórico, ordem e possibilidade de reverter.

Existem várias ferramentas de migrations; cada linguagem/framework tem a sua. Se eventualmente eu quiser explorar CakePHP seria legal usar o Phinx, pois é usado por baixo dos panos no framework.

Outro exemplo é o [Doctrine](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.9/reference/introduction.html#introduction), que possui um ecossistema voltado para isso → [[Doctrine Project]] (lá explico DBAL vs ORM vs Migrations, que é bom ler antes/junto desta nota).

---
## Qual problema ela resolve?

Sem elas, precisaríamos escrever código SQL na mão em cada ambiente. Por exemplo, acessar o Dbeaver e escrever:

```sql
CREATE TABLE USERS (...);
```

Qualquer erro no outro ambiente daria erro. E caso tivéssemos que add uma nova coluna, teríamos que fazer isso em cada ambiente.

Com as **Migrations**, temos um controle de versões de cada alteração e criação. Como fazemos com os códigos.

---
## Conceitos centrais

- **Versionamento**: cada migration tem uma ordem (geralmente timestamp) e um estado "aplicada" ou "pendente", guardado numa tabela de controle no próprio banco.
- **Uma migration = uma classe PHP** com dois métodos: `up()` (aplica) e `down()` (desfaz).
- **up/down (ou change)**: toda mudança precisa ser reversível — aplicar e desfazer.
- **Uma migration = uma mudança lógica**: não junte "criar tabela users" + "criar tabela produtos" no mesmo arquivo.
- **Migrations são imutáveis depois de aplicadas em produção**: se precisar corrigir algo, você cria uma _nova_ migration, nunca edita uma antiga que já rodou em algum ambiente compartilhado.

---
## Seed vs Migrations

- Seeds alteram os **dados**;
- Migrations alteram a **carcaça** (estrutura).

---
## 🔁 Checklist — Como aplicar Doctrine Migrations num projeto novo

Roteiro que sigo do zero num projeto novo (é o que fiz no Greengrocer's — ver exemplo prático mais abaixo).

1. **Instalar a dependência**
   ```bash
   composer require doctrine/migrations
   ```
   O `doctrine/dbal` (camada de abstração do banco — ver [[Doctrine Project]]) já vem junto como dependência dele, não precisa instalar separado.

2. **Garantir o autoload do projeto (PSR-4) no `composer.json`** — sem isso o PHP não acha suas classes, inclusive as migrations. Ver [[Composer]] pra entender o porquê e como configurar.

3. **Criar o `migrations.php`** na raiz do projeto — diz ONDE ficam as migrations e ONDE o histórico é gravado (não mexe na conexão em si, isso fica no arquivo do passo 4):
   ```php
   // raiz/migrations.php

   declare(strict_types=1);
   // Configuração do doctrine/migrations (standalone, sem ORM).
   // Só diz ONDE ficam as migrations e ONDE o histórico é gravado — a conexão
   // em si mora em migrations-db.php.
   return [
       'table_storage' => [
           'table_name' => 'doctrine_migration_versions',
       ],
       'migrations_paths' => [
           'User\Greengrocers\Migrations' => __DIR__ . '/database/migrations',
       ],
       // Uma migration que falha no meio deixaria o banco pela metade. SQLite e
       // Postgres aceitam DDL dentro de transação, então dá para exigir tudo-ou-nada.
       'all_or_nothing' => true,
       'transactional'  => true,
   ];
   ```
   > No projeto novo, troque `User\Greengrocers\Migrations` pelo namespace do projeto e `database/migrations` pelo caminho onde as migrations vão morar.

4. **Criar o `migrations-db.php`** — só a conexão com o banco:
   ```php
   //migrations-db.php
   return [
       'dbname' => 'migrations_docs_example',
       'user' => 'root',
       'password' => '',
       'host' => 'localhost',
       'driver' => 'pdo_mysql',
   ];
   ```

5. **Criar a pasta apontada em `migrations_paths`** (ex: `database/migrations/`) — é onde os arquivos de migration vão morar.

6. **Gerar uma migration nova**
   ```bash
   vendor/bin/doctrine-migrations migrations:generate
   ```
   Isso cria um arquivo com o esqueleto de `up()`/`down()` já dentro da pasta configurada.

7. **Escrever o `up()` e o `down()`** da migration — ver exemplo prático logo abaixo de como organizei isso no Greengrocer's.

8. **Rodar a migration (aplicar)**
   ```bash
   vendor/bin/doctrine-migrations migrations:migrate
   ```

9. **Conferir o que já rodou**
   ```bash
   vendor/bin/doctrine-migrations migrations:status
   ```

10. **Reverter, se precisar**
    ```bash
    vendor/bin/doctrine-migrations migrations:execute --down <versão>
    ```
    ou `migrations:migrate` apontando para uma versão anterior.

> Os nomes exatos dos comandos podem variar um pouco conforme a versão instalada — se der erro de comando não encontrado, `vendor/bin/doctrine-migrations list` mostra todos os comandos disponíveis.

---
## Exemplo prático — UP() (projeto Greengrocer's)

Esse é o `up()` real que uso no Greengrocer's: constrói onze tabelas (`createUsers`, `createCategories`, ... `createStockMovements`) **numa ordem que respeita as chaves estrangeiras** — uma tabela só é criada depois que todas as tabelas que ela referencia já existem (por exemplo, `products` precisa de `categories` primeiro, `sale_items` precisa tanto de `sales` quanto de `products` primeiro).

Serve de referência de como organizar uma migration grande em métodos privados, um por tabela.

```php
public function up(Schema $schema): void
{
	$this->createUsers($schema);
	$this->createCategories($schema);
    $this->createProducts($schema);
}

/** Clientes e administradores na mesma tabela, separados pela coluna `type`. */
private function createUsers(Schema $schema): void
{
	$table = $schema->createTable('users');
	$table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
	$table->addColumn('name', Types::STRING, ['length' => 50]);
	$table->addColumn('email', Types::STRING, ['length' => 255]);
	
	// Argon2id gera hash de ~97 caracteres. O VARCHAR(60) que se vê por aí é
	// dimensionado para bcrypt e truncaria o hash — quebrando o login em silêncio.
	$table->addColumn('password', Types::STRING, ['length' => 255]);

	// 0 = admin, 1 = cliente. O CHECK impede que um terceiro valor entre por engano.

	$table->addColumn('type', Types::SMALLINT, [
		'columnDefinition' => 'SMALLINT NOT NULL DEFAULT 1 CHECK (type IN (0, 1))',
	]);

	$table->addColumn('address', Types::STRING, ['length' => 255, 'notnull' => false]);
	$table->addColumn('phone', Types::STRING, ['length' => 20, 'notnull' => false]);
	$table->addColumn('created_at', Types::DATETIME_IMMUTABLE);

	$table->setPrimaryKey(['id']);

	// O e-mail identifica o usuário no login: duplicata tornaria o login ambíguo.

	$table->addUniqueIndex(['email'], 'uniq_users_email');
}
```

---
## Exemplo prático — DOWN()

Desfaz isso, então a migração pode ser revertida com `migrations:execute --down` ou `migrations:migrate` para uma versão anterior. Ela deleta as mesmas onze tabelas na **ordem exatamente inversa**: `stock_movements` primeiro, `users` por último.

Isso é obrigatório, não apenas estilístico — você não pode `dropTable('categories')` enquanto `products.category_id` ainda tem uma chave estrangeira apontando para ela; o banco de dados rejeitaria com uma violação de restrição. Então `down()` remove as tabelas que dependem de outras antes de deletar aquelas de que dependem — a imagem espelhada de como `up()` as construiu.

```php
public function down(Schema $schema): void
{
	// Ordem inversa da criação: uma tabela não pode cair antes de quem a referencia.
	$schema->dropTable('stock_movements');
	$schema->dropTable('sale_items');
	$schema->dropTable('sales');
	$schema->dropTable('cart');
	$schema->dropTable('purchase_items');
	$schema->dropTable('purchases');
	$schema->dropTable('products');
	$schema->dropTable('payment_methods');
	$schema->dropTable('suppliers');
	$schema->dropTable('categories');
	$schema->dropTable('users');
}
```

---
## ⚠️ Erros comuns / coisas pra prestar atenção

- **Ordem errada no `down()`**: dropar uma tabela antes de quem a referencia gera erro de violação de FK. A regra é sempre a ordem inversa da criação.
- **`down()` ausente ou incorreta**: a migração ainda assim é aplicada normalmente para frente (`up()` roda igual), mas reverter gera erro de FK no meio da deleção ou (se o `down()` estiver vazio) deixa o esquema preso sem volta, exceto por correção manual.
- **Editar uma migration que já rodou em produção/outro ambiente**: nunca faça isso — crie uma nova migration para corrigir.
- **Autoload desatualizado**: se o Doctrine não encontrar a classe da migration, rodar `composer dump-autoload` costuma resolver (ver [[Composer]]).

---
## 🧩 Alterando uma tabela que já tem dados

Até agora todo exemplo daqui é criar tabela do zero — banco vazio, sem risco. O primeiro susto de verdade acontece quando você precisa alterar uma tabela que **já tem linhas**.

Exemplo clássico: adicionar uma coluna `NOT NULL` numa tabela que já existe e já tem dados.

```php
// Isso quebra se a tabela já tiver linhas — o banco não sabe
// que valor colocar nas linhas que já existem.
$table->addColumn('cpf', Types::STRING, ['length' => 14, 'notnull' => true]);
```

O banco não tem como preencher esse valor sozinho nas linhas antigas, então a migration falha na hora de rodar.

**Duas saídas comuns:**

1. **Dar um valor padrão** (`'default' => 'algum-valor'`) — resolve na hora, mas pode deixar dado "falso" nas linhas antigas.
2. **Duas etapas, mais correto:**
   - Migration 1: cria a coluna como `'notnull' => false` (aceita nulo por enquanto).
   - Preenche/corrige o valor nas linhas antigas (um `UPDATE`, dentro da própria migration ou uma seed).
   - Migration 2: torna a coluna `NOT NULL` de verdade, já que agora todo mundo tem valor.

Isso é o que costuma ser chamado de **"data migration"** — quando a mudança mexe tanto na estrutura quanto nos dados que já existem, não só na carcaça.

---
## 🔮 Próximos passos (o que ainda não sei)

Coisas que ficaram de fora por enquanto — não porque não importam, mas porque ainda não bati de frente com elas. Quando aprender cada uma, volto aqui pra marcar e linkar a nota nova.

- [ ] **CI/CD** — integrar o pipeline para rodar `migrations:migrate` sozinho antes (ou depois) de subir a versão nova do sistema.
- [ ] **`migrations:diff`** — o Doctrine consegue comparar o schema do banco com as entidades e gerar a migration sozinho. Só serve se eu adotar o Doctrine ORM (hoje uso só o DBAL, standalone). -- Alura tem uma aula sobre::[Doctrine: Migrations, relatórios e performance](https://cursos.alura.com.br/course/doctrine-migrations-relatorios-performance)
- [ ] **Migrations em equipe** — como resolver quando dois devs criam uma migration ao mesmo tempo (conflito de ordem/timestamp).
- [ ] **Squash de migrations antigas** — comprimir um histórico grande numa migration só, pra acelerar o setup de um ambiente novo.

---
## Links

- https://www.dbvis.com/thetable/introduction-to-database-migration-a-beginners-guide/
- [Doctrine: Migrations, relatórios e performance](https://cursos.alura.com.br/course/doctrine-migrations-relatorios-performance)
- [Doctrine](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.9/reference/introduction.html#introduction)