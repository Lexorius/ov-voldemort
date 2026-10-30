<?php
/** @var array $tank @var array $vehicle @var ?array $letzte @var array $belege @var array $errors @var bool $loeschen @var bool $verwalten @var array $karten */
$isNew = empty($tank['id']);
$liter = (string)($tank['liter'] ?? '');
if ($liter !== '' && is_numeric($liter)) {
    $liter = number_format((float)$liter, 2, ',', '');
}
$betrag = $tank['betrag'] ?? null;
if ($betrag !== null && $betrag !== '' && is_numeric((string)$betrag)) {
    $betrag = number_format((float)$betrag, 2, ',', '');
}
$karten ??= [];
$karteId = (int)($tank['tankkarte_id'] ?? 0);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Tankung erfassen' : 'Tankung bearbeiten' ?></h1>
    <p class="muted small"><?= e((string)$vehicle['bezeichnung']) ?><?= $vehicle['kennzeichen'] ? ' · ' . e((string)$vehicle['kennzeichen']) : '' ?><?= $vehicle['km_stand'] !== null ? ' · Stand ' . e(tank_km_text((int)$vehicle['km_stand'])) : '' ?></p>
  </div>
  <a class="btn btn--sec" href="<?= e(url('vehicle_tanken', ['id' => $vehicle['id']])) ?>">Zurück</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="form" enctype="multipart/form-data"
      action="<?= e($isNew ? url('vehicle_tank_edit', ['vehicle_id' => $vehicle['id']]) : url('vehicle_tank_edit', ['id' => $tank['id']])) ?>">
  <?= csrf_field() ?>

  <section class="card">
    <h2>Quittung</h2>
    <div class="grid3">
      <div class="field">
        <label for="datum">Datum</label>
        <input type="date" id="datum" name="datum" required value="<?= e((string)($tank['datum'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="km_stand">Kilometerstand</label>
        <input type="number" id="km_stand" name="km_stand" min="0" inputmode="numeric" value="<?= e((string)($tank['km_stand'] ?? '')) ?>"
               placeholder="<?= $letzte && $letzte['km_stand'] !== null ? e('zuletzt ' . number_format((float)$letzte['km_stand'], 0, ',', '.')) : 'laut Tacho' ?>">
        <small class="muted">Ohne Stand lässt sich kein Verbrauch rechnen.</small>
      </div>
      <div class="field">
        <label for="liter">Menge <span class="muted">(Liter, bei Strom kWh)</span></label>
        <input type="text" id="liter" name="liter" required inputmode="decimal" value="<?= e($liter) ?>" placeholder="z. B. 62,40">
      </div>
    </div>
    <div class="grid3">
      <div class="field">
        <label for="art">Was</label>
        <select id="art" name="art">
          <?php foreach (TANK_ARTEN as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= (string)($tank['art'] ?? 'kraftstoff') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field field--check" style="align-self:end">
        <input type="checkbox" id="voll" name="voll" value="1" <?= !empty($tank['voll']) ? 'checked' : '' ?>>
        <label for="voll">Vollgetankt / voll geladen</label>
      </div>
      <div class="field">
        <label for="betrag">Betrag <span class="muted small">(optional)</span></label>
        <input type="text" id="betrag" name="betrag" inputmode="decimal" value="<?= e((string)($betrag ?? '')) ?>" placeholder="leer bei Tankkarte">
        <small class="muted">Mit Tankkarte (DKV) steht oft kein Betrag auf dem Beleg – dann leer lassen.</small>
      </div>
    </div>
    <p class="small muted">Der Verbrauch wird von Volltankung zu Volltankung gerechnet. Nur nachgefüllt? Dann den Haken weglassen,
      die Menge zählt zur Strecke bis zur nächsten vollen Tankung. Strom wird getrennt in kWh je 100 km gerechnet.</p>
  </section>

  <section class="card">
    <h2>Woher</h2>
    <div class="grid2">
      <div class="field">
        <label for="tankkarte_id">Tankkarte</label>
        <select id="tankkarte_id" name="tankkarte_id">
          <option value="">– keine / bar –</option>
          <?php foreach ($karten as $gruppe => $liste): ?>
            <optgroup label="<?= e((string)$gruppe) ?>">
              <?php foreach ($liste as $k): ?>
                <option value="<?= (int)$k['id'] ?>"<?= (int)$k['id'] === $karteId ? ' selected' : '' ?>><?= e(trim((string)($k['anbieter_label'] ?? '') . ' ' . (string)$k['nummer'])) ?><?= tankkarte_laedt($k) ? ' ⚡' : '' ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <?php if (!$karten): ?><small class="muted">Noch keine Tankkarte angelegt – das geht im Modul Tankkarten.</small><?php endif; ?>
      </div>
      <div class="field">
        <label for="tankstelle">Tankstelle / Ladesäule</label>
        <input type="text" id="tankstelle" name="tankstelle" maxlength="150" value="<?= e((string)($tank['tankstelle'] ?? '')) ?>" placeholder="z. B. Aral Heilbronn Süd">
      </div>
      <div class="field">
        <label for="beleg_nr">Belegnummer</label>
        <input type="text" id="beleg_nr" name="beleg_nr" maxlength="80" class="mono" value="<?= e((string)($tank['beleg_nr'] ?? '')) ?>">
      </div>
    </div>
    <div class="field">
      <label for="notiz">Bemerkung</label>
      <textarea id="notiz" name="notiz" rows="2" placeholder="z. B. Rückfahrt vom Einsatz, Anhänger dran"><?= e((string)($tank['notiz'] ?? '')) ?></textarea>
    </div>
    <div class="field">
      <label for="belege">Beleg fotografieren oder hochladen <span class="muted small">(optional, mehrere möglich)</span></label>
      <input type="file" id="belege" name="belege[]" multiple accept="image/*,.pdf"
             data-max-mb="<?= (int)setting_int('upload_max_mb', 10) ?>">
      <small class="muted">Am Handy geht auch die Kamera. Die Belege hängen an der Tankung und tauchen nicht in der Bildergalerie des Fahrzeugs auf.</small>
    </div>
  </section>

  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Tankung erfassen' : 'Speichern' ?></button>
    <a class="btn btn--sec" href="<?= e(url('vehicle_tanken', ['id' => $vehicle['id']])) ?>">Abbrechen</a>
  </div>
</form>

<?php if ($belege): ?>
  <section class="card" id="belege">
    <div class="card__head"><h2>Belege an dieser Tankung</h2></div>
    <ul style="list-style:none;padding:0;margin:0">
      <?php foreach ($belege as $f): ?>
        <li class="small" style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;padding:.25rem 0">
          <?php if ((string)$f['art'] === 'bild'): ?>
            <a href="<?= e(url('vehicle_file', ['id' => $f['id']])) ?>" target="_blank" rel="noopener"><img src="<?= e(url('vehicle_file', ['id' => $f['id'], 'vorschau' => 1])) ?>" alt="" style="height:48px;border-radius:6px"></a>
          <?php endif; ?>
          <a href="<?= e(url('vehicle_file', ['id' => $f['id']])) ?>" target="_blank" rel="noopener"><?= e((string)$f['orig_name']) ?></a>
          <span class="muted">· <?= e(bytes_human((int)$f['size_bytes'])) ?></span>
          <?php if ($verwalten): ?>
            <form method="post" action="<?= e(url('vehicle_action')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="file_delete">
              <input type="hidden" name="file_id" value="<?= (int)$f['id'] ?>">
              <button class="btn btn--sm btn--sec" type="submit" data-confirm="Beleg entfernen?">Entfernen</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($loeschen): ?>
  <form method="post" action="<?= e(url('vehicle_action')) ?>" class="form" style="margin-top:1rem">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="tank_delete">
    <input type="hidden" name="tank_id" value="<?= (int)$tank['id'] ?>">
    <button class="btn btn--sec btn--sm" type="submit" data-confirm="Tankung samt Belegen löschen? Im Journal bleibt, dass es sie gab.">Tankung löschen</button>
  </form>
<?php endif; ?>
