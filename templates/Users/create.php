<h1><?= htmlspecialchars($title) ?></h1>

<form method="post" action="/register">
    <label>
        Nome
        <input type="text" name="name" required>
    </label>

    <label>
        E-mail
        <input type="email" name="email" required>
    </label>

    <label>
        Senha
        <input type="password" name="password" required>
    </label>

    <button type="submit">Cadastrar</button>
</form>

<a href="/">Voltar</a>
