<?php
/** @var string $fehler @var string $hinweis @var array $status @var string $an */
?>
<div class="pagehead">
  <div>
    <h1>E-Mail</h1>
    <p>Berichte der Abstimmungen gehen per E-Mail an einen Kontakt. Dafür spricht OV-Multitool direkt mit einem
       SMTP-Server – dem des Vereins, des Providers oder einem Postfach der Dienststelle.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('admin_settings', ['group' => 'E-Mail'])) ?>">Einstellungen</a>
    <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Verwaltung</a>
  </div>
</div>

<?php if ($fehler !== ''): ?><div class="alert alert--error"><?= e($fehler) ?></div><?php endif; ?>
<?php if ($hinweis !== ''): ?><div class="alert alert--success"><?= e($hinweis) ?></div><?php endif; ?>

<?php if (!$status['aktiv']): ?>
  <div class="alert alert--warn">Der Versand ist <strong>ausgeschaltet</strong>. Es geht keine Nachricht hinaus, Berichte bleiben
    liegen. Einschalten in den <a href="<?= e(url('admin_settings', ['group' => 'E-Mail'])) ?>">Einstellungen</a>.</div>
<?php elseif (!$status['eingerichtet']): ?>
  <div class="alert alert--warn">Eingeschaltet, aber unvollständig: Es fehlt der Server oder eine gültige Absenderadresse.</div>
<?php endif; ?>

<section class="card">
  <div class="card__head"><h2>Zustand</h2></div>
  <div class="stats">
    <div class="stat"><div class="stat__label">Server</div>
      <div class="stat__value" style="font-size:1rem"><?= $status['host'] !== '' ? e($status['host']) . ':' . (int)$status['port'] : '<span class="muted">–</span>' ?></div>
      <div class="stat__hint"><?= e(explode(' – ', MAIL_SICHERHEIT[$status['sicherheit']])[0]) ?></div></div>
    <div class="stat"><div class="stat__label">Absender</div>
      <div class="stat__value" style="font-size:1rem"><?= $status['absender'] !== '' ? e($status['absender']) : '<span class="muted">–</span>' ?></div>
      <div class="stat__hint"><?= e($status['absender_name']) ?><?= $status['benutzer'] !== '' ? ' · Anmeldung als ' . e($status['benutzer']) : ' · ohne Anmeldung' ?></div></div>
    <div class="stat"><div class="stat__label">Zuletzt gesendet</div>
      <div class="stat__value" style="font-size:1rem"><?= $status['zuletzt'] > 0 ? e(de_datetime(date('Y-m-d H:i:s', $status['zuletzt']))) : '<span class="muted">noch nie</span>' ?></div></div>
    <div class="stat"><div class="stat__label">Letzter Fehler</div>
      <div class="stat__value" style="font-size:.9rem;<?= $status['letzter_fehler'] !== '' ? 'color:var(--bad)' : '' ?>"><?= $status['letzter_fehler'] !== '' ? e($status['letzter_fehler']) : '<span class="muted">keiner</span>' ?></div></div>
  </div>
</section>

<section class="card">
  <div class="card__head"><h2>Testnachricht</h2></div>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="test">
    <div class="field">
      <label for="an">An</label>
      <input type="email" id="an" name="an" required maxlength="254" value="<?= e($an) ?>" placeholder="name@example.de">
    </div>
    <div class="btnrow">
      <button class="btn" type="submit" <?= $status['eingerichtet'] ? '' : 'disabled' ?>>Testnachricht senden</button>
    </div>
  </form>
  <p class="small muted">Typische Werte: Port 587 mit STARTTLS, Port 465 mit SSL/TLS. Benutzer und Passwort sind meist die des
    Postfachs; viele Anbieter verlangen, dass die Absenderadresse zum Postfach gehört.</p>
</section>
