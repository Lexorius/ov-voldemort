<?php
/** @var array $d @var array $errors @var array $alle @var array $ausser */
$isNew = empty($d['id']);
?>
<div class="pagehead">
  <h1><?= $isNew ? 'Dienststelle anlegen' : 'Dienststelle bearbeiten' ?></h1>
  <a class="btn btn--sec" href="<?= e(url('thw')) ?>">Abbrechen</a>
</div>
<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="post" class="card form" action="<?= e(url('thw_dienststelle_edit', $isNew ? [] : ['id' => $d['id']])) ?>">
  <?= csrf_field() ?>
  <div class="grid2">
    <div class="field">
      <label for="name">Name *</label>
      <input type="text" id="name" name="name" required maxlength="150" value="<?= e((string)$d['name']) ?>" placeholder="z. B. Regionalstelle Heilbronn">
    </div>
    <div class="field">
      <label for="kurz">Kürzel</label>
      <input type="text" id="kurz" name="kurz" maxlength="30" value="<?= e((string)($d['kurz'] ?? '')) ?>" placeholder="z. B. RSt HN">
    </div>
    <div class="field">
      <label for="typ">Art</label>
      <select id="typ" name="typ">
        <?php foreach (THW_TYPEN as $key => $t): ?><option value="<?= e($key) ?>"<?= thw_typ((string)($d['typ'] ?? '')) === $key ? ' selected' : '' ?>><?= e($t['icon'] . ' ' . $t['label']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="parent_id">Hängt unter</label>
      <select id="parent_id" name="parent_id"><?= standort_optionen($alle, (int)($d['parent_id'] ?? 0), '– ganz oben –', $ausser) ?></select>
      <small>Ortsverbände unter ihrer Regionalstelle, Regionalstellen unter dem Landesverband.</small>
    </div>
    <div class="field">
      <label for="strasse">Straße</label>
      <input type="text" id="strasse" name="strasse" maxlength="150" value="<?= e((string)($d['strasse'] ?? '')) ?>">
    </div>
    <div class="field">
      <label for="plz">PLZ und Ort</label>
      <div style="display:flex;gap:.4rem"><input type="text" id="plz" name="plz" maxlength="15" value="<?= e((string)($d['plz'] ?? '')) ?>" style="width:6rem" placeholder="PLZ">
        <input type="text" id="ort" name="ort" maxlength="100" value="<?= e((string)($d['ort'] ?? '')) ?>" placeholder="Ort" aria-label="Ort"></div>
    </div>
    <div class="field">
      <label for="telefon">Telefon</label>
      <input type="text" id="telefon" name="telefon" maxlength="60" value="<?= e((string)($d['telefon'] ?? '')) ?>">
    </div>
    <div class="field">
      <label for="email">E-Mail (allgemein)</label>
      <input type="email" id="email" name="email" maxlength="150" value="<?= e((string)($d['email'] ?? '')) ?>">
      <small>Funktionspostfächer kommen gesondert dazu – mit Zweck und Erreichbarkeit.</small>
    </div>
    <div class="field">
      <label for="web">Website</label>
      <input type="text" id="web" name="web" maxlength="200" value="<?= e((string)($d['web'] ?? '')) ?>" placeholder="https://…">
    </div>
    <div class="field">
      <label for="sort_order">Reihenfolge</label>
      <input type="number" id="sort_order" name="sort_order" value="<?= (int)($d['sort_order'] ?? 0) ?>">
    </div>
    <div class="field field--check">
      <input type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($d['is_active']) ? 'checked' : '' ?>>
      <label for="is_active">Aktiv</label>
    </div>
  </div>
  <div class="field">
    <label for="notiz">Notiz</label>
    <textarea id="notiz" name="notiz" rows="2"><?= e((string)($d['notiz'] ?? '')) ?></textarea>
  </div>
  <?php $kontaktOptionen ??= []; $kontakteGewaehlt ??= []; if ($kontaktOptionen && can('view_contacts')): ?>
    <h2 class="mt">Ansprechpartner aus dem Kontaktmodul</h2>
    <?= render_partial('partials/wahl_liste', ['name' => 'kontakte', 'feld' => 'kontakte_feld', 'label' => 'Kontakte dieser Dienststelle', 'optionen' => $kontaktOptionen, 'gewaehlt' => $kontakteGewaehlt, 'leer' => '– Kontakt wählen –',
        'hinweis' => 'Etwa Ortsbeauftragte oder Sachbearbeiter – dieselbe Zuordnung lässt sich auch im Kontakt selbst setzen.']) ?>
  <?php endif; ?>
  <div class="btnrow">
    <button class="btn" type="submit">Speichern</button>
    <a class="btn btn--sec" href="<?= e(url('thw')) ?>">Abbrechen</a>
  </div>
</form>
<?php if (!$isNew): ?>
  <form method="post" class="card" action="<?= e(url('thw_dienststelle_edit', ['id' => $d['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <button class="btn btn--danger" type="submit" data-confirm="Dienststelle löschen? Postfächer bleiben erhalten, verlieren aber die Zuordnung.">Dienststelle löschen</button>
  </form>
<?php endif; ?>
