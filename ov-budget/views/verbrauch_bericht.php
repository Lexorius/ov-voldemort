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
  <?php if ($b['solar']['vorhanden']): $so = $b['solar']; ?>
    <div class="kachel" style="border-top:4px solid #ca8a04">
      <div class="kachel__label">Solar <?= (int)$jahr ?></div>
      <div class="kachel__wert"><?= e(menge($so['erzeugung'], '', 0)) ?> <small>kWh erzeugt</small></div>
      <div class="kachel__hinweis"><?= e(menge($so['einspeisung'], 'kWh', 0)) ?> eingespeist, <?= e(menge($so['eigenverbrauch'], 'kWh', 0)) ?> selbst genutzt
        · Autarkie <?= (int)$so['autarkie'] ?> %<?= $so['erloes'] > 0 ? ' · Erlös ' . e(money($so['erloes'])) : '' ?></div>
    </div>
  <?php endif; ?>
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
        <td><?= (string)($z['rolle'] ?? 'bezug') === 'unter' ? '<span class="klein">↳ </span>' : '' ?><strong><?= e((string)$z['name']) ?></strong><?= (int)$z['is_active'] !== 1 ? ' <span class="klein">stillgelegt</span>' : '' ?>
          <?= trim((string)$z['zaehlernummer']) !== '' ? '<div class="klein">Nr. ' . e((string)$z['zaehlernummer']) . '</div>' : '' ?>
          <?php $rolle = (string)($z['rolle'] ?? 'bezug'); if ($rolle === 'unter'): ?>
            <div class="klein">Unterzähler von <?= e((string)$z['parent_name']) ?><?= $z['anteil'] !== null ? ', ' . e(number_format($z['anteil'], 1, ',', '.')) . ' %' : '' ?></div>
          <?php elseif ($rolle !== 'bezug'): ?>
            <div class="klein"><?= e(explode(' – ', METER_ROLLEN[$rolle])[0]) ?> (Solar)</div>
          <?php endif; ?></td>
        <td><?= e($a['label']) ?></td>
        <td><?= e((string)$z['standort']) ?></td>
        <td class="num"><?= e(menge($z['summe'], (string)$z['einheit'], 0)) ?></td>
        <td class="num"><?= $z['summe_vorjahr'] === null ? '<span class="klein">–</span>' : e(menge($z['summe_vorjahr'], (string)$z['einheit'], 0)) ?></td>
        <td class="num"><?= $z['delta_prozent'] === null ? '<span class="klein">–</span>'
            : '<span class="' . ($z['delta_prozent'] > 0 ? 'plus' : 'minus') . '">' . ($z['delta_prozent'] > 0 ? '+' : '') . e(number_format($z['delta_prozent'], 1, ',', '.')) . ' %</span>' ?></td>
        <td class="num"><?php if (in_array((string)($z['rolle'] ?? 'bezug'), ['unter', 'erzeugung'], true)): ?><span class="klein">–</span>
          <?php else: ?><?= !empty($z['erloes']) ? '+ ' : '' ?><?= e(money($z['kosten'])) ?><?= $z['ohne_tarif'] > 0 ? ' <span class="klein plus">*</span>' : '' ?><?php endif; ?></td>
        <td class="num"><?= (int)$z['ablesungen'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="6">Kosten gesamt<?= $b['erloes'] > 0 ? ' abzüglich ' . e(money($b['erloes'])) . ' Einspeiseerlös' : '' ?></td>
      <td class="num"><?= e(money($b['kosten'] - $b['erloes'])) ?></td><td></td></tr></tfoot>
  </table>
  <?php if ($b['ohne_tarif'] > 0): ?><p class="klein">* Für mindestens einen Monat war kein Tarif hinterlegt – die Kosten sind unvollständig.</p><?php endif; ?>
  <p class="klein">Verbrauch zwischen zwei Ablesungen gleichmäßig auf die Tage verteilt; Kosten nach dem Tarif, der am 15. des Monats gilt.
    <?= $b['bis_heute'] ? 'Das Berichtsjahr läuft noch – Vorjahreswerte beziehen sich auf denselben Zeitraum.' : '' ?></p>
</div>

<div class="fuss"><span><?= e((string)setting('app_name', 'OV-Multitool')) ?></span><span>Verbrauchsbericht <?= (int)$jahr ?></span></div>
</body>
</html>
