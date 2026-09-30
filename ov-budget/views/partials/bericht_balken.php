<?php
/**
 * Monatsbalken für die Berichte: zwei Jahre nebeneinander, ohne JavaScript,
 * druckfest. Beträge in der Zählereinheit.
 *
 * @var array  $monate     [1..12 => float|null]  Berichtsjahr
 * @var array  $vorjahr    [1..12 => float|null]  Jahr davor
 * @var string $einheit
 * @var string $farbe
 * @var int    $jahr
 */
$monatsnamen = ['', 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
$max = max(array_merge([0.0], array_map(static fn($v) => (float)($v ?? 0), $monate), array_map(static fn($v) => (float)($v ?? 0), $vorjahr)));
?>
<div class="balken">
  <?php for ($m = 1; $m <= 12; $m++):
      $a = (float)($monate[$m] ?? 0);
      $b = (float)($vorjahr[$m] ?? 0);
      $ha = $max > 0 ? max(($a > 0 ? 2 : 0), $a / $max * 100) : 0;
      $hb = $max > 0 ? max(($b > 0 ? 2 : 0), $b / $max * 100) : 0;
  ?>
    <div class="balken__monat">
      <div class="balken__paar">
        <div class="balken__stab balken__stab--vorjahr" style="height:<?= number_format($hb, 1, '.', '') ?>%"
             title="<?= e(($jahr - 1) . ' ' . $monatsnamen[$m] . ': ' . menge($b, $einheit)) ?>"></div>
        <div class="balken__stab" style="height:<?= number_format($ha, 1, '.', '') ?>%;background:<?= e($farbe) ?>"
             title="<?= e($jahr . ' ' . $monatsnamen[$m] . ': ' . menge($a, $einheit)) ?>"></div>
      </div>
      <div class="balken__wert"><?= $monate[$m] === null ? '' : e(menge($a, '', 0)) ?></div>
      <div class="balken__name"><?= e($monatsnamen[$m]) ?></div>
    </div>
  <?php endfor; ?>
</div>
<div class="legende"><span class="legende__feld" style="background:<?= e($farbe) ?>"></span> <?= (int)$jahr ?>
  <span class="legende__feld legende__feld--vorjahr"></span> <?= (int)$jahr - 1 ?>
  <span class="klein">· Werte in <?= e($einheit) ?></span></div>
