<?php
/** @var array $gruppe @var array $errors @var array $fahrzeuge @var array $fachgruppen @var array $personen */
$isNew = empty($gruppe['id']);
$zielTyp = radio_ziel_typ((string)($gruppe['ziel_typ'] ?? 'ov'));
$zielId = (int)($gruppe['ziel_id'] ?? 0);
?>
<div class="pagehead">
  <div><h1><?= $isNew ? 'Gruppe anlegen' : 'Gruppe bearbeiten' ?></h1>
    <p>Eine Gruppe fasst Geräte zusammen, die zusammen liegen: ein HRT-Koffer, eine Ladeschale,
       der Satz im Fahrzeug. Sie kann einen eigenen QR-Code bekommen – dann meldet ein Tipp alle
       Geräte auf einmal als „am Lagerort".</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e($isNew ? url('radios') : url('radio_group', ['id' => $gruppe['id']])) ?>">Abbrechen</a>
  </div>
</div>

<?php foreach ($errors as $f): ?>
  <div class="alert alert--error"><?= e($f) ?></div>
<?php endforeach; ?>

<form method="post" class="form">
  <?= csrf_field() ?>

  <section class="card">
    <h2>Gruppe</h2>
    <div class="field">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" required maxlength="150"
             value="<?= e((string)($gruppe['name'] ?? '')) ?>" placeholder="z. B. HRT-Koffer Zugtrupp">
    </div>
    <div class="field">
      <label for="lagerort">Lagerort</label>
      <input type="text" id="lagerort" name="lagerort" maxlength="150"
             value="<?= e((string)($gruppe['lagerort'] ?? '')) ?>" placeholder="z. B. Funkraum, Regal 2">
    </div>
    <div class="field">
      <label for="beschreibung">Beschreibung</label>
      <textarea id="beschreibung" name="beschreibung" rows="2"><?= e((string)($gruppe['beschreibung'] ?? '')) ?></textarea>
    </div>
  </section>

  <section class="card">
    <h2>Zuordnung</h2>
    <div class="field">
      <label for="ziel_typ">Gehört zu</label>
      <select id="ziel_typ" name="ziel_typ" data-ziel-auswahl>
        <?php foreach (RADIO_ZIELE as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $key === $zielTyp ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" data-ziel-feld="fahrzeug"<?= $zielTyp === 'fahrzeug' ? '' : ' hidden' ?>>
      <label for="ziel_fahrzeug">Fahrzeug</label>
      <select id="ziel_fahrzeug" name="ziel_id_fahrzeug">
        <option value="">– bitte wählen –</option>
        <?php foreach ($fahrzeuge as $v): ?>
          <option value="<?= (int)$v['id'] ?>" <?= $zielTyp === 'fahrzeug' && (int)$v['id'] === $zielId ? 'selected' : '' ?>>
            <?= e((string)$v['bezeichnung']) ?><?= $v['kennzeichen'] ? ' · ' . e((string)$v['kennzeichen']) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" data-ziel-feld="fachgruppe"<?= $zielTyp === 'fachgruppe' ? '' : ' hidden' ?>>
      <label for="ziel_fachgruppe">Fachgruppe</label>
      <select id="ziel_fachgruppe" name="ziel_id_fachgruppe">
        <option value="">– bitte wählen –</option>
        <?php foreach ($fachgruppen as $fg): ?>
          <option value="<?= (int)$fg['id'] ?>" <?= $zielTyp === 'fachgruppe' && (int)$fg['id'] === $zielId ? 'selected' : '' ?>>
            <?= e((string)$fg['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" data-ziel-feld="person"<?= $zielTyp === 'person' ? '' : ' hidden' ?>>
      <label for="ziel_person">Person</label>
      <select id="ziel_person" name="ziel_id_person">
        <option value="">– bitte wählen –</option>
        <?php foreach ($personen as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= $zielTyp === 'person' && (int)$u['id'] === $zielId ? 'selected' : '' ?>>
            <?= e((string)$u['display_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <input type="hidden" name="ziel_id" value="<?= $zielId ?: '' ?>">
  </section>

  <section class="card">
    <h2>Sonstiges</h2>
    <div class="field field--check">
      <input type="checkbox" id="is_active" name="is_active" value="1"
             <?= (int)($gruppe['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
      <label for="is_active">In Betrieb</label>
    </div>
    <div class="btnrow">
      <button class="btn" type="submit">Speichern</button>
      <a class="btn btn--sec" href="<?= e($isNew ? url('radios') : url('radio_group', ['id' => $gruppe['id']])) ?>">Abbrechen</a>
    </div>
  </section>
</form>

<?php if (!$isNew): ?>
  <section class="card">
    <h2>Gruppe löschen</h2>
    <p class="small">Die Geräte bleiben bestehen und sind danach keiner Gruppe mehr zugeordnet.
      Ein QR-Code der Gruppe wird ungültig.</p>
    <form method="post" action="<?= e(url('radio_action')) ?>" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="loeschen">
      <input type="hidden" name="group_id" value="<?= (int)$gruppe['id'] ?>">
      <button class="btn btn--danger" type="submit"
              data-confirm="Diese Gruppe löschen? Die Geräte bleiben bestehen."
              data-confirm2="Bist du wirklich sicher?">Löschen</button>
    </form>
  </section>
<?php endif; ?>
