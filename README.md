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

### Convenções (padrão CakePHP)

O projeto segue as convenções do **CakePHP**, para facilitar a futura migração:

| Item | Convenção | Exemplo |
|---|---|---|
| Controller | Plural, PascalCase, sufixo `Controller` | `UsersController` |
| `index()` | Lista os registros | `GET /users` |
| `add()` | Formulário (GET) + criação (POST) | `/users/add` |
| `view($id)` | Mostra um registro | `GET /users/view/1` |
| Templates | `templates/{Plural}/{acao}.php` | `templates/Users/add.php` |
| Layout | `templates/layout/default.php` | — |

### Rotas ativas

| Rota | Ação | O que faz |
|---|---|---|
| `GET /` | — | Redireciona para `/users/add` |
| `GET /users` | `index()` | Lista os usuários |
| `GET /users/add` | `add()` | Mostra o formulário de cadastro |
| `POST /users/add` | `add()` | Cria o usuário com os dados enviados |

### Exemplo de fluxo — cadastro de usuário

1. O usuário acessa `http://greengrocers.test/` → o `index.php` redireciona para `/users/add`.
2. `GET /users/add` → o `UsersController::add()` renderiza o formulário (`templates/Users/add.php`).
3. O usuário preenche **nome** e **senha** e envia (`POST /users/add`).
4. O `add()` lê o `$_POST`, cria o objeto `User` — que **faz o hash da senha** e **valida o nome** (máx. 50 caracteres).
5. **Sucesso:** a View renderiza `templates/Users/view.php` mostrando o usuário criado (com a senha já em hash).
6. **Erro** (nome > 50): a exceção é capturada e o formulário volta com a mensagem de erro.

### O código por trás do fluxo

**Roteamento — `public/index.php`:**

```php
require __DIR__ . '/../vendor/autoload.php';

use User\Greengrocers\Controller\UsersController;

// Lê o caminho pedido na URL (ex.: "/users/add")
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$controller = new UsersController();

switch ($path) {
    case '/':
        header('Location: /users/add');
        break;

    case '/users':
        $controller->index();
        break;

    case '/users/add':
        $controller->add();
        break;

    default:
        http_response_code(404);
        echo 'Página não encontrada';
}
```

**Ação — `src/Controller/UsersController.php` (form + criação numa só ação, estilo CakePHP):**

```php
public function add(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // O User já faz o hash da senha e valida o nome
            $user = new User(
                name: $_POST['name'] ?? '',
                password: $_POST['password'] ?? '',
            );

            $this->view->render('Users/view', [
                'title' => 'Usuário Cadastrado',
                'user'  => $user,
            ]);
            return;
        } catch (\InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }

    $this->view->render('Users/add', [
        'title' => 'Cadastrar Usuário',
        'error' => $error ?? null,
    ]);
}
```

**Model — `src/Model/User.php` (dados + regras de negócio):**

```php
class User
{
    public const MAX_NAME_LENGTH = 50;

    private string $passwordHash;

    public function __construct(
        public string $name,
        string $password,
        public string $email = '',
    ) {
        // O nome não pode passar do limite de caracteres
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('O nome não pode ter mais de %d caracteres.', self::MAX_NAME_LENGTH)
            );
        }

        // Guarda apenas o HASH da senha — nunca o texto puro
        $this->passwordHash = password_hash($password, PASSWORD_ARGON2ID);
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }
}
```

**View — `src/View/View.php` (renderiza o template dentro do layout):**

```php
public function render(string $template, array $data = []): void
{
    // As chaves do array viram variáveis: ['title' => 'x'] -> $title
    extract($data);

    // Captura o HTML do template numa variável ($content)
    ob_start();
    require "{$this->templatesPath}/{$template}.php";
    $content = ob_get_clean();

    // Injeta esse HTML dentro do layout e envia ao navegador
    require "{$this->templatesPath}/layout/default.php";
}
```

**Template — `templates/Users/add.php` (o formulário):**

```php
<h1><?= htmlspecialchars($title) ?></h1>

<?php if (!empty($error)): ?>
    <p style="color: red;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post" action="/users/add">
    <input type="text" name="name" maxlength="50" required>
    <input type="password" name="password" required>
    <button type="submit">Cadastrar</button>
</form>
```
