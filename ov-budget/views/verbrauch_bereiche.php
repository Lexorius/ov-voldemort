<?php
/** @var int $jahr @var array $jahre @var array $zeilen @var array $ohneBereich @var array $gesamt @var bool $bisHeute @var bool $plaetzeDa */
$zelle = static function (array $w, bool $muted = false): string {
    if ($w['menge'] <= 0 && $w['kosten'] <= 0) {
        return '<span class="muted">–</span>';
    }
    return '<span' . ($muted ? ' class="muted"' : '') . '>' . e(menge($w['menge'], $w['einheit'], 0)) . '</span>'
        . ($w['kosten'] > 0 ? '<br><small class="muted">' . e(money($w['kosten'])) . '</small>' : '');
};
$gesamtKosten = 0.0;
foreach ($gesamt as $w) {
    $gesamtKosten += $w['kosten'];
}
?>
<div class="pagehead">
  <div>
    <h1>Kosten je Bereich <?= (int)$jahr ?></h1>
    <p>Was Gebäude, Hallen, Stockwerke und Räume an Strom, Gas und Wasser verbrauchen und kosten – aus den Zählern,
      die für den jeweiligen Bereich zuständig sind. Ein Hauptzähler steht für sein Gebäude, seine Unterzähler teilen
      es auf Stockwerke und Hallen auf; was kein Unterzähler erfasst, bleibt als „nicht weiter aufgeteilt" beim Gebäude.
      Kosten nach den hinterlegten Tarifen<?= $bisHeute ? ', bis heute' : '' ?>.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('verbrauch', ['jahr' => $jahr])) ?>">Zum Verbrauch</a>
    <a class="btn btn--sec" href="<?= e(url('standorte')) ?>">Standorte</a>
  </div>
</div>

<div class="tabs">
  <?php foreach ($jahre as $j): ?>
    <a class="tab<?= $j === $jahr ? ' is-active' : '' ?>" href="<?= e(url('verbrauch_bereiche', ['jahr' => $j])) ?>"><?= (int)$j ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$plaetzeDa): ?>
  <section class="card"><div class="empty">Noch keine Stell- und Lagerplätze angelegt. Die Leitung legt sie unter Verwaltung → Stell- und Lagerplätze an;
    danach bekommt jeder Zähler im Formular den Bereich, für den er zuständig ist.</div></section>
<?php elseif (!$zeilen): ?>
  <section class="card"><div class="empty">Noch kein Zähler ist einem Bereich zugeordnet. Im Zählerformular unter „Zuständig für" das Gebäude,
    das Stockwerk oder die Halle wählen, die der Zähler misst.</div></section>
<?php else: ?>
<section class="card">
  <div class="tablewrap">
    <table class="data">
      <thead><tr><th>Bereich</th><th>Zähler</th>
        <?php foreach (METER_ARTEN as $a): ?><th class="num"><?= e($a['label']) ?></th><?php endforeach; ?>
        <th class="num">Kosten</th><th class="num">Anteil</th></tr></thead>
      <tbody>
      <?php foreach ($zeilen as $z): $typ = STANDORT_TYPEN[standort_typ($z['typ'])]; ?>
        <tr<?= $z['is_active'] !== 1 ? ' class="is-muted"' : '' ?>>
          <td style="padding-left:<?= .6 + $z['tiefe'] * 1.2 ?>rem"><span title="<?= e($typ['label']) ?>"><?= e($typ['icon']) ?></span>
            <a href="<?= e(url('standorte', ['id' => $z['id']])) ?>"><?= e($z['name']) ?></a></td>
          <td class="small"><?php foreach ($z['eigen'] as $i => $m): ?><?= $i > 0 ? ', ' : '' ?><a href="<?= e(url('meter', ['id' => $m['id'], 'jahr' => $jahr])) ?>"><?= e((string)$m['name']) ?></a><?= (string)($m['rolle'] ?? 'bezug') === 'unter' ? ' <span class="muted">(Unterzähler)</span>' : '' ?><?php endforeach; ?>
            <?php if (!$z['eigen']): ?><span class="muted">in den Bereichen darunter</span><?php endif; ?></td>
          <?php foreach (METER_ARTEN as $key => $a): ?><td class="num"><?= $zelle($z['je_art'][$key]) ?></td><?php endforeach; ?>
          <td class="num"><?= $z['kosten'] > 0 ? '<strong>' . e(money($z['kosten'])) . '</strong>' : '<span class="muted">–</span>' ?></td>
          <td class="num small"><?= $z['anteil'] !== null ? e(number_format($z['anteil'], 1, ',', '.')) . ' %<br><span class="muted">von ' . e((string)$z['anteil_von']) . '</span>' : '' ?></td>
        </tr>
        <?php if ($z['unter'] !== null): ?>
          <tr class="zeile-ohne-rand">
            <td style="padding-left:<?= .6 + $z['tiefe'] * 1.2 + 1.2 ?>rem" class="small muted" colspan="2">davon in Teilbereichen gemessen · nicht weiter aufgeteilt</td>
            <?php foreach (METER_ARTEN as $key => $a): ?>
              <td class="num small"><?= $zelle($z['unter'][$key], true) ?><?= ($z['unter'][$key]['menge'] > 0 || $z['rest'][$key]['menge'] > 0) ? '<div class="muted" style="margin-top:.2rem">· ' . e(menge($z['rest'][$key]['menge'], $z['rest'][$key]['einheit'], 0)) . ($z['rest'][$key]['kosten'] > 0 ? ' <small>' . e(money($z['rest'][$key]['kosten'])) . '</small>' : '') . '</div>' : '' ?></td>
            <?php endforeach; ?>
            <td></td><td></td>
          </tr>
        <?php endif; ?>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><th>Alle Hauptzähler</th><th></th>
        <?php foreach (METER_ARTEN as $key => $a): ?><th class="num"><?= $zelle($gesamt[$key]) ?></th><?php endforeach; ?>
        <th class="num"><?= $gesamtKosten > 0 ? e(money($gesamtKosten)) : '–' ?></th><th></th></tr></tfoot>
    </table>
  </div>
  <p class="small muted" style="margin:.6rem 0 0">Unterzähler kosten den Arbeitspreis ihres Hauptzählers, der Grundpreis bleibt beim Hauptzähler.
    Der Anteil bezieht sich auf den Bereich des Hauptzählers, zu dem der Zähler gehört.</p>
</section>
<?php endif; ?>

<?php if ($ohneBereich): ?>
<section class="card">
  <div class="card__head">
    <h2>Zähler ohne Bereich</h2>
    <span class="muted small"><?= count($ohneBereich) ?></span>
  </div>
  <p class="small muted">Diese Zähler fehlen in der Tabelle. Im Zählerformular unter „Zuständig für" den Bereich wählen, den sie messen.</p>
  <div class="chips">
    <?php foreach ($ohneBereich as $m): ?>
      <a class="chip" href="<?= e(url(can('manage_verbrauch') ? 'meter_edit' : 'meter', ['id' => $m['id']])) ?>"><?= e((string)$m['name']) ?>
        <span class="muted small"><?= e(METER_ARTEN[(string)$m['art']]['label'] ?? $m['art']) ?> · <?= e(menge($m['menge'], (string)$m['einheit'], 0)) ?><?= $m['kosten'] > 0 ? ' · ' . e(money($m['kosten'])) : '' ?></span></a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
