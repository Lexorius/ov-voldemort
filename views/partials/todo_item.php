<?php
/** @var array $t  Zeile aus todo_query() */
$done = (int)$t['status_final'] === 1;
$overdue = !$done && $t['faellig_am'] && $t['faellig_am'] < date('Y-m-d');
$warnTage = setting_int('todo_faellig_warntage', 7);
$bald = !$done && !$overdue && $t['faellig_am']
    && $t['faellig_am'] <= date('Y-m-d', strtotime('+' . $warnTage . ' days'));
$classes = 'item' . ($done ? ' item--done' : '') . ($overdue ? ' item--overdue' : '');
?>
<a class="<?= $classes ?>" href="<?= e(url('todo', ['id' => $t['id']])) ?>"
   style="border-left-color:<?= e($t['prio_color'] ?: '#94a3b8') ?>">
  <div class="item__top">
    <div style="min-width:0">
      <div class="item__title"><?= e($t['titel']) ?></div>
      <div class="item__sub">
        <?= e(todo_target_name($t)) ?>
        <?php if ($t['faellig_am']): ?>
          · fällig <?= e(de_date($t['faellig_am'])) ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="item__meta">
    <?= badge($t['status_label'] ? ['label' => $t['status_label'], 'color' => $t['status_color']] : null) ?>
    <?= badge($t['prio_label'] ? ['label' => $t['prio_label'], 'color' => $t['prio_color']] : null) ?>
    <?php if ($overdue): ?><span class="badge" style="background:#b91c1c">überfällig</span><?php endif; ?>
    <?php if ($bald): ?><span class="badge" style="background:#b45309">bald fällig</span><?php endif; ?>
    <?php if ((int)$t['kommentare'] > 0): ?>
      <span class="badge badge--outline">💬 <?= (int)$t['kommentare'] ?></span>
    <?php endif; ?>
    <?php if (!empty($t['wish_id'])): ?>
      <span class="badge badge--outline">zu Wunsch #<?= (int)$t['wish_id'] ?></span>
    <?php endif; ?>
  </div>
</a>
