<?php
/** @var ?string $problem @var array $liste @var string $fehler @var string $hinweis
 *  @var array $verlauf @var ?array $ergebnis @var string $letzte @var int $automatik
 *  @var int $aufheben @var string $letzteAuto @var int $uploadMax */
$gruende = [
    'manuell'               => 'von Hand',
    'automatisch'           => 'automatisch',
    'hochgeladen'           => 'hochgeladen',
    'vor-wiederherstellung' => 'vor Wiederherstellung',
];
$grundVon = static function (string $name) use ($gruende): string {
    $g = preg_replace('/^ovbudget-\d{8}-\d{6}-|\.zip$/', '', $name);
    return $gruende[$g] ?? $g;
};
?>
<div class="pagehead">
  <div>
    <h1>Sicherung</h1>
    <p>Datenbank und Dateiablage in einer ZIP-Datei – zum Herunterladen, Aufheben und
       Wiederherstellen. Die Sicherungen liegen im Datenordner unter <span class="mono">sicherungen/</span>
       und sind damit auch Teil eines Home-Assistant-Backups.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Zur Verwaltung</a>
  </div>
</div>

<?php if ($problem): ?>
  <div class="alert alert--error"><?= e($problem) ?></div>
<?php endif; ?>
<?php if ($fehler): ?>
  <div class="alert alert--error"><?= e($fehler) ?></div>
<?php endif; ?>
<?php if ($hinweis): ?>
  <div class="alert alert--success"><?= e($hinweis) ?></div>
<?php endif; ?>

<?php if ($ergebnis): ?>
  <section class="card">
    <h2>Wiederhergestellt</h2>
    <p>Eingespielt wurde <strong><?= e($ergebnis['sicherung']) ?></strong>
      (Stand <?= e(de_datetime(date('Y-m-d H:i:s', strtotime($ergebnis['zeit']) ?: time()))) ?>,
      Fassung <?= e($ergebnis['fassung']) ?>). Der Stand von vorher liegt als
      <strong><?= e($ergebnis['vorher']) ?></strong> bereit – falls es die falsche war.</p>
    <ul class="small">
      <?php foreach ($verlauf as $z): ?><li><?= e($z) ?></li><?php endforeach; ?>
    </ul>
    <p class="small muted">Sitzungen anderer Benutzer bleiben nur gültig, wenn es ihren Zugang in
      der Sicherung gibt. Dein eigener Zugang stammt jetzt ebenfalls aus der Sicherung.</p>
  </section>
<?php endif; ?>

<div class="stats">
  <div class="stat"><div class="stat__label">Sicherungen</div><div class="stat__value"><?= count($liste) ?></div></div>
  <div class="stat"><div class="stat__label">Letzte</div>
    <div class="stat__value" style="font-size:1rem"><?= $letzte ? e(de_datetime($letzte)) : '–' ?></div></div>
  <div class="stat"><div class="stat__label">Automatisch</div>
    <div class="stat__value" style="font-size:1rem"><?= $automatik > 0
        ? 'alle ' . (int)$automatik . ' Tag(e), ' . (int)$aufheben . ' aufheben' : 'aus' ?></div></div>
  <div class="stat"><div class="stat__label">Letzte automatische</div>
    <div class="stat__value" style="font-size:1rem"><?= $letzteAuto ? e(de_datetime($letzteAuto)) : '–' ?></div></div>
</div>

<div class="grid2">
  <section class="card">
    <h2>Sicherung anlegen</h2>
    <p class="small">Enthält alle Tabellen samt Inhalt und die Dateiablage. Passwörter sind darin
      nur als Hash, aber Zugangsdaten zu Divera, Stein.APP und MQTT sowie PIN und PUK der
      SIM-Karten stehen im Klartext – die Datei entsprechend behandeln.</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="erstellen">
      <button class="btn" type="submit"<?= $problem ? ' disabled' : '' ?>>Jetzt sichern</button>
    </form>
    <p class="small muted mt">Automatische Sicherungen lassen sich unter
      <a href="<?= e(url('admin_settings', ['group' => 'Sicherung'])) ?>">Einstellungen → Sicherung</a>
      einschalten; sie laufen mit dem Abruf über <span class="mono">cron.php</span>.</p>
  </section>

  <section class="card">
    <h2>Sicherung hochladen</h2>
    <p class="small">Eine früher heruntergeladene Sicherung wieder auf den Server bringen, etwa nach
      einem Umzug. Sie erscheint danach in der Liste und lässt sich von dort wiederherstellen.</p>
    <form method="post" enctype="multipart/form-data" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="hochladen">
      <div class="field">
        <label for="sicherung">ZIP-Datei</label>
        <input type="file" id="sicherung" name="sicherung" accept=".zip,application/zip" required>
        <small>Höchstens <?= e(bytes_human($uploadMax)) ?> – begrenzt durch den Webserver.</small>
      </div>
      <div><button class="btn btn--sec" type="submit"<?= $problem ? ' disabled' : '' ?>>Hochladen</button></div>
    </form>
  </section>
</div>

<section class="card">
  <div class="card__head">
    <h2>Vorhandene Sicherungen</h2>
    <span class="muted small"><?= count($liste) ?></span>
  </div>
  <?php if (!$liste): ?>
    <div class="empty">Noch keine Sicherung vorhanden.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Zeitpunkt</th><th>Anlass</th><th>Fassung</th><th class="num">Tabellen</th>
                   <th class="num">Dateien</th><th class="num">Größe</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($liste as $b): ?>
          <tr<?= $b['kaputt'] ? ' class="is-muted"' : '' ?>>
            <td class="nowrap">
              <?= e(de_datetime(date('Y-m-d H:i:s', $b['zeit']))) ?>
              <div class="small mono muted"><?= e($b['name']) ?></div>
            </td>
            <td class="small"><?= e($grundVon($b['name'])) ?>
              <?php if (!empty($b['meta']['von'])): ?><div class="small muted"><?= e($b['meta']['von']) ?></div><?php endif; ?></td>
            <td class="small"><?= $b['kaputt'] ? '<span class="badge badge--muted">unlesbar</span>' : e((string)($b['meta']['fassung'] ?? '')) ?></td>
            <td class="num small"><?= (int)($b['meta']['tabellen'] ?? 0) ?: '–' ?></td>
            <td class="num small"><?= isset($b['meta']['dateien']) ? (int)$b['meta']['dateien'] : '–' ?></td>
            <td class="num small nowrap"><?= e(bytes_human($b['groesse'])) ?></td>
            <td class="nowrap">
              <a class="btn btn--sec btn--sm" href="<?= e(url('admin_backup', ['datei' => $b['name']])) ?>">Herunterladen</a>
              <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="loeschen">
                <input type="hidden" name="name" value="<?= e($b['name']) ?>">
                <button class="btn btn--danger btn--sm" type="submit"
                        data-confirm="Diese Sicherung löschen?">Löschen</button>
              </form>
            </td>
          </tr>
          <?php if (!$b['kaputt']): ?>
            <tr class="zeile-ohne-rand">
              <td colspan="7">
                <details>
                  <summary class="small">Diese Sicherung wiederherstellen …</summary>
                  <form method="post" class="form mt" style="max-width:36rem">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="wiederherstellen">
                    <input type="hidden" name="name" value="<?= e($b['name']) ?>">
                    <p><strong>Achtung:</strong> Das ersetzt die gesamte Datenbank und die Dateiablage
                      durch den Stand vom <?= e(de_datetime(date('Y-m-d H:i:s', $b['zeit']))) ?>.
                      Alles, was seitdem eingetragen wurde, ist danach weg. Vorher wird der jetzige
                      Stand automatisch gesichert.</p>
                    <div class="field">
                      <label for="pw-<?= e(md5($b['name'])) ?>">Dein Passwort</label>
                      <input type="password" id="pw-<?= e(md5($b['name'])) ?>" name="passwort"
                             autocomplete="current-password" required>
                    </div>
                    <div class="field">
                      <label for="ok-<?= e(md5($b['name'])) ?>">Zur Bestätigung „<?= e(BACKUP_BESTAETIGUNG) ?>" eintippen</label>
                      <input type="text" id="ok-<?= e(md5($b['name'])) ?>" name="bestaetigung"
                             autocomplete="off" autocapitalize="characters" required>
                    </div>
                    <div class="btnrow">
                      <button class="btn btn--danger" type="submit"
                              data-confirm="Wirklich alles durch diese Sicherung ersetzen?">Jetzt wiederherstellen</button>
                    </div>
                  </form>
                </details>
              </td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
