<?php
/** @var array $meeting @var array $errors */
$isNew = empty($meeting['id']);
$uhr = static fn($v) => $v ? substr((string)$v, 0, 5) : '';
?>
<div class="pagehead">
  <h1><?= $isNew ? 'Besprechung anlegen' : 'Besprechung bearbeiten' ?></h1>
  <a class="btn btn--sec" href="<?= $isNew ? e(url('meetings')) : e(url('meeting', ['id' => $meeting['id']])) ?>">Abbrechen</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card form" action="<?= e(url('meeting_edit', $isNew ? [] : ['id' => $meeting['id']])) ?>">
  <?= csrf_field() ?>
  <div class="grid2">
    <div class="field">
      <label for="titel">Titel *</label>
      <input type="text" id="titel" name="titel" required maxlength="200" value="<?= e((string)$meeting['titel']) ?>"
             placeholder="z.B. OV-Stab September">
    </div>
    <div class="field">
      <label for="typ_id">Art</label>
      <select id="typ_id" name="typ_id"><?= list_options('besprechung_typ', (int)($meeting['typ_id'] ?? 0)) ?></select>
    </div>
  </div>
  <div class="grid3">
    <div class="field">
      <label for="datum">Datum *</label>
      <input type="date" id="datum" name="datum" required value="<?= e((string)$meeting['datum']) ?>">
    </div>
    <div class="field">
      <label for="beginn">Beginn</label>
      <input type="time" id="beginn" name="beginn" value="<?= e($uhr($meeting['beginn'] ?? null)) ?>">
    </div>
    <div class="field">
      <label for="ende">Ende (geplant)</label>
      <input type="time" id="ende" name="ende" value="<?= e($uhr($meeting['ende'] ?? null)) ?>">
    </div>
    <div class="field">
      <label for="ort">Ort</label>
      <input type="text" id="ort" name="ort" value="<?= e((string)$meeting['ort']) ?>" placeholder="z.B. Unterkunft, Besprechungsraum">
    </div>
    <div class="field">
      <label for="leitung">Leitung</label>
      <input type="text" id="leitung" name="leitung" value="<?= e((string)$meeting['leitung']) ?>">
    </div>
    <div class="field">
      <label for="protokoll_von">Protokoll</label>
      <input type="text" id="protokoll_von" name="protokoll_von" value="<?= e((string)$meeting['protokoll_von']) ?>">
    </div>
  </div>
  <div class="field">
    <label for="teilnehmer">Eingeladen / Teilnehmende</label>
    <textarea id="teilnehmer" name="teilnehmer" rows="3"><?= e((string)$meeting['teilnehmer']) ?></textarea>
  </div>
  <div class="field">
    <label for="beschreibung">Hinweise zur Besprechung</label>
    <textarea id="beschreibung" name="beschreibung"><?= e((string)$meeting['beschreibung']) ?></textarea>
  </div>
  <?php if ($isNew): ?>
    <p class="small muted mb0">Ort, Leitung und Uhrzeit werden von der letzten Besprechung dieser Art übernommen, sofern es eine gibt.</p>
  <?php endif; ?>
  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Anlegen' : 'Speichern' ?></button>
  </div>
</form>

<?php if (!$isNew): ?>
  <form method="post" class="card" action="<?= e(url('meeting_edit', ['id' => $meeting['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <p class="small muted">Die Themen der Besprechung gehen nicht verloren – sie wandern zurück in den Themenspeicher.</p>
    <button class="btn btn--danger" type="submit" data-confirm="Besprechung löschen?">Besprechung löschen</button>
  </form>
<?php endif; ?>
