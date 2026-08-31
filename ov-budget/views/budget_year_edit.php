<?php /** @var array $eintrag @var array $errors @var float $ausgaben */ ?>
<div class="pagehead">
  <div>
    <h1>Jahresbudget <?= (int)$eintrag['jahr'] ?></h1>
    <p>Der Gesamtrahmen des Haushaltsjahres. Die Budgettöpfe unterteilen ihn nur –
       für die Übersicht zählt dieser Betrag.</p>
  </div>
  <a class="btn btn--sec" href="<?= e(url('budget', ['jahr' => $eintrag['jahr']])) ?>">Zurück</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card form" action="<?= e(url('budget_year_edit', ['jahr' => $eintrag['jahr']])) ?>">
  <?= csrf_field() ?>
  <div class="grid2">
    <div class="field">
      <label for="jahr">Haushaltsjahr *</label>
      <input type="number" id="jahr" name="jahr" required min="2000" max="2100" value="<?= (int)$eintrag['jahr'] ?>">
    </div>
    <div class="field">
      <label for="betrag">Gesamtbudget</label>
      <input type="text" inputmode="decimal" id="betrag" name="betrag" placeholder="0,00"
             value="<?= e(num_input($eintrag['betrag'])) ?>">
      <small>So erfasst wie die Ausgaben (<?= e((string)setting('ausgaben_betragsart', 'brutto')) ?>).</small>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($eintrag['is_active']) ? 'checked' : '' ?>>
      <label for="is_active">Aktiv</label>
    </div>
  </div>
  <div class="field">
    <label for="beschreibung">Notiz</label>
    <textarea id="beschreibung" name="beschreibung" placeholder="Woher stammt der Betrag, was ist enthalten?"><?= e((string)$eintrag['beschreibung']) ?></textarea>
  </div>
  <?php if ($ausgaben > 0): ?>
    <p class="small muted mb0">Bereits erfasste Ausgaben in <?= (int)$eintrag['jahr'] ?>: <strong><?= e(money($ausgaben)) ?></strong></p>
  <?php endif; ?>
  <div class="btnrow">
    <button class="btn" type="submit">Speichern</button>
    <a class="btn btn--sec" href="<?= e(url('budget', ['jahr' => $eintrag['jahr']])) ?>">Abbrechen</a>
  </div>
</form>
