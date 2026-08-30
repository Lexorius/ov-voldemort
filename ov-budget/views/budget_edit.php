<?php
/** @var array $budget @var array $errors */
$isNew = empty($budget['id']);
?>
<div class="pagehead">
  <h1><?= $isNew ? 'Budgettopf anlegen' : 'Budgettopf bearbeiten' ?></h1>
  <a class="btn btn--sec" href="<?= e(url('budget', ['jahr' => $budget['jahr']])) ?>">Abbrechen</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card form" action="<?= e(url('budget_edit', $isNew ? [] : ['id' => $budget['id']])) ?>">
  <?= csrf_field() ?>
  <div class="grid2">
    <div class="field">
      <label for="name">Bezeichnung *</label>
      <input type="text" id="name" name="name" required value="<?= e((string)$budget['name']) ?>"
             placeholder="z.B. Selbstbeschaffung Ausstattung">
    </div>
    <div class="field">
      <label for="jahr">Haushaltsjahr *</label>
      <input type="number" id="jahr" name="jahr" required value="<?= (int)$budget['jahr'] ?>" min="2000" max="2100">
    </div>
    <div class="field">
      <label for="betrag_netto">Betrag netto</label>
      <input type="text" inputmode="decimal" id="betrag_netto" name="betrag_netto"
             value="<?= e(num_input($budget['betrag_netto'])) ?>"
             placeholder="0,00">
    </div>
    <div class="field">
      <label for="kategorie_id">Kategorie (optional)</label>
      <select id="kategorie_id" name="kategorie_id"><?= list_options('kategorie', (int)($budget['kategorie_id'] ?? 0), 'alle Kategorien') ?></select>
    </div>
    <div class="field">
      <label for="fachgruppe_id">Fachgruppe (optional)</label>
      <select id="fachgruppe_id" name="fachgruppe_id"><?= list_options('fachgruppe', (int)($budget['fachgruppe_id'] ?? 0), 'ortsverbandsweit') ?></select>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($budget['is_active']) ? 'checked' : '' ?>>
      <label for="is_active">Aktiv</label>
    </div>
  </div>
  <div class="field">
    <label for="beschreibung">Beschreibung</label>
    <textarea id="beschreibung" name="beschreibung"><?= e((string)$budget['beschreibung']) ?></textarea>
  </div>
  <div class="btnrow">
    <button class="btn" type="submit">Speichern</button>
    <a class="btn btn--sec" href="<?= e(url('budget', ['jahr' => $budget['jahr']])) ?>">Abbrechen</a>
  </div>
</form>

<?php if (!$isNew): ?>
  <form method="post" class="card" action="<?= e(url('budget_edit', ['id' => $budget['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <button class="btn btn--danger" type="submit"
            data-confirm="Budgettopf löschen? Zugeordnete Wünsche bleiben bestehen, verlieren aber die Zuordnung.">Topf löschen</button>
  </form>
<?php endif; ?>
