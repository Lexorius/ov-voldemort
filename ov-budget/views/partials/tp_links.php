<?php
/**
 * Bezüge eines Tagesordnungspunkts als Kette von Kennzeichen; Veranstaltungen
 * mit Kurzbericht (Budgettopf, Kosten, Verpflegung).
 * @var array $liste   aus tp_links_fuer()[tp_id]
 * @var array $events  aus tp_event_kurzberichte(), optional
 */
$liste ??= [];
$events ??= [];
if (!$liste) {
    return;
}
$farben = ['termin' => '#0369a1', 'event' => '#c2410c', 'vehicle' => '#0f766e', 'radio' => '#7c3aed'];
?>
<div class="chips" style="margin-top:.35rem">
  <?php foreach ($liste as $l): ?>
    <a class="chip" href="<?= e($l['url']) ?>" style="border-left:3px solid <?= e($farben[$l['typ']] ?? '#94a3b8') ?>"
       title="<?= e(TP_LINK_TYPEN[$l['typ']]['label'] . ($l['zusatz'] !== '' ? ' · ' . $l['zusatz'] : '')) ?>">
      <span class="muted small"><?= e(TP_LINK_TYPEN[$l['typ']]['label']) ?></span> <?= e($l['label']) ?><?= $l['zusatz'] !== '' ? ' <span class="muted small">' . e($l['zusatz']) . '</span>' : '' ?>
    </a>
  <?php endforeach; ?>
</div>
<?php foreach ($liste as $l): if ($l['typ'] !== 'event' || !isset($events[(int)$l['id']])) { continue; } $b = $events[(int)$l['id']]; ?>
  <div class="small" style="margin:.3rem 0 0 .5rem;padding-left:.6rem;border-left:2px solid #c2410c">
    <?php foreach (tp_event_kurzbericht_zeilen($b) as $i => $zeile): ?>
      <div<?= $i === 0 ? '' : ' class="muted"' ?>><?= e($zeile) ?></div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
