<?php
/**
 * Bezüge eines Tagesordnungspunkts als Kette von Kennzeichen; Veranstaltungen
 * mit Kurzbericht (Budgettopf, Kosten, Verpflegung).
 * @var array $liste   aus tp_links_fuer()[tp_id]
 * @var array $events  aus tp_event_kurzberichte(), optional
 * @var array $budgets aus tp_budget_kurzberichte(), optional
 */
$liste ??= [];
$events ??= [];
$budgets ??= [];
if (!$liste) {
    return;
}
$farben = ['termin' => '#0369a1', 'event' => '#c2410c', 'vehicle' => '#0f766e', 'radio' => '#7c3aed', 'budget' => '#15803d'];
$anteilZeile = static function (array $liste, string $farbe): string {
    if (!$liste) {
        return '<div class="muted">nichts gebucht</div>';
    }
    $html = '';
    foreach ($liste as $k) {
        $html .= '<div style="display:flex;align-items:center;gap:.5rem;margin:.15rem 0"><span style="flex:0 0 11rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . e($k['label']) . '</span>'
            . '<span class="bar" style="flex:1 1 auto;height:8px"><span class="bar__fill" style="display:block;height:100%;width:' . (int)$k['anteil'] . '%;background:' . e($k['color'] ?: $farbe) . '"></span></span>'
            . '<span style="flex:0 0 9rem;text-align:right;font-variant-numeric:tabular-nums"><strong>' . (int)$k['anteil'] . ' %</strong> <span class="muted">' . e(money($k['betrag'], false)) . '</span></span></div>';
    }
    return $html;
};
?>
<div class="chips" style="margin-top:.35rem">
  <?php foreach ($liste as $l): ?>
    <a class="chip" href="<?= e($l['url']) ?>" style="border-left:3px solid <?= e($farben[$l['typ']] ?? '#94a3b8') ?>"
       title="<?= e(TP_LINK_TYPEN[$l['typ']]['label'] . ($l['zusatz'] !== '' ? ' · ' . $l['zusatz'] : '')) ?>">
      <span class="muted small"><?= e(TP_LINK_TYPEN[$l['typ']]['label']) ?></span> <?= e($l['label']) ?><?= $l['zusatz'] !== '' ? ' <span class="muted small">' . e($l['zusatz']) . '</span>' : '' ?>
    </a>
  <?php endforeach; ?>
</div>
<?php foreach ($liste as $l): if ($l['typ'] !== 'budget' || !isset($budgets[(int)$l['id']])) { continue; } $b = $budgets[(int)$l['id']]; ?>
  <div class="small" style="margin:.4rem 0 0 .5rem;padding-left:.6rem;border-left:2px solid #15803d">
    <div><strong>Budget <?= (int)$b['jahr'] ?></strong> · Zuweisung <?= e(money($b['budget'])) ?> + eingegangen <?= e(money($b['einnahmen'])) ?><?= $b['zugesagt'] > 0 ? ' (dazu ' . e(money($b['zugesagt'])) . ' zugesagt)' : '' ?>
      = verfügbar <?= e(money($b['verfuegbar'])) ?> · gebucht <?= e(money($b['ausgaben'])) ?> (<?= (int)round($b['quote']) ?> %)<?= $b['offen'] > 0 ? ', davon ' . e(money($b['offen'])) . ' noch nicht bezahlt' : '' ?>
      · <strong>frei <?= e(money($b['frei'])) ?></strong><?= $b['geplant'] > 0 ? ' <span class="muted">(nach Planung ' . e(money($b['frei'] - $b['geplant'])) . ')</span>' : '' ?><?= !empty($b['stichtag']) ? ' · Stichtag ' . e(de_date((string)$b['stichtag'])) : '' ?></div>
    <div class="grid2" style="margin-top:.3rem">
      <div><div class="muted" style="margin-bottom:.1rem">Ausgaben nach Zweck</div><?= $anteilZeile($b['ausgaben_zwecke'], '#94a3b8') ?></div>
      <div><div class="muted" style="margin-bottom:.1rem">Einnahmen nach Zweck</div><?= $anteilZeile($b['einnahmen_zwecke'], '#15803d') ?><?= $b['forderungen'] > 0 ? '<div class="muted">dazu ' . e(money($b['forderungen'])) . ' abgerechnet oder gestellt, noch nicht da</div>' : '' ?></div>
    </div>
    <?php if ($b['toepfe']): ?>
      <div class="muted" style="margin-top:.3rem">Töpfe: <?php $teile = []; foreach ($b['toepfe'] as $t) { $teile[] = e($t['label']) . ' ' . e(money($t['ist'], false)) . ' von ' . e(money($t['soll'], false)) . ' (' . (int)$t['anteil'] . ' %)'; } echo implode(' · ', $teile); ?></div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php foreach ($liste as $l): if ($l['typ'] !== 'event' || !isset($events[(int)$l['id']])) { continue; } $b = $events[(int)$l['id']]; ?>
  <div class="small" style="margin:.3rem 0 0 .5rem;padding-left:.6rem;border-left:2px solid #c2410c">
    <?php foreach (tp_event_kurzbericht_zeilen($b) as $i => $zeile): ?>
      <div<?= $i === 0 ? '' : ' class="muted"' ?>><?= e($zeile) ?></div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
