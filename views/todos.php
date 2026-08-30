<?php
/** @var array $rows @var array $filters @var string $scope @var array $users */
$modul = (string)setting('todo_modul_name', 'Aufgaben');
$erledigtSichtbar = get_str('erledigt') === '1';
$offen = 0;
$ueberfaellig = 0;
foreach ($rows as $r) {
    if (!(int)$r['status_final']) {
        $offen++;
        if ($r['faellig_am'] && $r['faellig_am'] < date('Y-m-d')) {
            $ueberfaellig++;
        }
    }
}
$scopeLink = static fn(string $s, array $extra = []) => url('todos', array_merge(['scope' => $s], $extra));
?>
<div class="pagehead">
  <div>
    <h1><?= e($modul) ?></h1>
    <p><?= nl2br(e((string)setting('todo_intro', ''))) ?></p>
  </div>
  <?php if (can('create_todo')): ?>
    <a class="btn" href="<?= e(url('todo_edit')) ?>">+ Neue Aufgabe</a>
  <?php endif; ?>
</div>

<div class="stats">
  <div class="stat"><div class="stat__label">Angezeigt</div><div class="stat__value"><?= count($rows) ?></div></div>
  <div class="stat"><div class="stat__label">Offen</div><div class="stat__value"><?= $offen ?></div></div>
  <div class="stat"><div class="stat__label">Überfällig</div><div class="stat__value"
       style="<?= $ueberfaellig ? 'color:var(--bad)' : '' ?>"><?= $ueberfaellig ?></div></div>
  <div class="stat"><div class="stat__label">Ansicht</div><div class="stat__value" style="font-size:1rem"><?= e($scope) ?></div></div>
</div>

<div class="tabs">
  <a class="tab<?= $scope === 'mine' ? ' is-active' : '' ?>" href="<?= e($scopeLink('mine')) ?>">Für mich</a>
  <a class="tab<?= $scope === 'ov' ? ' is-active' : '' ?>" href="<?= e($scopeLink('ov')) ?>">Ortsverband</a>
  <a class="tab<?= $scope === 'fachgruppe' ? ' is-active' : '' ?>" href="<?= e($scopeLink('fachgruppe')) ?>">Fachgruppen</a>
  <a class="tab<?= $scope === 'funktion' ? ' is-active' : '' ?>" href="<?= e($scopeLink('funktion')) ?>">Funktionen</a>
  <a class="tab<?= $scope === 'alle' ? ' is-active' : '' ?>" href="<?= e($scopeLink('alle')) ?>">Alle</a>
</div>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="todos">
  <input type="hidden" name="scope" value="<?= e($scope) ?>">
  <div class="filters">
    <div class="field">
      <label for="q">Suche</label>
      <input type="search" id="q" name="q" value="<?= e((string)$filters['q']) ?>" placeholder="Titel oder Beschreibung">
    </div>
    <div class="field">
      <label for="status_id">Status</label>
      <select id="status_id" name="status_id"><?= list_options('todo_status', $filters['status_id'], 'alle') ?></select>
    </div>
    <?php if ($scope === 'fachgruppe'): ?>
      <div class="field">
        <label for="target_id">Fachgruppe</label>
        <select id="target_id" name="target_id"><?= list_options('fachgruppe', get_int('target_id'), 'alle') ?></select>
      </div>
    <?php elseif ($scope === 'funktion'): ?>
      <div class="field">
        <label for="target_id">Funktion</label>
        <select id="target_id" name="target_id"><?= list_options('funktion', get_int('target_id'), 'alle') ?></select>
      </div>
    <?php elseif ($scope === 'user'): ?>
      <div class="field">
        <label for="target_id">Person</label>
        <select id="target_id" name="target_id">
          <?php foreach ($users as $u2): ?>
            <option value="<?= (int)$u2['id'] ?>"<?= get_int('target_id') === (int)$u2['id'] ? ' selected' : '' ?>>
              <?= e($u2['display_name'] ?: $u2['username']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
    <div class="field">
      <label for="sort">Sortierung</label>
      <select id="sort" name="sort">
        <option value="standard"<?= $filters['sort'] === 'standard' ? ' selected' : '' ?>>Fälligkeit</option>
        <option value="neu"<?= $filters['sort'] === 'neu' ? ' selected' : '' ?>>Neueste</option>
        <option value="titel"<?= $filters['sort'] === 'titel' ? ' selected' : '' ?>>Titel</option>
      </select>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <button class="btn btn--sec" type="submit">Filtern</button>
    </div>
  </div>
  <div class="chips mt">
    <a class="chip<?= $erledigtSichtbar ? '' : ' is-active' ?>"
       href="<?= e($scopeLink($scope, array_diff_key($_GET, ['erledigt' => 1, 'p' => 1, 'scope' => 1]))) ?>">nur offene</a>
    <a class="chip<?= $erledigtSichtbar ? ' is-active' : '' ?>"
       href="<?= e($scopeLink($scope, array_merge(array_diff_key($_GET, ['p' => 1, 'scope' => 1]), ['erledigt' => '1']))) ?>">inkl. erledigte</a>
  </div>
</form>

<?php if (!$rows): ?>
  <div class="card"><div class="empty">Keine Aufgaben in dieser Ansicht.</div></div>
<?php else: ?>
  <div class="itemlist">
    <?php foreach ($rows as $t): ?>
      <?= render_partial('partials/todo_item', ['t' => $t]) ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
