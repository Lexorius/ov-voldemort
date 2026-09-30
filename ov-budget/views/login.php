<?php /** @var string $error @var string $username @var bool $zweiterFaktor */ ?>
<div class="login">
  <div class="login__box">
    <div class="login__logo">THW</div>
    <h1 style="text-align:center"><?= e((string)setting('app_name', 'OV-Multitool')) ?></h1>
    <p class="muted small" style="text-align:center"><?= e((string)setting('ov_name', '')) ?></p>

    <?php if ($error !== ''): ?>
      <div class="alert alert--error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($zweiterFaktor)): ?>
    <form method="post" class="card form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="totp">
      <p>Passwort stimmt. Jetzt noch den <strong>Code aus der Authenticator-App</strong> – oder einen
        Backup-Code, wenn das Handy fehlt.</p>
      <div class="field">
        <label for="code">Code</label>
        <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus
               style="font-size:1.3rem;letter-spacing:.2em">
      </div>
      <button class="btn btn--block" type="submit">Anmelden</button>
    </form>
    <form method="post" style="text-align:center;margin-top:.6rem">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="totp_abbrechen">
      <button class="btn btn--sec btn--sm" type="submit">Abbrechen</button>
    </form>
    <?php else: ?>
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
    <?php endif; ?>

    <?php if (setting('login_hinweis')): ?>
      <p class="muted small" style="text-align:center"><?= nl2br(e((string)setting('login_hinweis'))) ?></p>
    <?php endif; ?>
  </div>
</div>
