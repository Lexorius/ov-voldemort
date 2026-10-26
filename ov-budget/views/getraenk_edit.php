<?php
/** @var array $getraenk @var array $errors @var array $lieferanten */
$isNew = empty($getraenk['id']);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Getränk anlegen' : 'Getränk bearbeiten' ?></h1>
    <p>Jedes Getränk gehört zu einem Lieferanten; danach werden Bestellungen gebündelt. Der Mindestbestand gilt über
       alle Lagerplätze zusammen – fällt der Bestand darunter, steht das Getränk im Bedarf.</p>
  </div>
  <div class="btnrow"><a class="btn btn--sec" href="<?= e(url('getraenke')) ?>#getraenke">Abbrechen</a></div>
</div>

<?php foreach ($errors as $f): ?><div class="alert alert--error"><?= e($f) ?></div><?php endforeach; ?>

<form method="post" class="form">
  <?= csrf_field() ?>
  <section class="card">
    <div class="grid2">
      <div class="field">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required maxlength="100" value="<?= e((string)$getraenk['name']) ?>" placeholder="z. B. Wasser still">
      </div>
      <div class="field">
        <label for="lieferant_id">Lieferant</label>
        <select id="lieferant_id" name="lieferant_id">
          <option value="">– keiner –</option>
          <?php foreach ($lieferanten as $l): ?>
            <option value="<?= (int)$l['id'] ?>"<?= (int)$l['id'] === (int)($getraenk['lieferant_id'] ?? 0) ? ' selected' : '' ?>><?= e((string)$l['name']) ?><?= (int)$l['is_active'] !== 1 ? ' (nicht aktiv)' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="gebinde">Gebinde</label>
        <input type="text" id="gebinde" name="gebinde" maxlength="60" value="<?= e((string)$getraenk['gebinde']) ?>" placeholder="z. B. Kasten 12 × 0,7 l">
      </div>
      <div class="field">
        <label for="preis">Preis je Gebinde <span class="muted">(für die Summe der Bestellung)</span></label>
        <input type="text" inputmode="decimal" id="preis" name="preis" value="<?= e((string)$getraenk['preis']) ?>" placeholder="0,00">
      </div>
      <div class="field">
        <label for="mindestbestand">Mindestbestand <span class="muted">(Gebinde, alle Plätze zusammen)</span></label>
        <input type="number" id="mindestbestand" name="mindestbestand" min="0" max="9999" value="<?= (int)$getraenk['mindestbestand'] ?>">
      </div>
      <div class="field">
        <label for="sort_order">Reihenfolge</label>
        <input type="number" id="sort_order" name="sort_order" value="<?= (int)$getraenk['sort_order'] ?>">
      </div>
      <div class="field" style="grid-column:1/-1">
        <label for="notiz">Notiz</label>
        <input type="text" id="notiz" name="notiz" maxlength="200" value="<?= e((string)$getraenk['notiz']) ?>">
      </div>
      <div class="field field--check">
        <input type="checkbox" id="is_active" name="is_active" value="1" <?= (int)($getraenk['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
        <label for="is_active">Aktiv <span class="muted">(steht auf den Meldeseiten und im Bedarf)</span></label>
      </div>
    </div>
  </section>
  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Anlegen' : 'Speichern' ?></button>
    <a class="btn btn--sec" href="<?= e(url('getraenke')) ?>#getraenke">Abbrechen</a>
  </div>
</form>
<?php if (!$isNew): ?>
  <form method="post" class="form" style="margin-top:1rem">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="loeschen">
    <button class="btn btn--sec btn--sm" type="submit" data-confirm="Getränk samt Beständen und Meldungen löschen?" data-confirm2="Bist du wirklich sicher?">Löschen</button>
  </form>
<?php endif; ?>
