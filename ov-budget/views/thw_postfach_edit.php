<?php
/** @var array $p @var array $errors @var array $alle */
$isNew = empty($p['id']);
?>
<div class="pagehead">
  <h1><?= $isNew ? 'Postfach anlegen' : 'Postfach bearbeiten' ?></h1>
  <a class="btn btn--sec" href="<?= e(url('thw')) ?>">Abbrechen</a>
</div>
<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="post" class="card form" action="<?= e(url('thw_postfach_edit', $isNew ? [] : ['id' => $p['id']])) ?>">
  <?= csrf_field() ?>
  <div class="grid2">
    <div class="field">
      <label for="name">Bezeichnung *</label>
      <input type="text" id="name" name="name" required maxlength="150" value="<?= e((string)$p['name']) ?>" placeholder="z. B. Einsatz, Verwaltung, Ausbildung">
    </div>
    <div class="field">
      <label for="email">E-Mail-Adresse *</label>
      <input type="email" id="email" name="email" required maxlength="150" value="<?= e((string)$p['email']) ?>" placeholder="z. B. einsatz.rst-heilbronn@thw.de">
    </div>
    <div class="field">
      <label for="dienststelle_id">Dienststelle</label>
      <select id="dienststelle_id" name="dienststelle_id"><?= standort_optionen($alle, (int)($p['dienststelle_id'] ?? 0), '– keiner Dienststelle zugeordnet –') ?></select>
    </div>
    <div class="field">
      <label for="sort_order">Reihenfolge</label>
      <input type="number" id="sort_order" name="sort_order" value="<?= (int)($p['sort_order'] ?? 0) ?>">
    </div>
  </div>
  <div class="field">
    <label for="beschreibung">Wofür ist das Postfach da?</label>
    <input type="text" id="beschreibung" name="beschreibung" maxlength="500" value="<?= e((string)($p['beschreibung'] ?? '')) ?>" placeholder="z. B. Einsatzabrechnungen und Alarmierungen der Regionalstelle">
  </div>
  <fieldset class="field" style="border:0;padding:0;margin:0 0 .8rem">
    <legend class="small" style="font-weight:600;margin-bottom:.3rem">Erreichbar über</legend>
    <div class="chips">
      <label class="chip<?= !empty($p['offiziell']) ? ' is-on' : '' ?>"><input type="checkbox" name="offiziell" value="1"<?= !empty($p['offiziell']) ? ' checked' : '' ?>> offizielles Netz (Internet)</label>
      <label class="chip<?= !empty($p['behoerdennetz']) ? ' is-on' : '' ?>"><input type="checkbox" name="behoerdennetz" value="1"<?= !empty($p['behoerdennetz']) ? ' checked' : '' ?>> Behördennetz</label>
    </div>
    <small>Beides anhaken, wenn das Postfach aus beiden Netzen erreichbar ist.</small>
  </fieldset>
  <div class="grid2">
    <div class="field">
      <label for="notiz">Notiz</label>
      <textarea id="notiz" name="notiz" rows="2"><?= e((string)($p['notiz'] ?? '')) ?></textarea>
    </div>
    <div class="field field--check" style="align-self:end">
      <input type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($p['is_active']) ? 'checked' : '' ?>>
      <label for="is_active">Aktiv</label>
    </div>
  </div>
  <div class="btnrow">
    <button class="btn" type="submit">Speichern</button>
    <a class="btn btn--sec" href="<?= e(url('thw')) ?>">Abbrechen</a>
  </div>
</form>
<?php if (!$isNew): ?>
  <form method="post" class="card" action="<?= e(url('thw_postfach_edit', ['id' => $p['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <button class="btn btn--danger" type="submit" data-confirm="Dieses Postfach löschen?">Postfach löschen</button>
  </form>
<?php endif; ?>
