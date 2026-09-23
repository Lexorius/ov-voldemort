<?php
/** @var bool $aktiv @var string $url @var bool $gekoppelt @var string $pubkey
 *  @var int $letzter @var array $fahrzeuge @var string $fehler @var string $hinweis */
?>
<div class="pagehead">
  <div>
    <h1>Standortmeldung per QR-Code</h1>
    <p>In jedem Fahrzeug hängt ein QR-Code. Wer ihn scannt, kann den Standort melden – ohne Anmeldung
       und ohne Einblick in die Fahrzeugakte. Die Meldung wird auf dem Handy verschlüsselt; der
       Connector auf dem Webserver reicht sie nur weiter und kann sie nicht lesen.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('admin_settings', ['group' => 'Fahrzeuge'])) ?>">Einstellungen</a>
    <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Verwaltung</a>
  </div>
</div>

<?php if ($fehler !== ''): ?><div class="alert alert--error"><?= e($fehler) ?></div><?php endif; ?>
<?php if ($hinweis !== ''): ?><div class="alert alert--success"><?= e($hinweis) ?></div><?php endif; ?>

<?php if (!$aktiv): ?>
  <div class="alert alert--warn">Die Funktion ist ausgeschaltet. In den
    <a href="<?= e(url('admin_settings', ['group' => 'Fahrzeuge'])) ?>">Einstellungen</a> einschalten.</div>
<?php endif; ?>

<div class="card">
  <h2>Kopplung</h2>
  <?php if (!$gekoppelt): ?>
    <ol class="small">
      <li>Den Ordner <span class="mono">connector/</span> auf den Webserver legen, das Dokumentenverzeichnis
        auf <span class="mono">connector/public</span> zeigen lassen.</li>
      <li>Die Startseite des Connectors einmal aufrufen – dabei legt er den Kopplungscode an.</li>
      <li>Den Code aus der Datei <span class="mono">connector/daten/kopplungscode.txt</span> holen
        (FTP oder SSH) und hier eintragen.</li>
    </ol>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="koppeln">
      <div class="grid2">
        <div class="field">
          <label for="url">Adresse des Connectors</label>
          <input type="url" id="url" name="url" required placeholder="https://ov.example.de/connector"
                 value="<?= e($url) ?>">
        </div>
        <div class="field">
          <label for="code">Kopplungscode</label>
          <input type="text" id="code" name="code" required placeholder="A1B2C3-D4E5F6-071829" autocomplete="off">
        </div>
      </div>
      <div class="btnrow"><button class="btn" type="submit">Koppeln</button></div>
    </form>
  <?php else: ?>
    <dl class="dl">
      <div class="dl__item"><div class="dl__label">Connector</div>
        <div class="dl__value mono small"><?= e($url) ?></div></div>
      <div class="dl__item"><div class="dl__label">Zuletzt abgeholt</div>
        <div class="dl__value"><?= $letzter > 0 ? e(de_datetime(date('Y-m-d H:i:s', $letzter))) : 'noch nie' ?></div></div>
      <div class="dl__item"><div class="dl__label">Unser Schlüssel</div>
        <div class="dl__value mono small" style="word-break:break-all"><?= e(substr($pubkey, 0, 24)) ?>…</div></div>
    </dl>
    <div class="btnrow">
      <form method="post" class="inline-form"><?= csrf_field() ?>
        <input type="hidden" name="action" value="abholen">
        <button class="btn" type="submit">Jetzt abholen</button></form>
      <form method="post" class="inline-form"><?= csrf_field() ?>
        <input type="hidden" name="action" value="senden">
        <button class="btn btn--sec" type="submit">Fahrzeuge neu anmelden</button></form>
      <form method="post" class="inline-form"><?= csrf_field() ?>
        <input type="hidden" name="action" value="trennen">
        <button class="btn btn--sec" type="submit"
                data-confirm="Kopplung lösen? Die QR-Codes funktionieren erst nach einer neuen Kopplung wieder.">Kopplung lösen</button></form>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card__head">
    <h2>Fahrzeuge mit QR-Code</h2>
    <span class="muted small"><?= count($fahrzeuge) ?></span>
  </div>
  <?php if (!$fahrzeuge): ?>
    <div class="empty">Noch kein Fahrzeug hat einen Code. Den gibt es in der jeweiligen Fahrzeugakte.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Fahrzeug</th><th>Letzte Position</th><th>Quelle</th></tr></thead>
        <tbody>
        <?php foreach ($fahrzeuge as $v): ?>
          <tr>
            <td><a href="<?= e(url('vehicle', ['id' => $v['id']])) ?>#qr"><?= e($v['bezeichnung']) ?></a>
              <?= $v['kennzeichen'] ? '<span class="muted small">· ' . e((string)$v['kennzeichen']) . '</span>' : '' ?></td>
            <td class="small"><?= $v['geo_at'] ? e(de_datetime($v['geo_at'])) : '<span class="muted">–</span>' ?></td>
            <td class="small"><?= e(match ((string)$v['geo_quelle']) {
                'qr'     => 'QR-Meldung',
                'divera' => 'Divera',
                'mensch' => 'von Hand',
                default  => '–',
            }) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
