<h1><?= htmlspecialchars($title) ?></h1>

<?php if (!empty($error)): ?>
    <p style="color: red;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post" action="/users/add">
    <div>
        <label for="name">Nome:</label>
        <input type="text" id="name" name="name" maxlength="50" required>
    </div>

    <div>
        <label for="password">Senha:</label>
        <input type="password" id="password" name="password" required>
    </div>

    <button type="submit">Cadastrar</button>
</form>
