<?php
/**
 * Zweiter Faktor im Profil: einrichten, Backup-Codes, abschalten.
 *
 * @var array $totp  status (aktiv, seit, backup_rest, pflicht), setup (Geheimnis
 *                   während der Einrichtung), uri, codes (einmalig zu zeigen)
 */
$st = $totp['status'];
if ($totp['setup'] !== '') {
    $GLOBALS['ovb_qr_js'] = true;
}
?>
<section class="card" id="zweiter-faktor">
  <h2>Zweiter Faktor</h2>

  <?php if ($totp['codes']): ?>
    <div class="alert alert--warn">
      <strong>Deine Backup-Codes – sie erscheinen nur jetzt.</strong> Jeder Code gilt einmal und ersetzt die App,
      wenn das Handy fehlt. Bitte ausdrucken oder sicher ablegen.
    </div>
    <div class="chips mono" style="font-size:1.05rem;gap:.6rem;margin:.6rem 0">
      <?php foreach ($totp['codes'] as $c): ?><span class="chip"><?= e($c) ?></span><?php endforeach; ?>
    </div>
    <div class="btnrow">
      <button class="btn btn--sec" type="button" onclick="window.print()">Drucken</button>
    </div>
    <hr>
  <?php endif; ?>

  <?php if ($totp['setup'] !== ''): ?>
    <p>Scanne den Code mit einer Authenticator-App (z. B. Aegis, 2FAS, Google Authenticator, Microsoft
      Authenticator, Bitwarden) und trage dann den sechsstelligen Code ein, den die App zeigt.</p>
    <div class="grid2">
      <div class="qr-block" data-qr="<?= e($totp['uri']) ?>">
        <div class="qr-bild" id="qr-bild" style="max-width:220px"></div>
        <p class="small muted">Ohne Kamera: Konto von Hand anlegen mit dem Schlüssel<br>
          <span class="mono"><?= e(totp_secret_lesbar($totp['setup'])) ?></span><br>
          (zeitbasiert, 6 Stellen, 30 Sekunden)</p>
      </div>
      <div>
        <form method="post" class="form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="totp_aktivieren">
          <div class="field">
            <label for="totp_code">Code aus der App</label>
            <input type="text" id="totp_code" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7"
                   autocomplete="one-time-code" required autofocus style="max-width:12rem;font-size:1.3rem;letter-spacing:.2em">
          </div>
          <div class="btnrow">
            <button class="btn" type="submit">Zweiten Faktor einschalten</button>
          </div>
        </form>
        <form method="post" style="margin-top:.6rem">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="totp_abbrechen">
          <button class="btn btn--sec btn--sm" type="submit">Abbrechen</button>
        </form>
      </div>
    </div>

  <?php elseif ($st['aktiv']): ?>
    <p><span class="badge" style="background:#15803d">eingeschaltet</span>
      <?php if ($st['seit']): ?><span class="muted small">seit <?= e(de_datetime((string)$st['seit'])) ?></span><?php endif; ?>
      · <?= (int)$st['backup_rest'] ?> Backup-Code<?= (int)$st['backup_rest'] === 1 ? '' : 's' ?> übrig</p>
    <p class="small muted">Bei jeder Anmeldung fragt die Anwendung nach Passwort und Code aus der App.
      Fehlt das Handy, geht ein Backup-Code. Ist beides weg, kann die Administration den zweiten Faktor
      zurücksetzen.</p>
    <div class="grid2">
      <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="totp_codes_neu">
        <h3>Neue Backup-Codes</h3>
        <p class="small muted">Die alten Codes gelten danach nicht mehr.</p>
        <div class="field">
          <label for="pw_codes">Passwort zur Bestätigung</label>
          <input type="password" id="pw_codes" name="passwort" required autocomplete="current-password">
        </div>
        <div><button class="btn btn--sec" type="submit">Codes erzeugen</button></div>
      </form>
      <?php if ($st['pflicht']): ?>
        <div>
          <h3>Abschalten</h3>
          <p class="small muted">Für deine Rolle ist der zweite Faktor Pflicht – abschalten geht nicht.
            Ein neues Handy richtest du ein, indem die Administration den zweiten Faktor zurücksetzt.</p>
        </div>
      <?php else: ?>
        <form method="post" class="form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="totp_aus">
          <h3>Abschalten</h3>
          <div class="field">
            <label for="pw_aus">Passwort zur Bestätigung</label>
            <input type="password" id="pw_aus" name="passwort" required autocomplete="current-password">
          </div>
          <div><button class="btn btn--danger" type="submit" data-confirm="Zweiten Faktor wirklich abschalten?">Abschalten</button></div>
        </form>
      <?php endif; ?>
    </div>

  <?php else: ?>
    <?php if ($st['pflicht']): ?>
      <div class="alert alert--warn">Für deine Rolle ist der zweite Faktor <strong>Pflicht</strong>. Bitte richte
        ihn jetzt ein – vorher geht es in der Anwendung nicht weiter.</div>
    <?php endif; ?>
    <p>Mit dem zweiten Faktor fragt die Anmeldung zusätzlich zum Passwort einen sechsstelligen Code aus einer
      Authenticator-App auf dem Handy ab. Ein gestohlenes Passwort allein reicht dann nicht mehr.</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="totp_start">
      <button class="btn" type="submit">Zweiten Faktor einrichten</button>
    </form>
  <?php endif; ?>
</section>
