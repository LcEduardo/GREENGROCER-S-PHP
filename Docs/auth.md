Começamos pela sessão. Na internet cada requisição HTTP é independente, ou seja, o servidor não mantém informações sobre requisições anteriores. Para contornar isso, utilizamos sessões, que permitem armazenar dados do usuário entre diferentes requisições. Desse modo, para cada requisição precisamos iniciar uma sessão, ``session_start()``. A sessão é identificada por um cookie que é enviado ao navegador do usuário.

É armazenado o ID da sessão no cookie, e o servidor utiliza esse ID para recuperar os dados da sessão correspondente. É importante garantir que a sessão seja iniciada antes de qualquer saída de conteúdo para o navegador, caso contrário, ocorrerá um erro. Alguns tipos de dados que normalmente são armazenados na sessão:

- User Id
- Permissões do usuário
- Cart content (em sites de e-commerce)

Optamos por iniciar a sessão no arquivo ``index.php`` para que ela esteja disponível em todas as páginas. Criamos um arquivo chamado ``Auth/Guard.php`` que contém a classe ``Guard``, com os métodos estáticos ``isLoggedIn()`` e ``isAdmin()`` — não são duas classes, e sim dois métodos da mesma classe. ``isLoggedIn()`` verifica se existe ``$_SESSION['user_id']``; ``isAdmin()`` verifica, além disso, se ``$_SESSION['admin']`` é ``true``.

Esse ``$_SESSION['admin']`` é a "permissão do usuário" citada acima: um booleano gravado no momento do login (``$_SESSION['admin'] = $user->admin``), não uma lista de permissões — é só o suficiente para diferenciar admin de cliente comum neste sistema.

Esses métodos são utilizados para proteger rotas específicas do sistema, garantindo que apenas usuários autenticados (ou administradores, no caso de ``isAdmin()``) possam acessá-las.

Com isso, o controller fica responsável pela rotas, enquanto a autenticação fica a cargo do Guard que bloqueia a rota antes de chamar o controller.