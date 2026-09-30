<?php
/** @var array $satz @var array $errors */
$isNew = empty($satz['id']);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Tagessatz anlegen' : 'Tagessatz bearbeiten' ?></h1>
    <p>Die drei Anteile ergeben zusammen 100 Prozent. Üblich sind 20 % Frühstück, 40 % Mittag,
       40 % Abend.</p>
  </div>
  <div class="btnrow"><a class="btn btn--sec" href="<?= e(url('verpflegung_saetze')) ?>">Abbrechen</a></div>
</div>

<?php foreach ($errors as $f): ?>
  <div class="alert alert--error"><?= e($f) ?></div>
<?php endforeach; ?>

<form method="post" class="form">
  <?= csrf_field() ?>
  <section class="card">
    <div class="grid2">
      <div class="field">
        <label for="tagessatz">Tagessatz je Person (€)</label>
        <input type="text" inputmode="decimal" id="tagessatz" name="tagessatz" required placeholder="0,00"
               value="<?= e(num_input($satz['tagessatz'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="notiz">Notiz</label>
        <input type="text" id="notiz" name="notiz" maxlength="150" value="<?= e((string)($satz['notiz'] ?? '')) ?>"
               placeholder="z. B. Erlass vom …">
      </div>
      <div class="field">
        <label for="gueltig_von">Gültig ab</label>
        <input type="date" id="gueltig_von" name="gueltig_von" required value="<?= e((string)$satz['gueltig_von']) ?>">
      </div>
      <div class="field">
        <label for="gueltig_bis">Gültig bis</label>
        <input type="date" id="gueltig_bis" name="gueltig_bis" value="<?= e((string)($satz['gueltig_bis'] ?? '')) ?>">
        <small>Leer = bis auf Weiteres.</small>
      </div>
    </div>
    <div class="grid3">
      <?php foreach (['fruehstueck' => 'Frühstück', 'mittag' => 'Mittagessen', 'abend' => 'Abendessen'] as $k => $label): ?>
        <div class="field">
          <label for="anteil_<?= e($k) ?>"><?= e($label) ?> (%)</label>
          <input type="number" id="anteil_<?= e($k) ?>" name="anteil_<?= e($k) ?>" min="0" max="100" required
                 value="<?= (int)($satz['anteil_' . $k] ?? 0) ?>">
        </div>
      <?php endforeach; ?>
    </div>
    <div class="btnrow">
      <button class="btn" type="submit">Speichern</button>
      <a class="btn btn--sec" href="<?= e(url('verpflegung_saetze')) ?>">Abbrechen</a>
    </div>
  </section>
</form>

<?php if (!$isNew): ?>
  <section class="card">
    <h2>Tagessatz löschen</h2>
    <p class="small">Veranstaltungen in diesem Zeitraum rechnen dann nichts mehr aus.</p>
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="loeschen">
      <button class="btn btn--danger" type="submit" data-confirm="Diesen Tagessatz löschen?">Löschen</button>
    </form>
  </section>
<?php endif; ?>
