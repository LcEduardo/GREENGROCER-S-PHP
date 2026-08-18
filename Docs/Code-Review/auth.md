Começamos pela sessão. Na internet cada requisição HTTP é independente, ou seja, o servidor não mantém informações sobre requisições anteriores. Para contornar isso, utilizamos sessões, que permitem armazenar dados do usuário entre diferentes requisições. Desse modo, para cada requisição precisamos iniciar uma sessão, ``session_start()``. A sessão é identificada por um cookie que é enviado ao navegador do usuário.

É armazenado o ID da sessão no cookie, e o servidor utiliza esse ID para recuperar os dados da sessão correspondente. É importante garantir que a sessão seja iniciada antes de qualquer saída de conteúdo para o navegador, caso contrário, ocorrerá um erro. Alguns tipos de dados que normalmente são armazenados na sessão:

- User Id
- Permissões do usuário
- Cart content (em sites de e-commerce)

Optamos por iniciar a sessão no arquivo ``public/index.php`` — é o Front Controller, o único ponto por onde toda requisição passa antes de chegar em qualquer Controller — para que ela esteja disponível em todas as rotas, sem precisar lembrar de chamar ``session_start()`` em cada uma.

## Guard: quem decide "está logado" / "é admin"

Criamos um arquivo ``Auth/Guard.php`` com a classe ``Guard``, que tem os métodos estáticos ``isLoggedIn()`` e ``isAdmin()`` — não são duas classes, e sim dois métodos da mesma classe:

```php
class Guard
{
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function isAdmin(): bool
    {
        return self::isLoggedIn() && ($_SESSION['admin'] ?? false) === true;
    }
}
```

``isLoggedIn()`` verifica se existe ``$_SESSION['user_id']``; ``isAdmin()`` verifica, além disso, se ``$_SESSION['admin']`` é ``true`` — e reaproveita ``isLoggedIn()`` em vez de checar a sessão duas vezes de dois jeitos diferentes.

Esse ``$_SESSION['admin']`` é a "permissão do usuário" citada acima: um booleano gravado no momento do login, não uma lista de permissões — é só o suficiente para diferenciar admin de cliente comum neste sistema.

O ``Guard`` fica fora dos Controllers de propósito: quem chama é o roteador (``public/index.php``), ANTES de instanciar qualquer Controller de uma rota ``/admin/*``. Assim o Controller nunca chega a rodar para quem não tem permissão — a proteção acontece uma camada antes:

```php
case '/admin/products':
    if (!Guard::isAdmin()) {
        header('Location: ' . (Guard::isLoggedIn() ? '/' : '/login'));
        break;
    }

    $repo = new ProductRepository(Connection::get());
    (new ProductsController($repo))->admin();
    break;
```

Repare na diferença dos dois redirecionamentos: quem não está logado vai para ``/login``; quem está logado mas não é admin (um cliente comum tentando acessar uma tela de admin) volta para ``/``, a vitrine — não faz sentido mandar para a tela de login alguém que já está autenticado.

Com isso, o Controller fica responsável só pela rota em si — ler o request, montar a resposta —, enquanto a autenticação fica inteiramente a cargo do Guard, que bloqueia antes mesmo do Controller ser chamado.

## Login: ``SessionsController``

Quem cuida do login e do logout é o ``SessionsController``, que recebe um ``UserRepository`` pronto — o mesmo padrão dos outros Controllers do projeto: o repository chega de fora, o Controller não sabe que existe um banco.

```php
public function store(): void
{
    $email = (string) filter_input(INPUT_POST, 'email');
    $password = (string) filter_input(INPUT_POST, 'password');

    $user = $this->users->findByEmail($email);

    if ($user === null || !$user->verifyPassword($password)) {
        $this->view->render('Sessions/create', [
            'title' => 'Entrar',
            'error' => 'E-mail ou senha inválidos.',
        ]);
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user->id;
    $_SESSION['admin'] = $user->admin;

    header('Location: ' . ($user->admin ? '/admin/products' : '/'));
}
```

O fluxo:

1. Busca o usuário pelo e-mail (``UserRepository::findByEmail()``).
2. Se não achou o usuário, ou a senha não bate, volta para a própria tela de login com um aviso genérico — "E-mail ou senha inválidos", sem dizer qual dos dois errou. Dizer "e-mail não cadastrado" ajudaria alguém a descobrir quais e-mails existem no sistema por tentativa e erro.
3. Se bateu, regenera o id da sessão (``session_regenerate_id(true)``) antes de gravar qualquer coisa nela. É uma proteção contra *session fixation*: o id de sessão de antes do login não pode continuar valendo depois que o usuário é conhecido — sem isso, alguém que tivesse forçado um id de sessão no navegador da vítima (por exemplo, por um link malicioso) poderia usar esse mesmo id para se autenticar como ela depois do login dela.
4. Grava ``user_id`` e ``admin`` na sessão — é literalmente esse ponto que "loga" o usuário; tudo depois disso (``Guard``, rotas protegidas) depende só desses dois valores existirem.
5. Redireciona: admin vai para ``/admin/products``; cliente comum volta para ``/``. Não existe hoje uma área logada para cliente comum além da vitrine — é o carrinho, ainda fora do escopo.

``verifyPassword()`` mora no ``Model\User``, não no Controller nem no Repository:

```php
public function verifyPassword(string $plainPassword): bool
{
    return password_verify($plainPassword, $this->password);
}
```

E a senha nunca é comparada em texto puro. No cadastro (``UsersController::store()``), ela passa por ``User::hashPassword()`` antes de ir para o banco:

```php
public static function hashPassword(string $plainPassword): string
{
    return password_hash($plainPassword, PASSWORD_ARGON2ID);
}
```

Usamos ``PASSWORD_ARGON2ID`` em vez do ``PASSWORD_DEFAULT`` (bcrypt) — é o algoritmo recomendado atualmente para hash de senha. O campo ``password`` do usuário nunca é lido de volta como senha; ``password_verify()`` compara o hash salvo com o hash do que foi digitado, sem nunca "descriptografar" nada — hash não é reversível.

## Logout

```php
public function destroy(): void
{
    $_SESSION = [];
    session_destroy();

    header('Location: /');
}
```

Logout esvazia o array ``$_SESSION`` e destrói a sessão no servidor (``session_destroy()``) — não é só apagar ``user_id``: qualquer outro dado que a sessão vier a guardar no futuro (carrinho, por exemplo) some junto. Depois disso, ``Guard::isLoggedIn()`` volta a ser ``false`` porque ``$_SESSION['user_id']`` não existe mais.

## Cadastro: ``UsersController``

O cadastro público (``/register``) nunca cria um admin — o ``admin: false`` é passado explicitamente para ``UserRepository::create()``:

```php
public function store(): void
{
    $this->users->create(
        name: (string) filter_input(INPUT_POST, 'name'),
        email: (string) filter_input(INPUT_POST, 'email'),
        password: User::hashPassword((string) filter_input(INPUT_POST, 'password')),
        admin: false,
    );

    header('Location: /');
}
```

Essa é uma decisão deliberada: não existe tela para "virar admin" dentro do sistema. Hoje um admin é criado na mão, alterando o campo direto no banco depois que o usuário já se cadastrou como cliente comum.

Repare que o cadastro não loga o usuário automaticamente — depois de criar a conta, ele volta para ``/`` e precisa passar pelo ``/login`` como qualquer outro fluxo.

---

Com isso, a autenticação do sistema fica inteira: sessão como mecanismo de "lembrar" o usuário entre requisições, ``Guard`` como o único lugar que decide quem pode ver o quê, e ``SessionsController``/``UsersController`` como quem efetivamente grava e apaga esses dados de sessão.
