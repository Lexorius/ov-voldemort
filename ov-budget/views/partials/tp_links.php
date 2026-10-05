<?php
/**
 * Bezüge eines Tagesordnungspunkts als Kette von Kennzeichen.
 * @var array $liste  aus tp_links_fuer()[tp_id]
 */
$liste ??= [];
if (!$liste) {
    return;
}
$farben = ['termin' => '#0369a1', 'vehicle' => '#0f766e', 'radio' => '#7c3aed'];
?>
<div class="chips" style="margin-top:.35rem">
  <?php foreach ($liste as $l): ?>
    <a class="chip" href="<?= e($l['url']) ?>" style="border-left:3px solid <?= e($farben[$l['typ']] ?? '#94a3b8') ?>"
       title="<?= e(TP_LINK_TYPEN[$l['typ']]['label'] . ($l['zusatz'] !== '' ? ' · ' . $l['zusatz'] : '')) ?>">
      <span class="muted small"><?= e(TP_LINK_TYPEN[$l['typ']]['label']) ?></span> <?= e($l['label']) ?><?= $l['zusatz'] !== '' ? ' <span class="muted small">' . e($l['zusatz']) . '</span>' : '' ?>
    </a>
  <?php endforeach; ?>
</div>
