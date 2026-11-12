<?php
/** @var array $kat @var array $rows @var array $ausser @var bool $geerbt @var bool $leer @var array $errors */
$isNew = empty($kat['id']);
$zurueck = $isNew
    ? url('ablage', !empty($kat['parent_id']) ? ['k' => 'k' . (int)$kat['parent_id']] : [])
    : url('ablage', ['k' => 'k' . (int)$kat['id']]);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Kategorie anlegen' : 'Kategorie bearbeiten' ?></h1>
    <p class="muted small">Eigene Kategorien der Dateiablage lassen sich beliebig tief schachteln. Die Kategorien mit Schloss kommen aus den Modulen und stehen hier nicht zur Wahl.</p>
  </div>
  <a class="btn btn--sec" href="<?= e($zurueck) ?>">Abbrechen</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="form" action="<?= e($isNew ? url('ablage_kategorie_edit') : url('ablage_kategorie_edit', ['id' => $kat['id']])) ?>">
  <?= csrf_field() ?>
  <section class="card">
    <div class="grid2">
      <div class="field">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required maxlength="150" value="<?= e((string)($kat['name'] ?? '')) ?>" placeholder="z. B. Verträge" autofocus>
      </div>
      <div class="field">
        <label for="parent_id">Liegt unter</label>
        <select id="parent_id" name="parent_id"><?= ablage_kategorie_optionen($rows, (int)($kat['parent_id'] ?? 0) ?: null, '– oberste Ebene –', $ausser) ?></select>
        <?php if (!$isNew): ?><small class="muted">Umhängen nimmt Unterkategorien und Dokumente mit.</small><?php endif; ?>
      </div>
    </div>
    <div class="field">
      <label for="beschreibung">Beschreibung <span class="muted">(freiwillig)</span></label>
      <textarea id="beschreibung" name="beschreibung" rows="2" maxlength="500" placeholder="Was gehört hier hinein?"><?= e((string)($kat['beschreibung'] ?? '')) ?></textarea>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="nur_leitung" name="nur_leitung" value="1" <?= !empty($kat['nur_leitung']) ? 'checked' : '' ?>>
      <label for="nur_leitung">Nur für die Leitung sichtbar – samt allen Unterkategorien und Dokumenten</label>
    </div>
    <?php if ($geerbt): ?><p class="small muted">Die Kategorie darüber ist bereits nur für die Leitung sichtbar; das gilt hier auch ohne Haken.</p><?php endif; ?>
    <div class="btnrow">
      <button class="btn" type="submit"><?= $isNew ? 'Anlegen' : 'Speichern' ?></button>
      <a class="btn btn--sec" href="<?= e($zurueck) ?>">Abbrechen</a>
    </div>
  </section>
</form>

<?php if (!$isNew): ?>
  <section class="card">
    <h2>Löschen</h2>
    <?php if ($leer): ?>
      <form method="post" action="<?= e(url('ablage_kategorie_edit', ['id' => $kat['id']])) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="loeschen">
        <button class="btn btn--sec" type="submit" data-confirm="Kategorie „<?= e((string)$kat['name']) ?>“ löschen?">Kategorie löschen</button>
      </form>
    <?php else: ?>
      <p class="small muted" style="margin:0">Löschen geht nur leer: erst Unterkategorien umhängen oder löschen und Dokumente verschieben oder entfernen.</p>
    <?php endif; ?>
  </section>
<?php endif; ?>
