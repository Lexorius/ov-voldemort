<?php
/** @var array $order @var array $vehicle @var array $errors */
$isNew = empty($order['id']);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Auftrag anlegen' : 'Auftrag bearbeiten' ?></h1>
    <p class="muted small"><?= e($vehicle['bezeichnung']) ?><?= $vehicle['kennzeichen'] ? ' · ' . e($vehicle['kennzeichen']) : '' ?></p>
  </div>
  <a class="btn btn--sec" href="<?= e($isNew ? url('vehicle', ['id' => $vehicle['id']]) : url('vehicle_order', ['id' => $order['id']])) ?>">Zurück</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="form" enctype="multipart/form-data"
      action="<?= e($isNew ? url('vehicle_order_edit', ['vehicle_id' => $vehicle['id']]) : url('vehicle_order_edit', ['id' => $order['id']])) ?>">
  <?= csrf_field() ?>

  <section class="card">
    <h2>Vorgang</h2>
    <div class="field">
      <label for="titel">Worum geht es?</label>
      <input type="text" id="titel" name="titel" required value="<?= e((string)$order['titel']) ?>"
             placeholder="z.B. Bremsen hinten schleifen">
    </div>
    <div class="field">
      <label for="beschreibung">Beschreibung</label>
      <textarea id="beschreibung" name="beschreibung" rows="4"
                placeholder="Seit wann, unter welchen Umständen, was wurde schon geprüft?"><?= e((string)$order['beschreibung']) ?></textarea>
    </div>
    <div class="grid3">
      <div class="field">
        <label for="art_id">Art</label>
        <select id="art_id" name="art_id"><?= list_options('auftrag_art', (int)($order['art_id'] ?? 0), '') ?></select>
      </div>
      <div class="field">
        <label for="prioritaet_id">Priorität</label>
        <select id="prioritaet_id" name="prioritaet_id"><?= list_options('auftrag_prioritaet', (int)($order['prioritaet_id'] ?? 0), '') ?></select>
      </div>
      <div class="field">
        <label for="km_stand">Kilometerstand</label>
        <input type="number" id="km_stand" name="km_stand" min="0" value="<?= e((string)($order['km_stand'] ?? '')) ?>">
      </div>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="ausfall" name="ausfall" value="1" <?= !empty($order['ausfall']) ? 'checked' : '' ?>>
      <label for="ausfall">Das Fahrzeug steht deswegen still</label>
    </div>
    <?php if ($isNew): ?>
      <div class="field">
        <label for="fotos">Fotos <span class="muted small">(optional, mehrere möglich)</span></label>
        <input type="file" id="fotos" name="fotos[]" multiple
               data-max-mb="<?= (int)setting_int('upload_max_mb', 10) ?>">
        <small class="muted">Am Handy geht auch die Kamera. Die Fotos hängen am Auftrag; weitere lassen sich
          später jederzeit hinzufügen. Große Bilder werden verkleinert, Metadaten wie der Aufnahmeort fallen weg.</small>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Meldung und Werkstatt</h2>
    <div class="grid3">
      <div class="field">
        <label for="gemeldet_von">Gemeldet von</label>
        <input type="text" id="gemeldet_von" name="gemeldet_von" value="<?= e((string)$order['gemeldet_von']) ?>">
      </div>
      <div class="field">
        <label for="gemeldet_am">Gemeldet am</label>
        <input type="date" id="gemeldet_am" name="gemeldet_am" value="<?= e((string)($order['gemeldet_am'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="faellig_am">Fällig bis</label>
        <input type="date" id="faellig_am" name="faellig_am" value="<?= e((string)($order['faellig_am'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="werkstatt">Werkstatt</label>
        <input type="text" id="werkstatt" name="werkstatt" value="<?= e((string)$order['werkstatt']) ?>"
               placeholder="Eigenleistung, Vertragswerkstatt, THW-Logistik …">
      </div>
      <div class="field">
        <label for="auftragsnummer">Auftragsnummer der Werkstatt</label>
        <input type="text" id="auftragsnummer" name="auftragsnummer" value="<?= e((string)$order['auftragsnummer']) ?>">
      </div>
      <div class="field">
        <label for="thw_nummer">Nummer der THW-Verwaltung</label>
        <input type="text" id="thw_nummer" name="thw_nummer" value="<?= e((string)($order['thw_nummer'] ?? '')) ?>"
               placeholder="wird von der Verwaltung vergeben">
        <small class="muted">Sobald die Verwaltung den Vorgang angelegt hat, hier eintragen –
          danach lässt sich der Auftrag darüber finden.</small>
      </div>
      <div class="field">
        <label for="kosten_geschaetzt">Kosten geschätzt (netto)</label>
        <input type="text" inputmode="decimal" id="kosten_geschaetzt" name="kosten_geschaetzt"
               value="<?= e(num_input($order['kosten_geschaetzt'] ?? null)) ?>">
      </div>
      <?php if (can('manage_vehicles')): ?>
        <div class="field">
          <label for="kosten_netto">Kosten tatsächlich (netto)</label>
          <input type="text" inputmode="decimal" id="kosten_netto" name="kosten_netto"
                 value="<?= e(num_input($order['kosten_netto'] ?? null)) ?>">
        </div>
      <?php endif; ?>
    </div>
    <div class="btnrow">
      <button class="btn" type="submit"><?= $isNew ? 'Auftrag anlegen' : 'Speichern' ?></button>
      <a class="btn btn--sec" href="<?= e($isNew ? url('vehicle', ['id' => $vehicle['id']]) : url('vehicle_order', ['id' => $order['id']])) ?>">Abbrechen</a>
    </div>
  </section>
</form>
