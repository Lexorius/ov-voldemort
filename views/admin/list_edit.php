<?php
/** @var array $item @var string $key @var array $errors */
$isNew = empty($item['id']);
$mitGewicht = in_array($key, ['dringlichkeit', 'todo_prioritaet'], true);
$mitFinal = in_array($key, ['wunsch_status', 'todo_status'], true);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Eintrag anlegen' : 'Eintrag bearbeiten' ?></h1>
    <p class="muted small">Liste: <?= e(LIST_KEYS[$key]) ?></p>
  </div>
  <a class="btn btn--sec" href="<?= e(url('admin_lists', ['key' => $key])) ?>">Zurück</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card form"
      action="<?= e(url('admin_list_edit', $isNew ? ['key' => $key] : ['id' => $item['id']])) ?>">
  <?= csrf_field() ?>
  <div class="grid2">
    <div class="field">
      <label for="label">Bezeichnung *</label>
      <input type="text" id="label" name="label" required value="<?= e((string)$item['label']) ?>">
    </div>
    <div class="field">
      <label for="slug">Schlüssel</label>
      <input type="text" id="slug" name="slug" value="<?= e((string)$item['slug']) ?>" placeholder="wird automatisch erzeugt">
      <small>Technischer Name, u.a. für den Divera-Abgleich. Ändern nur, wenn nötig.</small>
    </div>
    <div class="field">
      <label for="description">Beschreibung</label>
      <input type="text" id="description" name="description" value="<?= e((string)$item['description']) ?>">
    </div>
    <div class="field">
      <label for="color">Farbe</label>
      <input type="color" id="color" name="color" value="<?= e((string)($item['color'] ?: '#64748b')) ?>">
    </div>
    <div class="field">
      <label for="sort_order">Reihung</label>
      <input type="number" id="sort_order" name="sort_order" value="<?= (int)$item['sort_order'] ?>" step="10">
      <small>Kleinere Zahl = weiter oben in der Auswahl.</small>
    </div>
    <?php if ($mitGewicht): ?>
      <div class="field">
        <label for="weight">Gewicht</label>
        <input type="number" id="weight" name="weight" value="<?= (int)$item['weight'] ?>">
        <small>Höheres Gewicht sortiert Wünsche und Aufgaben weiter nach oben.</small>
      </div>
    <?php else: ?>
      <input type="hidden" name="weight" value="<?= (int)$item['weight'] ?>">
    <?php endif; ?>
  </div>

  <div class="grid3">
    <div class="field field--check">
      <input type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($item['is_active']) ? 'checked' : '' ?>>
      <label for="is_active">Aktiv (in Auswahlfeldern sichtbar)</label>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="is_default" name="is_default" value="1" <?= !empty($item['is_default']) ? 'checked' : '' ?>>
      <label for="is_default">Vorgabewert für neue Einträge</label>
    </div>
    <?php if ($mitFinal): ?>
      <div class="field field--check">
        <input type="checkbox" id="is_final" name="is_final" value="1" <?= !empty($item['is_final']) ? 'checked' : '' ?>>
        <label for="is_final">Gilt als abgeschlossen</label>
      </div>
    <?php else: ?>
      <input type="hidden" name="is_final" value="<?= (int)$item['is_final'] ?>">
    <?php endif; ?>
  </div>

  <div class="btnrow">
    <button class="btn" type="submit">Speichern</button>
    <a class="btn btn--sec" href="<?= e(url('admin_lists', ['key' => $key])) ?>">Abbrechen</a>
  </div>
</form>

<?php if (!$isNew): ?>
  <form method="post" class="card" action="<?= e(url('admin_list_edit', ['id' => $item['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="label" value="<?= e((string)$item['label']) ?>">
    <p class="small muted">Wird der Eintrag noch verwendet, lässt er sich nicht löschen – dann besser auf "inaktiv" setzen.</p>
    <button class="btn btn--danger" type="submit" data-confirm="Eintrag löschen?">Eintrag löschen</button>
  </form>
<?php endif; ?>
