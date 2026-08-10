<div class="gg-page">
    <div class="gg-auth">

        <div class="gg-page__head">
            <div>
                <h1 class="gg-page__title"><?= htmlspecialchars($title) ?></h1>
                <p class="gg-page__sub">Leva um minuto — nome, e-mail e senha.</p>
            </div>
        </div>

        <!-- Sem bloco de erro: UsersController::store() não tem caminho de
             falha hoje (nem e-mail repetido) — sempre grava e redireciona.
             Quando tiver, é copiar o .gg-alert de Sessions/create.php. -->
        <form class="gg-card-form" method="post" action="/register">
            <div class="gg-form-stack">
                <div class="gg-field">
                    <label class="gg-field__label" for="name">Nome</label>
                    <input class="gg-input" type="text" id="name" name="name" required>
                </div>

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
                <button class="gg-btn gg-btn--primary gg-btn--block" type="submit">Cadastrar</button>
            </div>
        </form>

        <p class="gg-form-links">
            <a href="/login">Já tenho conta</a>
            <a href="/">Voltar pra vitrine</a>
        </p>

    </div>
</div>
