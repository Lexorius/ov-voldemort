<?php
/** @var array $todo @var array $kommentare @var ?array $wish */
$done = (int)$todo['status_final'] === 1;
$overdue = !$done && $todo['faellig_am'] && $todo['faellig_am'] < date('Y-m-d');
?>
<div class="pagehead">
  <div>
    <h1><?= e($todo['titel']) ?></h1>
    <p class="muted small">
      Aufgabe #<?= (int)$todo['id'] ?> · angelegt am <?= e(de_datetime($todo['created_at'])) ?>
      <?php if ($todo['ersteller']): ?> von <?= e($todo['ersteller']) ?><?php endif; ?>
    </p>
  </div>
  <div class="btnrow">
    <?php if (can('edit_todo', $todo)): ?>
      <a class="btn" href="<?= e(url('todo_edit', ['id' => $todo['id']])) ?>">Bearbeiten</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('todos')) ?>">Zurück</a>
  </div>
</div>

<div class="card">
  <div class="item__meta" style="margin-top:0">
    <?= badge($todo['status_label'] ? ['label' => $todo['status_label'], 'color' => $todo['status_color']] : null) ?>
    <?= badge($todo['prio_label'] ? ['label' => $todo['prio_label'], 'color' => $todo['prio_color']] : null) ?>
    <span class="badge badge--outline"><?= e(todo_target_name($todo)) ?></span>
    <?php if ($overdue): ?><span class="badge" style="background:#b91c1c">überfällig</span><?php endif; ?>
  </div>

  <dl class="dl mt">
    <div class="dl__item"><div class="dl__label">Zuständig</div>
      <div class="dl__value"><?= e(todo_target_name($todo)) ?></div></div>
    <div class="dl__item"><div class="dl__label">Fällig am</div>
      <div class="dl__value"><?= $todo['faellig_am'] ? e(de_date($todo['faellig_am'])) : '– ohne Frist –' ?></div></div>
    <?php if ($todo['erledigt_am']): ?>
      <div class="dl__item"><div class="dl__label">Erledigt am</div>
        <div class="dl__value"><?= e(de_datetime($todo['erledigt_am'])) ?></div></div>
    <?php endif; ?>
    <?php if ($wish): ?>
      <div class="dl__item"><div class="dl__label">Gehört zu Wunsch</div>
        <div class="dl__value"><a href="<?= e(url('wish', ['id' => $wish['id']])) ?>">#<?= (int)$wish['id'] ?> · <?= e($wish['bezeichnung']) ?></a></div></div>
    <?php endif; ?>
  </dl>

  <?php if ($todo['beschreibung']): ?>
    <h3 class="mt">Beschreibung</h3>
    <div class="comment__body"><?= e($todo['beschreibung']) ?></div>
  <?php endif; ?>
</div>

<?php if (can('edit_todo', $todo)): ?>
  <div class="card">
    <h2>Status ändern</h2>
    <form method="post" action="<?= e(url('todo_action')) ?>" class="grid2">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="status">
      <input type="hidden" name="id" value="<?= (int)$todo['id'] ?>">
      <div class="field">
        <label for="status_id">Neuer Status</label>
        <select id="status_id" name="status_id"><?= list_options('todo_status', (int)$todo['status_id'], '') ?></select>
      </div>
      <div class="field">
        <label>&nbsp;</label>
        <div class="btnrow">
          <button class="btn" type="submit">Übernehmen</button>
          <?php if (!$done): ?>
            <button class="btn btn--ok" type="submit" name="quick" value="done">Als erledigt markieren</button>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Notizen</h2>
  <?php if (!$kommentare): ?>
    <p class="muted small">Noch keine Notizen.</p>
  <?php else: ?>
    <?php foreach ($kommentare as $c): ?>
      <div class="comment">
        <div class="comment__meta"><strong><?= e($c['autor'] ?: 'unbekannt') ?></strong> · <?= e(de_datetime($c['created_at'])) ?></div>
        <div class="comment__body"><?= e($c['body']) ?></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <form method="post" action="<?= e(url('todo_action')) ?>" class="form mt">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="comment">
    <input type="hidden" name="id" value="<?= (int)$todo['id'] ?>">
    <div class="field">
      <label for="body">Notiz</label>
      <textarea id="body" name="body" required placeholder="Zwischenstand, Rückfrage, Ergebnis ..."></textarea>
    </div>
    <div><button class="btn" type="submit">Absenden</button></div>
  </form>
</div>

<?php if (can('manage_todos') || (int)$todo['created_by'] === (int)current_user()['id']): ?>
  <form method="post" action="<?= e(url('todo_action')) ?>" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= (int)$todo['id'] ?>">
    <button class="btn btn--danger" type="submit" data-confirm="Aufgabe endgültig löschen?">Aufgabe löschen</button>
  </form>
<?php endif; ?>
