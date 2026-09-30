<?php
/** @var int $jahr @var array $bericht */
$b = $bericht;
echo render_partial('partials/bericht_kopf', [
    'titel'      => 'Verbrauch ' . $jahr,
    'untertitel' => 'Strom, Gas und Wasser · ' . count($b['zaehler']) . ' Zähler' . ($b['bis_heute'] ? ' · bis heute' : ''),
    'rueck'      => url('verbrauch', ['jahr' => $jahr]),
    'quer'       => true,
]);
?>

<div class="kacheln">
  <?php foreach (METER_ARTEN as $key => $a): $s = $b['je_art'][$key]; if ($s['zaehler'] === 0) { continue; } ?>
    <div class="kachel" style="border-top:4px solid <?= e($a['color']) ?>">
      <div class="kachel__label"><?= e($a['label']) ?> <?= (int)$jahr ?></div>
      <div class="kachel__wert"><?= e(menge($s['summe'], '', 0)) ?> <small><?= e($s['einheit']) ?></small></div>
      <div class="kachel__hinweis">
        <?php if ($s['delta_prozent'] !== null): ?>
          <span class="<?= $s['delta_prozent'] > 0 ? 'plus' : 'minus' ?>"><?= $s['delta_prozent'] > 0 ? '+' : '' ?><?= e(number_format($s['delta_prozent'], 1, ',', '.')) ?> %</span> zum Vorjahr
        <?php else: ?>kein Vorjahreswert<?php endif; ?>
        · <?= e(money($s['kosten'])) ?>
      </div>
    </div>
  <?php endforeach; ?>
  <div class="kachel">
    <div class="kachel__label">Kosten gesamt <?= (int)$jahr ?></div>
    <div class="kachel__wert"><?= e(money($b['kosten'], false)) ?> <small>€</small></div>
    <div class="kachel__hinweis"><?= $b['kosten_vorjahr'] !== null ? 'Vorjahr ' . e(money($b['kosten_vorjahr'])) : 'nach den hinterlegten Tarifen' ?>
      <?= $b['ohne_tarif'] > 0 ? '· <span class="plus">' . (int)$b['ohne_tarif'] . ' Zähler ohne Tarif</span>' : '' ?></div>
  </div>
</div>

<?php foreach (METER_ARTEN as $key => $a): $s = $b['je_art'][$key]; if ($s['zaehler'] === 0) { continue; } ?>
  <div class="abschnitt">
    <h2><?= e($a['label']) ?> je Monat <span class="klein">· <?= (int)$s['zaehler'] ?> Zähler zusammen</span></h2>
    <?= render_partial('partials/bericht_balken', ['monate' => $s['monate'], 'vorjahr' => $s['monate_vorjahr'],
        'einheit' => $s['einheit'], 'farbe' => $a['color'], 'jahr' => $jahr]) ?>
  </div>
<?php endforeach; ?>

<div class="abschnitt">
  <h2>Alle Zähler</h2>
  <table class="liste">
    <thead><tr><th>Zähler</th><th>Art</th><th>Standort</th><th class="num"><?= (int)$jahr ?></th><th class="num"><?= (int)$jahr - 1 ?></th>
               <th class="num">Veränderung</th><th class="num">Kosten</th><th class="num">Stände</th></tr></thead>
    <tbody>
    <?php foreach ($b['zaehler'] as $z): $a = METER_ARTEN[(string)$z['art']]; ?>
      <tr>
        <td><strong><?= e((string)$z['name']) ?></strong><?= (int)$z['is_active'] !== 1 ? ' <span class="klein">stillgelegt</span>' : '' ?>
          <?= trim((string)$z['zaehlernummer']) !== '' ? '<div class="klein">Nr. ' . e((string)$z['zaehlernummer']) . '</div>' : '' ?></td>
        <td><?= e($a['label']) ?></td>
        <td><?= e((string)$z['standort']) ?></td>
        <td class="num"><?= e(menge($z['summe'], (string)$z['einheit'], 0)) ?></td>
        <td class="num"><?= $z['summe_vorjahr'] === null ? '<span class="klein">–</span>' : e(menge($z['summe_vorjahr'], (string)$z['einheit'], 0)) ?></td>
        <td class="num"><?= $z['delta_prozent'] === null ? '<span class="klein">–</span>'
            : '<span class="' . ($z['delta_prozent'] > 0 ? 'plus' : 'minus') . '">' . ($z['delta_prozent'] > 0 ? '+' : '') . e(number_format($z['delta_prozent'], 1, ',', '.')) . ' %</span>' ?></td>
        <td class="num"><?= e(money($z['kosten'])) ?><?= $z['ohne_tarif'] > 0 ? ' <span class="klein plus">*</span>' : '' ?></td>
        <td class="num"><?= (int)$z['ablesungen'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="6">Kosten gesamt</td><td class="num"><?= e(money($b['kosten'])) ?></td><td></td></tr></tfoot>
  </table>
  <?php if ($b['ohne_tarif'] > 0): ?><p class="klein">* Für mindestens einen Monat war kein Tarif hinterlegt – die Kosten sind unvollständig.</p><?php endif; ?>
  <p class="klein">Verbrauch zwischen zwei Ablesungen gleichmäßig auf die Tage verteilt; Kosten nach dem Tarif, der am 15. des Monats gilt.
    <?= $b['bis_heute'] ? 'Das Berichtsjahr läuft noch – Vorjahreswerte beziehen sich auf denselben Zeitraum.' : '' ?></p>
</div>

<div class="fuss"><span><?= e((string)setting('app_name', 'OV-Multitool')) ?></span><span>Verbrauchsbericht <?= (int)$jahr ?></span></div>
</body>
</html>
