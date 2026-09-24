<?php
/** @var array $radio @var array $errors @var array $fahrzeuge @var array $fachgruppen @var array $personen */
$isNew = empty($radio['id']);
$zielTyp = radio_ziel_typ((string)($radio['ziel_typ'] ?? 'ov'));
$zielId = (int)($radio['ziel_id'] ?? 0);
?>
<div class="pagehead">
  <div><h1><?= $isNew ? 'Funkgerät anlegen' : 'Funkgerät bearbeiten' ?></h1></div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e($isNew ? url('radios') : url('radio', ['id' => $radio['id']])) ?>">Abbrechen</a>
  </div>
</div>

<?php foreach ($errors as $f): ?>
  <div class="alert alert--error"><?= e($f) ?></div>
<?php endforeach; ?>

<form method="post" class="form">
  <?= csrf_field() ?>

  <section class="card">
    <h2>Gerät</h2>
    <div class="field">
      <label for="bezeichnung">Bezeichnung</label>
      <input type="text" id="bezeichnung" name="bezeichnung" required maxlength="150"
             value="<?= e((string)($radio['bezeichnung'] ?? '')) ?>" placeholder="z. B. MRT GKW 1, HRT 03">
    </div>
    <div class="grid2">
      <div class="field">
        <label for="typ_id">Art</label>
        <select id="typ_id" name="typ_id"><?= list_options('funk_typ', (int)($radio['typ_id'] ?? 0)) ?></select>
      </div>
      <div class="field">
        <label for="status_id">Status</label>
        <select id="status_id" name="status_id"><?= list_options('funk_status', (int)($radio['status_id'] ?? 0)) ?></select>
      </div>
      <div class="field">
        <label for="hersteller">Hersteller</label>
        <input type="text" id="hersteller" name="hersteller" maxlength="80"
               value="<?= e((string)($radio['hersteller'] ?? '')) ?>" placeholder="z. B. Motorola">
      </div>
      <div class="field">
        <label for="modell">Modell</label>
        <input type="text" id="modell" name="modell" maxlength="80"
               value="<?= e((string)($radio['modell'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="seriennummer">Seriennummer</label>
        <input type="text" id="seriennummer" name="seriennummer" maxlength="60"
               value="<?= e((string)($radio['seriennummer'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="inventarnummer">Inventarnummer</label>
        <input type="text" id="inventarnummer" name="inventarnummer" maxlength="60"
               value="<?= e((string)($radio['inventarnummer'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="funkrufname">Funkrufname</label>
        <input type="text" id="funkrufname" name="funkrufname" maxlength="80"
               value="<?= e((string)($radio['funkrufname'] ?? '')) ?>" placeholder="z. B. Heros Musterstadt 24/51">
      </div>
      <div class="field">
        <label for="group_id">Gruppe</label>
        <select id="group_id" name="group_id">
          <option value="">– keine –</option>
          <?php foreach (radio_group_all(false) as $g): ?>
            <option value="<?= (int)$g['id'] ?>" <?= (int)$g['id'] === (int)($radio['group_id'] ?? 0) ? 'selected' : '' ?>>
              <?= e((string)$g['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="muted">Koffer, Ladeschale oder Satz – die Gruppe kann einen eigenen QR-Code haben.</small>
      </div>
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

    <div class="field">
      <label for="standort">Standort</label>
      <input type="text" id="standort" name="standort" maxlength="150"
             value="<?= e((string)($radio['standort'] ?? '')) ?>" placeholder="z. B. Ladeschale Funkraum">
    </div>
  </section>

  <section class="card">
    <h2>Fristen</h2>
    <div class="grid2">
      <div class="field">
        <label for="beschafft_am">Beschafft am</label>
        <input type="date" id="beschafft_am" name="beschafft_am"
               value="<?= e((string)($radio['beschafft_am'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="pruefung_bis">Prüfung bis</label>
        <input type="date" id="pruefung_bis" name="pruefung_bis"
               value="<?= e((string)($radio['pruefung_bis'] ?? '')) ?>">
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Sonstiges</h2>
    <div class="field">
      <label for="notiz">Notiz</label>
      <textarea id="notiz" name="notiz" rows="2"><?= e((string)($radio['notiz'] ?? '')) ?></textarea>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="is_active" name="is_active" value="1"
             <?= (int)($radio['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
      <label for="is_active">Im Bestand</label>
    </div>
    <div class="btnrow">
      <button class="btn" type="submit">Speichern</button>
      <a class="btn btn--sec" href="<?= e($isNew ? url('radios') : url('radio', ['id' => $radio['id']])) ?>">Abbrechen</a>
    </div>
  </section>
</form>

<?php if (!$isNew): ?>
  <section class="card">
    <h2>Funkgerät löschen</h2>
    <p class="small">Karten aus dem Gerät liegen danach wieder frei. Wer das Gerät nur aussortiert,
      nimmt es besser aus dem Bestand – dann bleibt es zum Nachschlagen erhalten.</p>
    <form method="post" action="<?= e(url('radio_action')) ?>" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="loeschen">
      <input type="hidden" name="radio_id" value="<?= (int)$radio['id'] ?>">
      <button class="btn btn--danger" type="submit"
              data-confirm="Dieses Funkgerät löschen?"
              data-confirm2="Bist du wirklich sicher?">Löschen</button>
    </form>
  </section>
<?php endif; ?>
