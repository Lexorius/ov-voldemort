<?php
/** @var array $lieferant @var array $errors */
$isNew = empty($lieferant['id']);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Lieferant anlegen' : 'Lieferant bearbeiten' ?></h1>
    <p>Bestellungen gehen per E-Mail an diese Adresse. Die Kundennummer steht mit in der Bestellung.</p>
  </div>
  <div class="btnrow"><a class="btn btn--sec" href="<?= e(url('getraenke')) ?>#lieferanten">Abbrechen</a></div>
</div>

<?php foreach ($errors as $f): ?><div class="alert alert--error"><?= e($f) ?></div><?php endforeach; ?>

<form method="post" class="form">
  <?= csrf_field() ?>
  <section class="card">
    <div class="grid2">
      <div class="field">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required maxlength="100" value="<?= e((string)$lieferant['name']) ?>" placeholder="z. B. Getränke Müller">
      </div>
      <div class="field">
        <label for="email">E-Mail für Bestellungen</label>
        <input type="email" id="email" name="email" maxlength="150" value="<?= e((string)$lieferant['email']) ?>" placeholder="bestellung@example.de">
      </div>
      <div class="field">
        <label for="telefon">Telefon</label>
        <input type="text" id="telefon" name="telefon" maxlength="60" value="<?= e((string)$lieferant['telefon']) ?>">
      </div>
      <div class="field">
        <label for="kundennummer">Kundennummer</label>
        <input type="text" id="kundennummer" name="kundennummer" maxlength="60" value="<?= e((string)$lieferant['kundennummer']) ?>">
      </div>
      <div class="field" style="grid-column:1/-1">
        <label for="strasse">Straße und Hausnummer</label>
        <input type="text" id="strasse" name="strasse" maxlength="150" value="<?= e((string)($lieferant['strasse'] ?? '')) ?>" placeholder="z. B. Industriestraße 12">
      </div>
      <div class="field">
        <label for="plz">PLZ</label>
        <input type="text" id="plz" name="plz" maxlength="15" value="<?= e((string)($lieferant['plz'] ?? '')) ?>" inputmode="numeric">
      </div>
      <div class="field">
        <label for="ort">Ort</label>
        <input type="text" id="ort" name="ort" maxlength="100" value="<?= e((string)($lieferant['ort'] ?? '')) ?>">
      </div>
      <div class="field" style="grid-column:1/-1">
        <label for="notiz">Notiz <span class="muted">(Lieferzeiten, Pfand, Ansprechpartner)</span></label>
        <textarea id="notiz" name="notiz" rows="3"><?= e((string)$lieferant['notiz']) ?></textarea>
      </div>
      <div class="field field--check">
        <input type="checkbox" id="is_active" name="is_active" value="1" <?= (int)($lieferant['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
        <label for="is_active">Aktiv</label>
      </div>
    </div>
  </section>
  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Anlegen' : 'Speichern' ?></button>
    <a class="btn btn--sec" href="<?= e(url('getraenke')) ?>#lieferanten">Abbrechen</a>
  </div>
</form>
<?php if (!$isNew): ?>
  <form method="post" class="form" style="margin-top:1rem">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="loeschen">
    <button class="btn btn--sec btn--sm" type="submit" data-confirm="Lieferant löschen? Seine Getränke bleiben, aber ohne Lieferant." data-confirm2="Bist du wirklich sicher?">Löschen</button>
  </form>
<?php endif; ?>
