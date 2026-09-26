<?php /** @var array $module @var bool $eigene */ $n = count($module); $i = 0; ?>
<div class="pagehead">
  <div>
    <h1>Menüleiste</h1>
    <p>In dieser Reihenfolge stehen die Module in der Leiste – für alle. Wer ein Modul nicht sehen
       darf, bekommt es ohnehin nicht angezeigt. Die Namen der Module stehen unter Einstellungen.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Zur Verwaltung</a>
  </div>
</div>

<section class="card">
  <div class="tablewrap">
    <table class="data">
      <thead><tr><th style="width:3rem">#</th><th>Modul</th><th>Sichtbar für</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($module as $key => $m): $i++; ?>
        <tr>
          <td class="muted"><?= $i ?></td>
          <td><span class="mainnav__icon" style="margin-right:.4rem"><?= e($m['icon']) ?></span><strong><?= e($m['label']) ?></strong></td>
          <td class="small muted"><?= $key === 'admin' ? 'Administration' : ($m['darf'] === true && in_array($key, ['dashboard', 'wishes', 'todos', 'budget'], true) ? 'alle' : 'je nach Recht') ?></td>
          <td class="nowrap">
            <form method="post" class="inline-form"><?= csrf_field() ?>
              <input type="hidden" name="action" value="hoch"><input type="hidden" name="key" value="<?= e($key) ?>">
              <button class="btn btn--sec btn--sm" type="submit" aria-label="nach oben"<?= $i === 1 ? ' disabled' : '' ?>>▲</button></form>
            <form method="post" class="inline-form"><?= csrf_field() ?>
              <input type="hidden" name="action" value="runter"><input type="hidden" name="key" value="<?= e($key) ?>">
              <button class="btn btn--sec btn--sm" type="submit" aria-label="nach unten"<?= $i === $n ? ' disabled' : '' ?>>▼</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($eigene): ?>
    <form method="post" class="mt"><?= csrf_field() ?>
      <input type="hidden" name="action" value="standard">
      <button class="btn btn--sec btn--sm" type="submit">Vorgabereihenfolge wiederherstellen</button>
    </form>
  <?php endif; ?>
</section>
