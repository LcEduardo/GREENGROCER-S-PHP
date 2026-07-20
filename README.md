## Requisitos

**Ambiente:**
- PHP 8.2 ou superior
- Composer

> Recomendo o uso do [Herd](https://herdphp.com/) para gerenciar o ambiente PHP. Ele já engloba o Composer e facilita a instalação de dependências + o servidor. (Projeto foi construído e testado no Herd);

**Rode:** 

```bash
# Clone o repositório do projeto
git clone url_do_repositorio.git

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

Copie o `.env.example` para `.env` e ajuste. O `.env` **não vai para o Git** — é onde moram os valores reais.

```bash
cp .env.example .env
```

O projeto suporta **SQLite** (padrão) e **Postgres**. Trocar de banco é trocar uma linha:

```dotenv
DB_DRIVER=sqlite   # ou pgsql
```

Com `sqlite`, o banco é um arquivo em `database/greengrocers.sqlite`, criado sozinho no primeiro uso — não precisa instalar nem subir serviço nenhum. Com `pgsql`, valem as variáveis `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASSWORD`.

O mapeamento das variáveis mora em `config/database.php`, e a `src/Database/Connection.php` é o **único** ponto do projeto que sabe qual banco está rodando. Todo o resto recebe um PDO pronto e não faz ideia do driver — por isso trocar de banco não exige mexer em mais nenhum arquivo.

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
OK (3 tests, 4 assertions)
```

Outros comandos úteis:

```bash
# Rodar um arquivo de teste específico
php vendor/bin/phpunit tests/Model/UserTest.php

# Rodar apenas um método, filtrando pelo nome
php vendor/bin/phpunit --filter test_a_senha_original_confere_contra_o_hash
```

---

## Ponto de Entrada e Rotas
Utilizamos o padrão MVC para organizar o projeto. As rotas são definidas no arquivo `public/index.php`. O motivo de existir a pasta public/ é: só ela deve ser a raiz que o navegador enxerga.

Imagine que o document root fosse a raiz do projeto. Aí qualquer pessoa poderia digitar na URL:

- http://greengrocers.test/composer.json → veria suas dependências
- http://greengrocers.test/src/Model/User.php → veria seu código-fonte
- http://greengrocers.test/.env → veria senhas de banco (no futuro)

Colocando só o index.php dentro de public/ e apontando o servidor para lá, o navegador não consegue subir para ../src, ../vendor, ../.env. Tudo que é sensível fica fora do alcance. Isso é uma boa prática de qualquer app PHP sério (Laravel, Symfony, todos fazem assim)

Outro ponto: o index.php é o único arquivo que o navegador acessa. Ele é nosso Front Controller. Ele vai receber todas as requisições e decidir qual Controller deve ser chamado, de acordo com a rota (o switch do roteamento). Vantagens:

- Um lugar central para carregar o autoload, iniciar sessão, tratar erros, etc.
- URLs limpas (/users em vez de /users.php).
- Controle total do fluxo.

> Para quem usar o HERD. Por padrão, o Herd já aponta o document root para public/. Então você só precisa acessar http://greengrocers.test/ e tudo vai funcionar.
