<h1><?= htmlspecialchars($title) ?></h1>

<?php if (empty($users)): ?>
    <p>Nenhum usuário cadastrado ainda.</p>
<?php else: ?>
    <ul>
        <?php foreach ($users as $user): ?>
            <li><?= htmlspecialchars($user->name) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<p><a href="/users/add">Cadastrar novo usuário</a></p>
