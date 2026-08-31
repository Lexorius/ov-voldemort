<?php
/** @var array $expense @var array $errors @var array $budgets @var array $wishes */
$isNew = empty($expense['id']);
$art = (string)setting('ausgaben_betragsart', 'brutto');
// Angezeigt wird der Betrag in der Erfassungsart aus den Einstellungen
$betragWert = $art === 'netto' ? ($expense['betrag_netto'] ?? '') : ($expense['betrag_brutto'] ?? '');
$zurueck = url('expenses', ['jahr' => (int)($expense['jahr'] ?? date('Y'))]);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Ausgabe erfassen' : 'Ausgabe bearbeiten' ?></h1>
    <p class="muted small">Beträge werden <strong><?= e($art) ?></strong> erfasst –
      umstellbar unter Verwaltung → Einstellungen → Budget.</p>
  </div>
  <a class="btn btn--sec" href="<?= e($zurueck) ?>">Abbrechen</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="form" action="<?= e(url('expense_edit', $isNew ? [] : ['id' => $expense['id']])) ?>">
  <?= csrf_field() ?>

  <section class="card">
    <div class="grid3">
      <div class="field">
        <label for="datum">Datum *</label>
        <input type="date" id="datum" name="datum" required value="<?= e((string)($expense['datum'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="jahr">Haushaltsjahr</label>
        <input type="number" id="jahr" name="jahr" min="2000" max="2100" value="<?= (int)($expense['jahr'] ?? date('Y')) ?>">
        <small>Weicht nur selten vom Datum ab.</small>
      </div>
      <div class="field">
        <label for="betrag">Betrag <?= e($art) ?> *</label>
        <input type="text" inputmode="decimal" id="betrag" name="betrag" required placeholder="0,00"
               value="<?= e(num_input($betragWert)) ?>">
      </div>
      <div class="field">
        <label for="mwst_satz">MwSt (%)</label>
        <input type="text" inputmode="decimal" id="mwst_satz" name="mwst_satz"
               value="<?= e(num_input($expense['mwst_satz'] ?? 19, true)) ?>">
        <small>Der jeweils andere Betrag wird daraus berechnet.</small>
      </div>
    </div>

    <div class="field">
      <label for="bezeichnung">Bezeichnung *</label>
      <input type="text" id="bezeichnung" name="bezeichnung" required maxlength="200"
             value="<?= e((string)$expense['bezeichnung']) ?>" placeholder="z.B. Stromabschlag Februar">
    </div>
    <div class="field">
      <label for="beschreibung">Beschreibung</label>
      <textarea id="beschreibung" name="beschreibung"><?= e((string)($expense['beschreibung'] ?? '')) ?></textarea>
    </div>
  </section>

  <section class="card">
    <h2>Einordnung</h2>
    <div class="grid3">
      <div class="field">
        <label for="kategorie_id">Kategorie</label>
        <select id="kategorie_id" name="kategorie_id"><?= list_options('ausgabe_kategorie', (int)($expense['kategorie_id'] ?? 0)) ?></select>
      </div>
      <div class="field">
        <label for="fachgruppe_id">Fachgruppe</label>
        <select id="fachgruppe_id" name="fachgruppe_id"><?= list_options('fachgruppe', (int)($expense['fachgruppe_id'] ?? 0), 'ortsverbandsweit') ?></select>
      </div>
      <div class="field">
        <label for="budget_id">Budgettopf</label>
        <select id="budget_id" name="budget_id">
          <option value="">– keinem Topf zugeordnet –</option>
          <?php foreach ($budgets as $b): ?>
            <option value="<?= (int)$b['id'] ?>"<?= (int)($expense['budget_id'] ?? 0) === (int)$b['id'] ? ' selected' : '' ?>>
              <?= e($b['jahr'] . ' · ' . $b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="wish_id">Gehört zu Wunsch</label>
        <select id="wish_id" name="wish_id">
          <option value="">– kein Bezug –</option>
          <?php foreach ($wishes as $w): ?>
            <option value="<?= (int)$w['id'] ?>"<?= (int)($expense['wish_id'] ?? 0) === (int)$w['id'] ? ' selected' : '' ?>>
              #<?= (int)$w['id'] ?> · <?= e(mb_substr($w['bezeichnung'], 0, 60)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Beleg</h2>
    <div class="grid3">
      <div class="field">
        <label for="lieferant">Lieferant / Empfänger</label>
        <input type="text" id="lieferant" name="lieferant" value="<?= e((string)($expense['lieferant'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="beleg_nr">Belegnummer</label>
        <input type="text" id="beleg_nr" name="beleg_nr" value="<?= e((string)($expense['beleg_nr'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="bezahlt_am">Bezahlt am</label>
        <input type="date" id="bezahlt_am" name="bezahlt_am" value="<?= e((string)($expense['bezahlt_am'] ?? '')) ?>">
      </div>
    </div>
    <div class="field">
      <label for="notiz">Notiz</label>
      <textarea id="notiz" name="notiz"><?= e((string)($expense['notiz'] ?? '')) ?></textarea>
    </div>
  </section>

  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Ausgabe eintragen' : 'Speichern' ?></button>
    <?php if ($isNew): ?>
      <button class="btn btn--sec" type="submit" name="weiter" value="1">Eintragen und nächste</button>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e($zurueck) ?>">Abbrechen</a>
  </div>
</form>

<?php if (!$isNew): ?>
  <form method="post" class="card" action="<?= e(url('expense_edit', ['id' => $expense['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <button class="btn btn--danger" type="submit" data-confirm="Diese Ausgabe endgültig löschen?">Ausgabe löschen</button>
  </form>
<?php endif; ?>
