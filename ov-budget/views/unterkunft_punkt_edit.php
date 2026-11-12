<?php
/** @var array $punkt @var array $standorte @var array $personen @var array $errors @var bool $verwalten */
$isNew = empty($punkt['id']);
$zurueck = $isNew ? url('unterkunft') : url('unterkunft_punkt', ['id' => $punkt['id']]);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Mangel oder Aufgabe melden' : 'Punkt bearbeiten' ?></h1>
    <p class="muted small">Was ist am oder im Gebäude kaputt, fehlt oder muss gemacht werden? Kurz und so, dass jemand anderes es findet.</p>
  </div>
  <a class="btn btn--sec" href="<?= e($zurueck) ?>">Abbrechen</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="form" action="<?= e($isNew ? url('unterkunft_punkt_edit') : url('unterkunft_punkt_edit', ['id' => $punkt['id']])) ?>">
  <?= csrf_field() ?>
  <section class="card">
    <h2>Was</h2>
    <div class="field">
      <label for="titel">Worum geht es?</label>
      <input type="text" id="titel" name="titel" required maxlength="200" value="<?= e((string)($punkt['titel'] ?? '')) ?>" placeholder="z. B. Wasserhahn in der Küche tropft">
    </div>
    <div class="field">
      <label for="beschreibung">Beschreibung</label>
      <textarea id="beschreibung" name="beschreibung" rows="4" placeholder="Seit wann, wie schlimm, was wurde schon versucht?"><?= e((string)($punkt['beschreibung'] ?? '')) ?></textarea>
    </div>
    <div class="grid2">
      <div class="field">
        <label for="art">Art</label>
        <select id="art" name="art">
          <?php foreach (UNTERKUNFT_ARTEN as $k => $l): ?>
            <option value="<?= e($k) ?>"<?= (string)($punkt['art'] ?? 'mangel') === $k ? ' selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="muted">Mangel: etwas ist defekt. Aufgabe: etwas soll gemacht werden – streichen, aufräumen, prüfen lassen.</small>
      </div>
      <div class="field">
        <label for="prioritaet_id">Dringlichkeit</label>
        <select id="prioritaet_id" name="prioritaet_id"><?= list_options('todo_prioritaet', (int)($punkt['prioritaet_id'] ?? 0), '') ?></select>
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Wo</h2>
    <div class="grid2">
      <?php if ($standorte && function_exists('standort_optionen')): ?>
        <div class="field">
          <label for="standort_id">Platz</label>
          <select id="standort_id" name="standort_id"><?= standort_optionen($standorte, (int)($punkt['standort_id'] ?? 0) ?: null, '– nicht in der Liste –') ?></select>
          <small class="muted">Aus den Stell- und Lagerplätzen: Gebäude, Halle, Raum, Schrank …</small>
        </div>
      <?php endif; ?>
      <div class="field">
        <label for="ort_text">Genauer <span class="muted">(freiwillig)</span></label>
        <input type="text" id="ort_text" name="ort_text" maxlength="200" value="<?= e((string)($punkt['ort_text'] ?? '')) ?>" placeholder="z. B. Fenster links neben der Tür">
      </div>
    </div>
  </section>

  <?php if ($verwalten): ?>
    <section class="card">
      <h2>Wer und bis wann</h2>
      <div class="grid3">
        <div class="field">
          <label for="zustaendig_id">Zuständig</label>
          <select id="zustaendig_id" name="zustaendig_id">
            <option value="">– niemand –</option>
            <?php foreach ($personen as $u): ?>
              <option value="<?= (int)$u['id'] ?>"<?= (int)$u['id'] === (int)($punkt['zustaendig_id'] ?? 0) ? ' selected' : '' ?>><?= e((string)$u['display_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="faellig_am">Frist</label>
          <input type="date" id="faellig_am" name="faellig_am" value="<?= e((string)($punkt['faellig_am'] ?? '')) ?>">
        </div>
        <div class="field">
          <label for="firma">Firma <span class="muted">(freiwillig)</span></label>
          <input type="text" id="firma" name="firma" maxlength="150" value="<?= e((string)($punkt['firma'] ?? '')) ?>" placeholder="z. B. Elektro Müller">
        </div>
      </div>
      <p class="small muted">Den Status setzt du auf der Seite des Punkts – dort steht auch der Verlauf.</p>
    </section>
  <?php endif; ?>

  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Melden' : 'Speichern' ?></button>
    <a class="btn btn--sec" href="<?= e($zurueck) ?>">Abbrechen</a>
  </div>
</form>
