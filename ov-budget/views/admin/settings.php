<?php
/** @var array $gruppen @var string $group */
?>
<div class="pagehead">
  <div>
    <h1>Einstellungen</h1>
    <p>Bezeichnungen, Texte und Regeln der Anwendung. Änderungen wirken sofort für alle Benutzer.</p>
  </div>
  <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Verwaltung</a>
</div>

<div class="tabs">
  <?php foreach (array_keys($gruppen) as $g): ?>
    <a class="tab<?= $g === $group ? ' is-active' : '' ?>" href="<?= e(url('admin_settings', ['group' => $g])) ?>"><?= e($g) ?></a>
  <?php endforeach; ?>
</div>

<form method="post" class="card form" action="<?= e(url('admin_settings', ['group' => $group])) ?>">
  <?= csrf_field() ?>
  <h2><?= e($group) ?></h2>

  <?php foreach ($gruppen[$group] as $s):
      $id = 's_' . $s['skey'];
      $val = (string)($s['svalue'] ?? '');
      $label = $s['label'] !== '' ? $s['label'] : $s['skey'];
  ?>
    <?php if ($s['stype'] === 'bool'): ?>
      <div class="field field--check">
        <input type="checkbox" id="<?= e($id) ?>" name="<?= e($id) ?>" value="1" <?= $val === '1' ? 'checked' : '' ?>>
        <label for="<?= e($id) ?>"><?= e($label) ?></label>
      </div>
      <?php if ($s['hint']): ?><small class="muted" style="margin-top:-.6rem"><?= e($s['hint']) ?></small><?php endif; ?>
    <?php else: ?>
      <div class="field">
        <label for="<?= e($id) ?>"><?= e($label) ?></label>
        <?php if ($s['stype'] === 'textarea'): ?>
          <textarea id="<?= e($id) ?>" name="<?= e($id) ?>"><?= e($val) ?></textarea>
        <?php elseif ($s['stype'] === 'color'): ?>
          <input type="color" id="<?= e($id) ?>" name="<?= e($id) ?>" value="<?= e($val ?: '#003399') ?>">
        <?php elseif ($s['stype'] === 'number'): ?>
          <input type="number" step="any" id="<?= e($id) ?>" name="<?= e($id) ?>" value="<?= e($val) ?>">
        <?php elseif ($s['stype'] === 'password'): ?>
          <input type="password" id="<?= e($id) ?>" name="<?= e($id) ?>" value="" autocomplete="new-password"
                 placeholder="<?= $val !== '' ? 'gespeichert – leer lassen, um ihn beizubehalten' : 'noch nicht hinterlegt' ?>">
        <?php elseif ($s['stype'] === 'select' && $s['skey'] === 'divera_auth_mode'): ?>
          <select id="<?= e($id) ?>" name="<?= e($id) ?>">
            <option value="query"<?= $val === 'query' ? ' selected' : '' ?>>Als Parameter (?accesskey=...)</option>
            <option value="header"<?= $val === 'header' ? ' selected' : '' ?>>Als Header (Authorization: Bearer ...)</option>
          </select>
        <?php else: ?>
          <input type="text" id="<?= e($id) ?>" name="<?= e($id) ?>" value="<?= e($val) ?>">
        <?php endif; ?>
        <?php if ($s['hint']): ?><small><?= e($s['hint']) ?></small><?php endif; ?>
        <small class="mono muted"><?= e($s['skey']) ?></small>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>

  <div class="btnrow">
    <button class="btn" type="submit">Speichern</button>
  </div>
</form>
