<?php
/** Aufgaben eines Talking Points in der Besprechung
 *  @var array $liste */
if (!$liste) {
    return;
}
?>
<div class="mt small">
  <span class="dl__label">Aufgaben</span>
  <ul style="margin:.2rem 0 0 1.1rem;padding:0">
    <?php foreach ($liste as $a): ?>
      <li>
        <a href="<?= e(url('todo', ['id' => $a['id']])) ?>"><?= e((string)$a['titel']) ?></a>
        <span class="muted">· <?= e(todo_target_name($a)) ?><?php if ($a['faellig_am']): ?> · bis <?= e(de_date($a['faellig_am'])) ?><?php endif; ?></span>
        <?= $a['status_label'] ? badge(['label' => $a['status_label'], 'color' => $a['status_color']]) : '' ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
