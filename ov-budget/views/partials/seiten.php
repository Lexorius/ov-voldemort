<?php
/**
 * Blätterer unter einer langen Liste.
 * @var array  $blatt   aus seiten_blaettern()
 * @var string $route   Seite, auf der geblättert wird
 * @var array  $params  weitere Parameter der Adresse (id, jahr …)
 * @var string $anker   Sprungziel, z. B. 'staende'
 */
$params ??= [];
$anker = isset($anker) && $anker !== '' ? '#' . $anker : '';
if ((int)$blatt['seiten'] <= 1) {
    return;
}
$link = static fn(int $s): string => url($route, $params + ['seite' => $s]) . $anker;
?>
<nav class="seiten" aria-label="Seiten">
  <?php if ($blatt['seite'] > 1): ?>
    <a class="btn btn--sec btn--sm" href="<?= e($link($blatt['seite'] - 1)) ?>">‹ Neuer</a>
  <?php else: ?>
    <span class="btn btn--sec btn--sm is-disabled" aria-disabled="true">‹ Neuer</span>
  <?php endif; ?>
  <span class="seiten__zahlen">
    <?php foreach (seiten_zahlen((int)$blatt['seite'], (int)$blatt['seiten']) as $z): ?>
      <?php if ($z === null): ?><span class="muted">…</span>
      <?php elseif ($z === (int)$blatt['seite']): ?><strong class="seiten__aktiv" aria-current="page"><?= $z ?></strong>
      <?php else: ?><a href="<?= e($link($z)) ?>"><?= $z ?></a><?php endif; ?>
    <?php endforeach; ?>
  </span>
  <?php if ($blatt['seite'] < $blatt['seiten']): ?>
    <a class="btn btn--sec btn--sm" href="<?= e($link($blatt['seite'] + 1)) ?>">Älter ›</a>
  <?php else: ?>
    <span class="btn btn--sec btn--sm is-disabled" aria-disabled="true">Älter ›</span>
  <?php endif; ?>
  <span class="muted small">Seite <?= (int)$blatt['seite'] ?> von <?= (int)$blatt['seiten'] ?></span>
</nav>
