<h1><?= htmlspecialchars($title) ?></h1>

<p>Usuário criado com sucesso!</p>

<ul>
    <li><strong>Nome:</strong> <?= htmlspecialchars($user->name) ?></li>
    <li><strong>Senha (hash):</strong> <code><?= htmlspecialchars($user->getPasswordHash()) ?></code></li>
</ul>

<p><a href="/users/add">Cadastrar outro usuário</a></p>
