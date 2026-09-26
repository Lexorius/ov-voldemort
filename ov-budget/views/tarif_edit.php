<?php
/** @var array $tarif @var array $errors */
$isNew = empty($tarif['id']);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Tarif anlegen' : 'Tarif bearbeiten' ?></h1>
    <p>Läuft ein Tarif aus, bekommt er ein Ende und der nächste beginnt am Tag danach.
       Überschneiden sich zwei, gilt der jüngere.</p>
  </div>
  <div class="btnrow"><a class="btn btn--sec" href="<?= e(url('tarife')) ?>">Abbrechen</a></div>
</div>

<?php foreach ($errors as $f): ?>
  <div class="alert alert--error"><?= e($f) ?></div>
<?php endforeach; ?>

<form method="post" class="form">
  <?= csrf_field() ?>
  <section class="card">
    <div class="grid2">
      <div class="field">
        <label for="art">Art</label>
        <select id="art" name="art">
          <?php foreach (METER_ARTEN as $key => $a): ?>
            <option value="<?= e($key) ?>"<?= (string)$tarif['art'] === $key ? ' selected' : '' ?>><?= e($a['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required maxlength="150" value="<?= e((string)$tarif['name']) ?>"
               placeholder="z. B. Stadtwerke Grundversorgung 2026">
      </div>
      <div class="field">
        <label for="anbieter">Anbieter</label>
        <input type="text" id="anbieter" name="anbieter" maxlength="150" value="<?= e((string)$tarif['anbieter']) ?>">
      </div>
      <div class="field">
        <label for="einheit">Einheit des Arbeitspreises</label>
        <input type="text" id="einheit" name="einheit" maxlength="10" value="<?= e((string)$tarif['einheit']) ?>" placeholder="kWh oder m³">
        <small>Weicht sie von der Einheit des Zählers ab (Gas: m³ am Zähler, kWh im Tarif),
          steht die Umrechnung am Zähler.</small>
      </div>
      <div class="field">
        <label for="gueltig_von">Gültig ab</label>
        <input type="date" id="gueltig_von" name="gueltig_von" required value="<?= e((string)$tarif['gueltig_von']) ?>">
      </div>
      <div class="field">
        <label for="gueltig_bis">Gültig bis</label>
        <input type="date" id="gueltig_bis" name="gueltig_bis" value="<?= e((string)($tarif['gueltig_bis'] ?? '')) ?>">
        <small>Leer = bis auf Weiteres.</small>
      </div>
      <div class="field">
        <label for="arbeitspreis">Arbeitspreis (€ je Einheit)</label>
        <input type="text" inputmode="decimal" id="arbeitspreis" name="arbeitspreis" required
               value="<?= e(num_input($tarif['arbeitspreis'] ?? '', true)) ?>" placeholder="0,3245">
        <small>Bis zu vier Nachkommastellen, z. B. 0,3245 €/kWh.</small>
      </div>
      <div class="field">
        <label for="grundpreis_monat">Grundpreis je Monat (€)</label>
        <input type="text" inputmode="decimal" id="grundpreis_monat" name="grundpreis_monat"
               value="<?= e(num_input($tarif['grundpreis_monat'] ?? '')) ?>" placeholder="0,00">
      </div>
    </div>
    <div class="field">
      <label for="notiz">Notiz</label>
      <textarea id="notiz" name="notiz" rows="2"><?= e((string)($tarif['notiz'] ?? '')) ?></textarea>
    </div>
    <div class="btnrow">
      <button class="btn" type="submit">Speichern</button>
      <a class="btn btn--sec" href="<?= e(url('tarife')) ?>">Abbrechen</a>
    </div>
  </section>
</form>

<?php if (!$isNew): ?>
  <section class="card">
    <h2>Tarif löschen</h2>
    <p class="small">Monate, für die danach kein Tarif mehr gilt, zeigen keine Kosten.</p>
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="loeschen">
      <button class="btn btn--danger" type="submit" data-confirm="Diesen Tarif löschen?">Löschen</button>
    </form>
  </section>
<?php endif; ?>
