<?php
/** @var array $user @var array $errors @var array $meine @var array $dienste */
$rollen = ['admin' => 'Administration', 'leitung' => 'Leitung', 'user' => 'Mitglied'];
?>
<div class="pagehead">
  <h1>Mein Profil</h1>
  <a class="btn btn--sec" href="<?= e(url('logout')) ?>">Abmelden</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="grid2">
  <form method="post" class="card form">
    <?= csrf_field() ?>
    <h2>Angaben</h2>
    <div class="field">
      <label>Benutzername</label>
      <input type="text" value="<?= e($user['username']) ?>" disabled>
    </div>
    <div class="field">
      <label for="display_name">Anzeigename</label>
      <input type="text" id="display_name" name="display_name" value="<?= e((string)$user['display_name']) ?>">
    </div>
    <div class="field">
      <label for="email">E-Mail</label>
      <input type="email" id="email" name="email" value="<?= e((string)$user['email']) ?>">
    </div>
    <div class="field">
      <label for="phone">Telefon</label>
      <input type="tel" id="phone" name="phone" value="<?= e((string)$user['phone']) ?>">
    </div>

    <?php if (setting_bool('ha_benachrichtigung_aktiv', false)): ?>
      <h3>Benachrichtigungen</h3>
      <div class="field">
        <label for="ha_notify">Ziel in Home Assistant</label>
        <input type="text" id="ha_notify" name="ha_notify" list="notify-dienste" placeholder="mobile_app_…"
               value="<?= e((string)($user['ha_notify'] ?? '')) ?>">
        <datalist id="notify-dienste">
          <?php foreach ($dienste as $d): ?><option value="<?= e($d) ?>"></option><?php endforeach; ?>
        </datalist>
        <small class="muted">Der Dienst der Companion-App, also <span class="mono">notify.<em>ziel</em></span> ohne
          das „notify.". <?= $dienste ? 'Die Liste kommt aus Home Assistant.' : 'Home Assistant meldet gerade keine Ziele.' ?></small>
      </div>
      <div class="field field--check">
        <input type="checkbox" id="notify_aktiv" name="notify_aktiv" value="1"
               <?= (int)($user['notify_aktiv'] ?? 1) ? 'checked' : '' ?>>
        <label for="notify_aktiv">Benachrichtigungen erhalten</label>
      </div>
    <?php endif; ?>

    <div><button class="btn" type="submit">Speichern</button></div>
  </form>

  <div>
    <div class="card">
      <h2>Zuordnung</h2>
      <dl class="dl">
        <div class="dl__item"><div class="dl__label">Rolle</div>
          <div class="dl__value"><?= e($rollen[$user['role']] ?? $user['role']) ?></div></div>
        <div class="dl__item"><div class="dl__label">Fachgruppe</div>
          <div class="dl__value"><?= e(list_label((int)($user['fachgruppe_id'] ?? 0), 'nicht zugeordnet')) ?></div></div>
      </dl>
      <h3 class="mt">Funktionen</h3>
      <?php if (!$meine): ?>
        <p class="muted small">Keine Funktionen hinterlegt.</p>
      <?php else: ?>
        <div class="chips"><?php foreach ($meine as $m): ?><span class="chip"><?= e($m['label']) ?></span><?php endforeach; ?></div>
      <?php endif; ?>
      <p class="muted small mt">Rolle, Fachgruppe und Funktionen werden von der Administration gepflegt.</p>
    </div>

    <form method="post" class="card form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <h2>Passwort ändern</h2>
      <div class="field">
        <label for="alt">Aktuelles Passwort</label>
        <input type="password" id="alt" name="alt" required autocomplete="current-password">
      </div>
      <div class="field">
        <label for="neu">Neues Passwort</label>
        <input type="password" id="neu" name="neu" required autocomplete="new-password">
        <small>Mindestens <?= setting_int('passwort_min_laenge', 10) ?> Zeichen.</small>
      </div>
      <div class="field">
        <label for="neu2">Neues Passwort wiederholen</label>
        <input type="password" id="neu2" name="neu2" required autocomplete="new-password">
      </div>
      <div><button class="btn" type="submit">Passwort ändern</button></div>
    </form>
  </div>
</div>
