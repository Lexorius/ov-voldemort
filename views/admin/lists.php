<?php
/** @var string $key @var array $items */
$mitFarbe = in_array($key, ['dringlichkeit', 'wunsch_status', 'todo_status', 'todo_prioritaet', 'fachgruppe'], true);
$mitGewicht = in_array($key, ['dringlichkeit', 'todo_prioritaet'], true);
$mitFinal = in_array($key, ['wunsch_status', 'todo_status'], true);
?>
<div class="pagehead">
  <div>
    <h1>Auswahllisten</h1>
    <p>Diese Einträge füllen die Auswahlfelder in der ganzen Anwendung. Deaktivierte Einträge verschwinden aus neuen
       Formularen, bleiben aber an bestehenden Datensätzen erhalten.</p>
  </div>
  <div class="btnrow">
    <a class="btn" href="<?= e(url('admin_list_edit', ['key' => $key])) ?>">+ Eintrag</a>
    <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Verwaltung</a>
  </div>
</div>

<div class="tabs">
  <?php foreach (LIST_KEYS as $k => $label): ?>
    <a class="tab<?= $k === $key ? ' is-active' : '' ?>" href="<?= e(url('admin_lists', ['key' => $k])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<form method="post" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="sort">
  <div class="card__head">
    <h2><?= e(LIST_KEYS[$key]) ?></h2>
    <button class="btn btn--sec btn--sm" type="submit">Reihenfolge speichern</button>
  </div>

  <?php if (!$items): ?>
    <div class="empty">Noch keine Einträge in dieser Liste.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead>
          <tr>
            <th style="width:5.5rem">Reihung</th>
            <th>Bezeichnung</th>
            <th class="hide-mobile">Schlüssel</th>
            <?php if ($mitFarbe): ?><th class="hide-mobile">Farbe</th><?php endif; ?>
            <?php if ($mitGewicht): ?><th class="num hide-mobile">Gewicht</th><?php endif; ?>
            <th>Status</th>
            <th class="num hide-mobile">Verwendet</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $i): ?>
          <tr>
            <td><input type="number" name="sort[<?= (int)$i['id'] ?>]" value="<?= (int)$i['sort_order'] ?>"
                       step="10" style="min-height:36px;padding:.3rem"></td>
            <td>
              <strong><?= e($i['label']) ?></strong>
              <?php if ($i['description']): ?><div class="small muted"><?= e($i['description']) ?></div><?php endif; ?>
            </td>
            <td class="mono small hide-mobile"><?= e($i['slug']) ?></td>
            <?php if ($mitFarbe): ?>
              <td class="hide-mobile"><?= badge(['label' => $i['color'], 'color' => $i['color']]) ?></td>
            <?php endif; ?>
            <?php if ($mitGewicht): ?><td class="num hide-mobile"><?= (int)$i['weight'] ?></td><?php endif; ?>
            <td>
              <?php if ((int)$i['is_active']): ?>
                <span class="badge" style="background:#15803d">aktiv</span>
              <?php else: ?>
                <span class="badge badge--muted">inaktiv</span>
              <?php endif; ?>
              <?php if ((int)$i['is_default']): ?><span class="badge badge--outline">Vorgabe</span><?php endif; ?>
              <?php if ($mitFinal && (int)$i['is_final']): ?><span class="badge badge--outline">abgeschlossen</span><?php endif; ?>
            </td>
            <td class="num small hide-mobile"><?= (int)$i['wunsch_nutzung'] + (int)$i['user_nutzung'] ?>×</td>
            <td><a class="btn btn--sec btn--sm" href="<?= e(url('admin_list_edit', ['id' => $i['id']])) ?>">Bearbeiten</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</form>
