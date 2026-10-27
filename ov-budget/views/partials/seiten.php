<?php
/**
 * Blätterer unter einer langen Liste.
 * @var array  $blatt   aus seiten_blaettern()
 * @var string $route   Seite, auf der geblättert wird
 * @var array  $params  weitere Parameter der Adresse (id, jahr …)
 * @var string $anker   Sprungziel, z. B. 'staende'
 * @var string $param   Name des Seitenparameters (Vorgabe 'seite' – mehrere Listen auf einer Seite brauchen eigene)
 * @var string $zurueck Beschriftung des Knopfs zur vorigen Seite (Vorgabe '‹ Neuer')
 * @var string $weiter  Beschriftung des Knopfs zur nächsten Seite (Vorgabe 'Älter ›')
 */
$params ??= [];
$param ??= 'seite';
$zurueck ??= '‹ Neuer';
$weiter ??= 'Älter ›';
$anker = isset($anker) && $anker !== '' ? '#' . $anker : '';
if ((int)$blatt['seiten'] <= 1) {
    return;
}
$link = static fn(int $s): string => url($route, array_merge($params, [$param => $s])) . $anker;
?>
<nav class="seiten" aria-label="Seiten">
  <?php if ($blatt['seite'] > 1): ?>
    <a class="btn btn--sec btn--sm" href="<?= e($link($blatt['seite'] - 1)) ?>"><?= e($zurueck) ?></a>
  <?php else: ?>
    <span class="btn btn--sec btn--sm is-disabled" aria-disabled="true"><?= e($zurueck) ?></span>
  <?php endif; ?>
  <span class="seiten__zahlen">
    <?php foreach (seiten_zahlen((int)$blatt['seite'], (int)$blatt['seiten']) as $z): ?>
      <?php if ($z === null): ?><span class="muted">…</span>
      <?php elseif ($z === (int)$blatt['seite']): ?><strong class="seiten__aktiv" aria-current="page"><?= $z ?></strong>
      <?php else: ?><a href="<?= e($link($z)) ?>"><?= $z ?></a><?php endif; ?>
    <?php endforeach; ?>
  </span>
  <?php if ($blatt['seite'] < $blatt['seiten']): ?>
    <a class="btn btn--sec btn--sm" href="<?= e($link($blatt['seite'] + 1)) ?>"><?= e($weiter) ?></a>
  <?php else: ?>
    <span class="btn btn--sec btn--sm is-disabled" aria-disabled="true"><?= e($weiter) ?></span>
  <?php endif; ?>
  <span class="muted small">Seite <?= (int)$blatt['seite'] ?> von <?= (int)$blatt['seiten'] ?><?= !empty($blatt['gesamt']) ? ' · ' . (int)$blatt['von'] . '–' . (int)$blatt['bis'] . ' von ' . (int)$blatt['gesamt'] : '' ?></span>
</nav>
