<div class="gg-page">
    <div class="gg-auth">

        <div class="gg-page__head">
            <div>
                <h1 class="gg-page__title"><?= htmlspecialchars($title) ?></h1>
                <p class="gg-page__sub">Entre pra montar seu carrinho.</p>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <p class="gg-alert" role="alert"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form class="gg-card-form" method="post" action="/login">
            <div class="gg-form-stack">
                <div class="gg-field">
                    <label class="gg-field__label" for="email">E-mail</label>
                    <input class="gg-input" type="email" id="email" name="email" required>
                </div>

                <div class="gg-field">
                    <label class="gg-field__label" for="password">Senha</label>
                    <input class="gg-input" type="password" id="password" name="password" required>
                </div>
            </div>

            <div class="gg-form-actions">
                <button class="gg-btn gg-btn--primary gg-btn--block" type="submit">Entrar</button>
            </div>
        </form>

        <p class="gg-form-links">
            <a href="/register">Criar conta</a>
            <a href="/">Voltar pra vitrine</a>
        </p>

    </div>
</div>
