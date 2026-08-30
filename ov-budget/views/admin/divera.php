<?php
/** @var array $forms @var array $remoteForms @var ?array $verbindung @var array $log */
$aktiv = setting_bool('divera_aktiv', false);
$keySet = (string)setting('divera_accesskey', '') !== '';
$cronToken = (string)setting('divera_cron_token', '');
?>
<div class="pagehead">
  <div>
    <h1>Divera 24/7</h1>
    <p>Formulare aus Divera abrufen und deren Einträge als Wünsche übernehmen. Bereits importierte Einträge werden
       anhand von Formular- und Eintrags-ID erkannt und nicht doppelt angelegt.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('admin_settings', ['group' => 'Divera 24/7'])) ?>">Zugang einrichten</a>
    <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Verwaltung</a>
  </div>
</div>

<?php if (!$aktiv || !$keySet): ?>
  <div class="alert alert--warn">
    Die Anbindung ist noch nicht einsatzbereit:
    <?php if (!$aktiv): ?>Sie ist deaktiviert.<?php endif; ?>
    <?php if (!$keySet): ?>Es ist kein Accesskey hinterlegt.<?php endif; ?>
    <a href="<?= e(url('admin_settings', ['group' => 'Divera 24/7'])) ?>">Jetzt einrichten</a>
  </div>
<?php endif; ?>

<?php if ($verbindung): ?>
  <div class="alert alert--<?= $verbindung['ok'] ? 'success' : 'error' ?>"><?= e($verbindung['msg']) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card__head">
    <h2>Verbindung prüfen</h2>
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="test">
      <button class="btn" type="submit">Formulare aus Divera abrufen</button>
    </form>
  </div>
  <dl class="dl">
    <div class="dl__item"><div class="dl__label">Basis-URL</div>
      <div class="dl__value mono small"><?= e((string)setting('divera_base_url', '')) ?></div></div>
    <div class="dl__item"><div class="dl__label">Pfad Formularliste</div>
      <div class="dl__value mono small"><?= e((string)setting('divera_forms_path', '')) ?></div></div>
    <div class="dl__item"><div class="dl__label">Pfad Einträge</div>
      <div class="dl__value mono small"><?= e((string)setting('divera_entries_path', '')) ?></div></div>
    <div class="dl__item"><div class="dl__label">Accesskey</div>
      <div class="dl__value"><?= $keySet ? 'hinterlegt' : '<span style="color:var(--bad)">fehlt</span>' ?></div></div>
  </dl>
</div>

<?php if ($remoteForms): ?>
  <div class="card">
    <h2>In Divera gefundene Formulare</h2>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Name</th><th>ID</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($remoteForms as $rf): ?>
          <tr>
            <td><?= e($rf['name']) ?></td>
            <td class="mono small"><?= e($rf['id']) ?></td>
            <td>
              <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_form">
                <input type="hidden" name="form_id" value="<?= e($rf['id']) ?>">
                <input type="hidden" name="name" value="<?= e($rf['name']) ?>">
                <button class="btn btn--sm" type="submit">Einbinden</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card__head">
    <h2>Eingebundene Formulare</h2>
    <span class="muted small"><?= count($forms) ?></span>
  </div>
  <?php if (!$forms): ?>
    <div class="empty">Noch kein Formular eingebunden. Oben "Formulare aus Divera abrufen" nutzen.</div>
  <?php else: ?>
    <div class="itemlist">
      <?php foreach ($forms as $f):
          $map = json_decode((string)$f['field_map'], true) ?: [];
      ?>
        <div class="card" style="margin:0">
          <div class="card__head">
            <div>
              <h3 style="margin:0"><?= e($f['name']) ?></h3>
              <div class="muted small">
                ID <span class="mono"><?= e($f['form_id']) ?></span> ·
                <?= count(array_filter($map)) ?> Felder zugeordnet ·
                letzter Abruf: <?= e(de_datetime($f['last_sync']) ?: 'noch nie') ?>
              </div>
            </div>
            <div class="btnrow">
              <a class="btn btn--sec btn--sm" href="<?= e(url('admin_divera_form', ['id' => $f['id']])) ?>">Felder zuordnen</a>
              <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="import">
                <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                <button class="btn btn--sm" type="submit">Jetzt importieren</button>
              </form>
              <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_form">
                <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                <button class="btn btn--sec btn--sm" type="submit"
                        data-confirm="Formular aus der Anwendung entfernen? Importierte Wünsche bleiben erhalten.">Entfernen</button>
              </form>
            </div>
          </div>
          <?php if (!array_filter($map)): ?>
            <p class="small" style="color:var(--warn);margin:0">Noch keine Feldzuordnung – der Import würde nur
              Notfall-Bezeichnungen erzeugen. Bitte zuerst die Felder zuordnen.</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Automatischer Abruf</h2>
  <?php if ($cronToken === ''): ?>
    <p class="small muted">Für einen automatischen Abruf per Cron in den
      <a href="<?= e(url('admin_settings', ['group' => 'Divera 24/7'])) ?>">Einstellungen</a>
      ein Token unter <span class="mono">divera_cron_token</span> hinterlegen.</p>
  <?php else: ?>
    <p class="small">Cron-Aufruf (z.B. stündlich):</p>
    <pre class="raw">curl -s "https://DEINE-DOMAIN<?= e(rtrim((string)app_config('base_path', ''), '/')) ?>/cron.php?token=<?= e($cronToken) ?>"</pre>
    <p class="small muted">Importiert werden dabei alle Formulare, bei denen "automatisch importieren" gesetzt ist.</p>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card__head">
    <h2>Letzte Importe</h2>
    <a class="btn btn--sec btn--sm" href="<?= e(url('admin_log', ['tab' => 'divera'])) ?>">Vollständiges Protokoll</a>
  </div>
  <?php if (!$log): ?>
    <div class="empty">Noch keine Importe protokolliert.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Zeitpunkt</th><th>Formular</th><th>Status</th><th>Meldung</th></tr></thead>
        <tbody>
        <?php foreach ($log as $l): ?>
          <tr>
            <td class="nowrap small"><?= e(de_datetime($l['created_at'])) ?></td>
            <td class="mono small"><?= e($l['form_id']) ?></td>
            <td><?= $l['status'] === 'ok'
                ? '<span class="badge" style="background:#15803d">ok</span>'
                : '<span class="badge" style="background:#b91c1c">Fehler</span>' ?></td>
            <td class="small">
              <?php if ($l['wish_id']): ?>
                <a href="<?= e(url('wish', ['id' => $l['wish_id']])) ?>"><?= e($l['message']) ?></a>
              <?php else: ?>
                <?= e($l['message']) ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
