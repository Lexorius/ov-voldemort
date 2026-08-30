<?php
/** @var array $todo @var array $errors @var array $users @var array $wishes */
$isNew = empty($todo['id']);
$tt = (string)($todo['target_type'] ?? 'ov');
$tid = (int)($todo['target_id'] ?? 0);
?>
<div class="pagehead">
  <h1><?= $isNew ? 'Neue Aufgabe' : 'Aufgabe bearbeiten' ?></h1>
  <a class="btn btn--sec" href="<?= $isNew ? e(url('todos')) : e(url('todo', ['id' => $todo['id']])) ?>">Abbrechen</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error">
    <ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form method="post" class="form" action="<?= e(url('todo_edit', $isNew ? [] : ['id' => $todo['id']])) ?>">
  <?= csrf_field() ?>

  <section class="card">
    <div class="form">
      <div class="field">
        <label for="titel">Titel *</label>
        <input type="text" id="titel" name="titel" required maxlength="200" value="<?= e((string)$todo['titel']) ?>">
      </div>
      <div class="field">
        <label for="beschreibung">Beschreibung</label>
        <textarea id="beschreibung" name="beschreibung"><?= e((string)$todo['beschreibung']) ?></textarea>
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Zuständigkeit</h2>
    <div class="grid2">
      <div class="field">
        <label for="f-target-type">Gilt für</label>
        <select id="f-target-type" name="target_type">
          <?php foreach (TODO_TARGETS as $k => $lbl): ?>
            <option value="<?= e($k) ?>"<?= $tt === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" id="target-fachgruppe" hidden>
        <label for="target_fachgruppe">Fachgruppe</label>
        <select id="target_fachgruppe" name="target_fachgruppe">
          <?= list_options('fachgruppe', $tt === 'fachgruppe' ? $tid : null) ?>
        </select>
      </div>
      <div class="field" id="target-funktion" hidden>
        <label for="target_funktion">Funktion</label>
        <select id="target_funktion" name="target_funktion">
          <?= list_options('funktion', $tt === 'funktion' ? $tid : null) ?>
        </select>
      </div>
      <div class="field" id="target-user" hidden>
        <label for="target_user">Person</label>
        <select id="target_user" name="target_user">
          <option value="">– bitte wählen –</option>
          <?php foreach ($users as $u2): ?>
            <option value="<?= (int)$u2['id'] ?>"<?= ($tt === 'user' && $tid === (int)$u2['id']) ? ' selected' : '' ?>>
              <?= e($u2['display_name'] ?: $u2['username']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Einordnung</h2>
    <div class="grid3">
      <div class="field">
        <label for="status_id">Status</label>
        <select id="status_id" name="status_id"><?= list_options('todo_status', (int)($todo['status_id'] ?? 0), '') ?></select>
      </div>
      <div class="field">
        <label for="prioritaet_id">Priorität</label>
        <select id="prioritaet_id" name="prioritaet_id"><?= list_options('todo_prioritaet', (int)($todo['prioritaet_id'] ?? 0), '') ?></select>
      </div>
      <div class="field">
        <label for="faellig_am">Fällig am</label>
        <input type="date" id="faellig_am" name="faellig_am" value="<?= e((string)($todo['faellig_am'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="wish_id">Gehört zu Wunsch</label>
        <select id="wish_id" name="wish_id">
          <option value="">– kein Bezug –</option>
          <?php foreach ($wishes as $w): ?>
            <option value="<?= (int)$w['id'] ?>"<?= (int)($todo['wish_id'] ?? 0) === (int)$w['id'] ? ' selected' : '' ?>>
              #<?= (int)$w['id'] ?> · <?= e(mb_substr($w['bezeichnung'], 0, 60)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </section>

  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Aufgabe anlegen' : 'Speichern' ?></button>
    <a class="btn btn--sec" href="<?= $isNew ? e(url('todos')) : e(url('todo', ['id' => $todo['id']])) ?>">Abbrechen</a>
  </div>
</form>
