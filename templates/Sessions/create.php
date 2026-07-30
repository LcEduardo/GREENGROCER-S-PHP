<h1><?= htmlspecialchars($title) ?></h1>

<?php if (!empty($error)): ?>
    <p><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post" action="/login">
    <label>
        E-mail
        <input type="email" name="email" required>
    </label>

    <label>
        Senha
        <input type="password" name="password" required>
    </label>

    <button type="submit">Entrar</button>
</form>

<a href="/register">Criar conta</a>
<a href="/">Voltar</a>
