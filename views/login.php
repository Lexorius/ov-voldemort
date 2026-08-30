<?php /** @var string $error @var string $username */ ?>
<div class="login">
  <div class="login__box">
    <div class="login__logo">THW</div>
    <h1 style="text-align:center"><?= e((string)setting('app_name', 'OV-Budget')) ?></h1>
    <p class="muted small" style="text-align:center"><?= e((string)setting('ov_name', '')) ?></p>

    <?php if ($error !== ''): ?>
      <div class="alert alert--error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="card form" autocomplete="on">
      <?= csrf_field() ?>
      <div class="field">
        <label for="username">Benutzername</label>
        <input type="text" id="username" name="username" value="<?= e($username) ?>"
               required autocapitalize="none" autocomplete="username" autofocus>
      </div>
      <div class="field">
        <label for="password">Passwort</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button class="btn btn--block" type="submit">Anmelden</button>
    </form>

    <?php if (setting('login_hinweis')): ?>
      <p class="muted small" style="text-align:center"><?= nl2br(e((string)setting('login_hinweis'))) ?></p>
    <?php endif; ?>
  </div>
</div>
