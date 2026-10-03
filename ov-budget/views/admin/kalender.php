<?php
/** @var array $verfuegbar @var string $listeFehler @var array $ausgewaehlt @var string $bundesland @var int $intervall
 *  @var int $letzterAbruf @var int $anzahl @var string $fehler @var string $hinweis */
$fremde = array_diff($ausgewaehlt, array_keys($verfuegbar));
?>
<div class="pagehead">
  <div>
    <h1>Kalender</h1>
    <p>Feiertage des Bundeslands und Kalender aus Home Assistant – etwa den Dienstplan, die Schulferien oder den
      Kalender der Jugendgruppe. Was hier ausgewählt ist, erscheint im Kalender für alle.</p>
  </div>
  <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Zur Verwaltung</a>
</div>

<?php if ($fehler !== ''): ?><div class="alert alert--error"><?= e($fehler) ?></div><?php endif; ?>
<?php if ($hinweis !== ''): ?><div class="alert alert--success"><?= e($hinweis) ?></div><?php endif; ?>

<form method="post" class="card form">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="speichern">
  <h2>Feiertage</h2>
  <div class="field">
    <label for="bundesland">Bundesland</label>
    <select id="bundesland" name="bundesland" style="max-width:20rem">
      <?php foreach (KALENDER_BUNDESLAENDER as $k => $name): ?>
        <option value="<?= e($k) ?>"<?= $bundesland === $k ? ' selected' : '' ?>><?= e($name) ?></option>
      <?php endforeach; ?>
    </select>
    <small>Die gesetzlichen Feiertage werden gerechnet, ohne Abruf – auch für kommende Jahre.</small>
  </div>

  <h2 class="mt">Kalender aus Home Assistant</h2>
  <?php if ($listeFehler !== ''): ?>
    <div class="alert alert--warn">Home Assistant antwortet nicht: <?= e($listeFehler) ?><br>
      <span class="small">Kalender lassen sich unten trotzdem von Hand eintragen (z. B. <span class="mono">calendar.dienstplan</span>).</span></div>
  <?php elseif (!$verfuegbar): ?>
    <div class="empty">Home Assistant kennt keine Kalender. Eine Kalender-Integration (lokaler Kalender, CalDAV, Google …) einrichten, dann erscheinen sie hier.</div>
  <?php else: ?>
    <div class="field">
      <?php foreach ($verfuegbar as $entity => $name): ?>
        <label class="wahl"><input type="checkbox" name="entitaeten[]" value="<?= e($entity) ?>"<?= in_array($entity, $ausgewaehlt, true) ? ' checked' : '' ?>>
          <?= e($name) ?> <span class="mono muted small"><?= e($entity) ?></span></label>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="grid2">
    <div class="field">
      <label for="weitere">Weitere Kalender (Entitäten, mit Komma)</label>
      <input type="text" id="weitere" name="weitere" value="<?= e(implode(', ', $fremde)) ?>" placeholder="calendar.schulferien, calendar.dienstplan">
    </div>
    <div class="field">
      <label for="intervall">Abruf alle … Minuten</label>
      <input type="number" id="intervall" name="intervall" min="5" max="1440" value="<?= (int)$intervall ?>" style="max-width:8rem">
      <small>Geholt werden 30 Tage zurück und ein Jahr voraus.</small>
    </div>
  </div>
  <div class="btnrow"><button class="btn" type="submit">Speichern</button></div>
</form>

<section class="card">
  <div class="card__head">
    <h2>Stand</h2>
    <form method="post" class="inline-form"><?= csrf_field() ?>
      <input type="hidden" name="action" value="abrufen">
      <button class="btn btn--sec btn--sm" type="submit"<?= $ausgewaehlt ? '' : ' disabled' ?>>Jetzt abrufen</button></form>
  </div>
  <dl class="dl">
    <div class="dl__item"><div class="dl__label">Ausgewählt</div><div class="dl__value"><?= count($ausgewaehlt) ?> Kalender</div></div>
    <div class="dl__item"><div class="dl__label">Einträge im Zwischenspeicher</div><div class="dl__value"><?= (int)$anzahl ?></div></div>
    <div class="dl__item"><div class="dl__label">Letzter Abruf</div><div class="dl__value"><?= $letzterAbruf > 0 ? e(de_datetime(date('Y-m-d H:i:s', $letzterAbruf))) : 'noch nie' ?></div></div>
  </dl>
</section>
