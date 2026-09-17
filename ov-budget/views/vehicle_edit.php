<?php
/** @var array $vehicle @var array $errors @var array $felder @var array $extra */
$isNew = empty($vehicle['id']);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Fahrzeug anlegen' : 'Fahrzeug bearbeiten' ?></h1>
    <?php if (!$isNew): ?>
      <p class="muted small">Jede Änderung an den Stammdaten steht anschließend im Journal der Akte.</p>
    <?php endif; ?>
  </div>
  <a class="btn btn--sec" href="<?= e($isNew ? url('vehicles') : url('vehicle', ['id' => $vehicle['id']])) ?>">Zurück</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="form" action="<?= e(url('vehicle_edit', $isNew ? [] : ['id' => $vehicle['id']])) ?>">
  <?= csrf_field() ?>

  <section class="card">
    <h2>Fahrzeug</h2>
    <div class="grid2">
      <div class="field">
        <label for="bezeichnung">Bezeichnung</label>
        <input type="text" id="bezeichnung" name="bezeichnung" required
               value="<?= e((string)$vehicle['bezeichnung']) ?>" placeholder="GKW 1">
      </div>
      <div class="field">
        <label for="funkrufname">Funkrufname</label>
        <input type="text" id="funkrufname" name="funkrufname" value="<?= e((string)$vehicle['funkrufname']) ?>"
               placeholder="Heros Musterstadt 24/51">
      </div>
      <div class="field">
        <label for="kennzeichen">Kennzeichen</label>
        <input type="text" id="kennzeichen" name="kennzeichen" value="<?= e((string)$vehicle['kennzeichen']) ?>"
               placeholder="THW-12345">
      </div>
      <div class="field">
        <label for="kennung">Kennung / Inventarnummer</label>
        <input type="text" id="kennung" name="kennung" value="<?= e((string)$vehicle['kennung']) ?>">
      </div>
      <div class="field">
        <label for="typ_id">Art</label>
        <select id="typ_id" name="typ_id"><?= list_options('fahrzeug_typ', (int)($vehicle['typ_id'] ?? 0)) ?></select>
      </div>
      <div class="field">
        <label for="fachgruppe_id">Fachgruppe</label>
        <select id="fachgruppe_id" name="fachgruppe_id"><?= list_options('fachgruppe', (int)($vehicle['fachgruppe_id'] ?? 0)) ?></select>
      </div>
      <div class="field">
        <label for="status_id">Status</label>
        <select id="status_id" name="status_id"><?= list_options('fahrzeug_status', (int)($vehicle['status_id'] ?? 0), '') ?></select>
        <?php if (!empty($vehicle['stein_asset_id'])): ?>
          <small class="muted">Der Abgleich mit der Stein.APP überschreibt den Status beim nächsten Abruf.</small>
        <?php endif; ?>
      </div>
      <div class="field">
        <label for="standort">Standort</label>
        <input type="text" id="standort" name="standort" value="<?= e((string)$vehicle['standort']) ?>"
               placeholder="Halle 1, Stellplatz 3">
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Technik</h2>
    <div class="grid3">
      <div class="field">
        <label for="hersteller">Hersteller</label>
        <input type="text" id="hersteller" name="hersteller" value="<?= e((string)$vehicle['hersteller']) ?>">
      </div>
      <div class="field">
        <label for="modell">Modell</label>
        <input type="text" id="modell" name="modell" value="<?= e((string)$vehicle['modell']) ?>">
      </div>
      <div class="field">
        <label for="baujahr">Baujahr</label>
        <input type="number" id="baujahr" name="baujahr" min="1900" max="2100"
               value="<?= e((string)($vehicle['baujahr'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="fahrgestellnummer">Fahrgestellnummer</label>
        <input type="text" id="fahrgestellnummer" name="fahrgestellnummer" value="<?= e((string)$vehicle['fahrgestellnummer']) ?>">
      </div>
      <div class="field">
        <label for="erstzulassung">Erstzulassung</label>
        <input type="date" id="erstzulassung" name="erstzulassung" value="<?= e((string)($vehicle['erstzulassung'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="km_stand">Kilometerstand</label>
        <input type="number" id="km_stand" name="km_stand" min="0" value="<?= e((string)($vehicle['km_stand'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="betriebsstunden">Betriebsstunden</label>
        <input type="number" id="betriebsstunden" name="betriebsstunden" min="0"
               value="<?= e((string)($vehicle['betriebsstunden'] ?? '')) ?>">
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Fristen</h2>
    <p class="small muted">HU und SP füllt der Abgleich mit der Stein.APP selbst, sobald das Fahrzeug verknüpft ist.</p>
    <div class="grid3">
      <div class="field">
        <label for="hu_bis">HU gültig bis</label>
        <input type="date" id="hu_bis" name="hu_bis" value="<?= e((string)($vehicle['hu_bis'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="sp_bis">SP gültig bis</label>
        <input type="date" id="sp_bis" name="sp_bis" value="<?= e((string)($vehicle['sp_bis'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="uvv_bis">UVV gültig bis</label>
        <input type="date" id="uvv_bis" name="uvv_bis" value="<?= e((string)($vehicle['uvv_bis'] ?? '')) ?>">
      </div>
    </div>
  </section>

  <?php if ($felder): ?>
    <section class="card">
      <h2>Weitere Angaben</h2>
      <div class="grid2">
        <?php foreach ($felder as $key => $def): $val = (string)($extra[$key] ?? ''); ?>
          <div class="field<?= $def['type'] === 'bool' ? ' field--check' : '' ?>">
            <?php if ($def['type'] === 'bool'): ?>
              <input type="checkbox" id="extra_<?= e($key) ?>" name="extra_<?= e($key) ?>" value="1" <?= $val === '1' ? 'checked' : '' ?>>
              <label for="extra_<?= e($key) ?>"><?= e($def['label']) ?></label>
            <?php elseif ($def['type'] === 'textarea'): ?>
              <label for="extra_<?= e($key) ?>"><?= e($def['label']) ?></label>
              <textarea id="extra_<?= e($key) ?>" name="extra_<?= e($key) ?>"><?= e($val) ?></textarea>
            <?php else: ?>
              <label for="extra_<?= e($key) ?>"><?= e($def['label']) ?></label>
              <input type="<?= $def['type'] === 'date' ? 'date' : ($def['type'] === 'number' ? 'number' : 'text') ?>"
                     id="extra_<?= e($key) ?>" name="extra_<?= e($key) ?>" value="<?= e($val) ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="card">
    <h2>Sonstiges</h2>
    <div class="field">
      <label for="notiz">Bemerkung</label>
      <textarea id="notiz" name="notiz" rows="3"><?= e((string)$vehicle['notiz']) ?></textarea>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($vehicle['is_active']) ? 'checked' : '' ?>>
      <label for="is_active">Im Dienst</label>
    </div>
    <div class="field">
      <label for="ausgemustert_am">Ausgemustert am</label>
      <input type="date" id="ausgemustert_am" name="ausgemustert_am" value="<?= e((string)($vehicle['ausgemustert_am'] ?? '')) ?>">
      <small class="muted">Nur nötig, wenn das Fahrzeug nicht mehr im Dienst ist. Die Akte bleibt erhalten.</small>
    </div>
    <div class="btnrow">
      <button class="btn" type="submit">Speichern</button>
      <a class="btn btn--sec" href="<?= e($isNew ? url('vehicles') : url('vehicle', ['id' => $vehicle['id']])) ?>">Abbrechen</a>
    </div>
  </section>
</form>
