<?php
/** @var array $meter @var int $jahr @var array $bericht */
$a = METER_ARTEN[(string)$meter['art']];
$einheit = (string)$meter['einheit'];
$b = $bericht;
$delta = $b['delta_prozent'];
echo render_partial('partials/bericht_kopf', [
    'titel'      => (string)$meter['name'],
    'untertitel' => $a['label'] . ((string)($meter['rolle'] ?? 'bezug') !== 'bezug' ? ' · ' . explode(' – ', METER_ROLLEN[(string)$meter['rolle']])[0] : '') . ' · Verbrauchsbericht ' . $jahr
        . (trim((string)$meter['zaehlernummer']) !== '' ? ' · Zähler Nr. ' . $meter['zaehlernummer'] : '')
        . (trim((string)$meter['standort']) !== '' ? ' · ' . $meter['standort'] : ''),
    'rueck'      => url('meter', ['id' => $meter['id'], 'jahr' => $jahr]),
    'quer'       => false,
]);
?>

<div class="kacheln">
  <div class="kachel">
    <div class="kachel__label">Verbrauch <?= (int)$jahr ?></div>
    <div class="kachel__wert"><?= e(menge($b['summe'], '', 0)) ?> <small><?= e($einheit) ?></small></div>
    <div class="kachel__hinweis"><?= $b['bis_heute'] ? 'bis heute' : 'ganzes Jahr' ?></div>
  </div>
  <div class="kachel">
    <div class="kachel__label">Vorjahr <?= (int)$jahr - 1 ?></div>
    <div class="kachel__wert"><?= $b['summe_vorjahr'] === null ? '–' : e(menge($b['summe_vorjahr'], '', 0)) ?> <small><?= e($einheit) ?></small></div>
    <div class="kachel__hinweis"><?= $delta === null ? 'kein Vergleich möglich'
        : '<span class="' . ($delta > 0 ? 'plus' : 'minus') . '">' . ($delta > 0 ? '+' : '') . e(number_format($delta, 1, ',', '.')) . ' %</span> gegenüber dem Vorjahr'
          . ($b['bis_heute'] ? ', gleicher Zeitraum' : '') ?></div>
  </div>
  <?php $tarifArt = meter_tarif_art($meter); if ($tarifArt !== null): ?>
  <div class="kachel">
    <div class="kachel__label"><?= $tarifArt === 'einspeisung' ? 'Erlös' : 'Kosten' ?> <?= (int)$jahr ?></div>
    <div class="kachel__wert"><?= e(money($b['kosten']['gesamt'], false)) ?> <small>€</small></div>
    <div class="kachel__hinweis"><?= $b['kosten']['ohne_tarif'] > 0 ? (int)$b['kosten']['ohne_tarif'] . ' Monat(e) ohne Tarif'
        : ($b['tarif'] ? e((string)$b['tarif']['name']) : 'kein Tarif hinterlegt') ?></div>
  </div>
  <?php elseif ((string)($meter['rolle'] ?? 'bezug') === 'unter'): ?>
  <div class="kachel">
    <div class="kachel__label">Unterzähler</div>
    <div class="kachel__wert" style="font-size:13pt"><?= e((string)($meter['parent_name'] ?? '')) ?></div>
    <div class="kachel__hinweis">Kosten stecken im Hauptzähler</div>
  </div>
  <?php endif; ?>
  <div class="kachel">
    <div class="kachel__label">Je Tag</div>
    <div class="kachel__wert"><?= e(menge($b['je_tag'], '', 1)) ?> <small><?= e($einheit) ?></small></div>
    <div class="kachel__hinweis">im Durchschnitt über <?= (int)$b['tage'] ?> Tage</div>
  </div>
  <div class="kachel">
    <div class="kachel__label">Stärkster Monat</div>
    <div class="kachel__wert"><?= $b['spitze'] ? e($b['spitze']['name']) : '–' ?></div>
    <div class="kachel__hinweis"><?= $b['spitze'] ? e(menge($b['spitze']['wert'], $einheit, 0)) : '' ?></div>
  </div>
  <div class="kachel">
    <div class="kachel__label">Ablesungen</div>
    <div class="kachel__wert"><?= (int)$b['ablesungen'] ?></div>
    <div class="kachel__hinweis">Quelle: <?= e(METER_QUELLEN[(string)$meter['quelle']] ?? '') ?><?= $b['letzter_stand'] !== null ? ', letzter Stand ' . e(menge($b['letzter_stand'], $einheit, 3)) : '' ?></div>
  </div>
</div>

<div class="abschnitt">
  <h2>Verbrauch je Monat</h2>
  <?= render_partial('partials/bericht_balken', ['monate' => $b['monate'], 'vorjahr' => $b['monate_vorjahr'],
      'einheit' => $einheit, 'farbe' => $a['color'], 'jahr' => $jahr]) ?>
</div>

<div class="abschnitt">
  <h2>Wann wird verbraucht?</h2>
  <?= render_partial('partials/verbrauch_profil', ['profil' => $b['profil'], 'einheit' => $einheit, 'farbe' => $a['color'], 'bericht' => true]) ?>
</div>

<div class="abschnitt">
  <h2>Monate im Einzelnen</h2>
  <table class="liste">
    <thead><tr><th>Monat</th><th class="num"><?= (int)$jahr ?></th><th class="num"><?= (int)$jahr - 1 ?></th>
               <th class="num">Veränderung</th><th class="num"><?= !empty($b['kosten']['erloes']) ? 'Erlös' : 'Kosten' ?> <?= (int)$jahr ?></th></tr></thead>
    <tbody>
    <?php $namen = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    for ($m = 1; $m <= 12; $m++): $x = $b['monate'][$m]; $y = $b['monate_vorjahr'][$m]; ?>
      <tr>
        <td><?= e($namen[$m]) ?></td>
        <td class="num"><?= $x === null ? '<span class="klein">–</span>' : e(menge($x, $einheit)) ?></td>
        <td class="num"><?= $y === null ? '<span class="klein">–</span>' : e(menge($y, $einheit)) ?></td>
        <td class="num"><?php if ($x !== null && $y !== null && $y > 0): $d = ($x - $y) / $y * 100; ?>
            <span class="<?= $d > 0 ? 'plus' : 'minus' ?>"><?= $d > 0 ? '+' : '' ?><?= e(number_format($d, 0)) ?> %</span>
          <?php else: ?><span class="klein">–</span><?php endif; ?></td>
        <td class="num"><?= $b['kosten']['monate'][$m] === null ? '<span class="klein">–</span>' : e(money($b['kosten']['monate'][$m])) ?></td>
      </tr>
    <?php endfor; ?>
    </tbody>
    <tfoot><tr>
      <td>Summe</td>
      <td class="num"><?= e(menge($b['summe'], $einheit)) ?></td>
      <td class="num"><?= $b['summe_vorjahr'] === null ? '–' : e(menge($b['summe_vorjahr'], $einheit)) ?></td>
      <td class="num"><?= $delta === null ? '–' : ($delta > 0 ? '+' : '') . e(number_format($delta, 1, ',', '.')) . ' %' ?></td>
      <td class="num"><?= e(money($b['kosten']['gesamt'])) ?></td>
    </tr></tfoot>
  </table>
  <?php if ($b['tarif']): ?>
    <p class="klein">Tarif: <?= e((string)$b['tarif']['name']) ?>, <?= e(number_format((float)$b['tarif']['arbeitspreis'], 4, ',', '.')) ?> €/<?= e((string)$b['tarif']['einheit']) ?><?= (float)$b['tarif']['grundpreis_monat'] > 0 ? ' + ' . e(money((float)$b['tarif']['grundpreis_monat'])) . ' je Monat' : '' ?><?= (float)$meter['umrechnung'] !== 1.0 ? ', Umrechnung × ' . e(num_input($meter['umrechnung'], true)) : '' ?>.
      Zwischen zwei Ablesungen wird der Verbrauch gleichmäßig auf die Tage verteilt.</p>
  <?php endif; ?>
</div>

<div class="fuss"><span><?= e((string)setting('app_name', 'OV-Multitool')) ?> · <?= e((string)$meter['name']) ?></span><span>Verbrauchsbericht <?= (int)$jahr ?></span></div>
</body>
</html>
