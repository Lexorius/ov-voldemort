<?php
/** @var int $jahr @var array $jahre @var array $auswertung @var int $konten */
$a = $auswertung;
$saldo = $a['summe']['einnahmen'] - $a['summe']['ausgaben'];
$prozent = static fn(?float $p): string => $p === null ? '' : number_format($p, 1, ',', '.') . ' %';
$zeile = static function (array $z, string $titel, string $farbe) use ($prozent): string {
    $html = '<tr><td><strong><span style="display:inline-block;width:.7rem;height:.7rem;border-radius:50%;background:' . e($farbe) . ';margin-right:.3rem"></span>' . e($titel) . '</strong>';
    if ($z['posten']) {
        $html .= '<details><summary class="small">' . count($z['posten']) . ' Posten</summary><table class="data" style="margin-top:.3rem"><tbody>';
        foreach ($z['posten'] as $p) {
            $html .= '<tr><td class="small"><span class="badge" style="background:' . e($p['color']) . '">' . ($p['art'] === 'ausgabe' ? 'Ausgabe' : 'Einnahme') . '</span> '
                . e($p['label']) . ($p['ohne_kategorie'] ? ' <span class="muted">(Buchungen ohne Kategorie)</span>' : '') . '</td>'
                . '<td class="num small">' . (int)$p['anzahl'] . '×</td><td class="num small">' . e(money($p['betrag'])) . '</td></tr>';
        }
        $html .= '</tbody></table></details>';
    }
    $html .= '</td><td class="num">' . ($z['ausgaben'] > 0 ? e(money($z['ausgaben'])) : '<span class="muted">–</span>') . '</td>'
        . '<td class="num small muted">' . e($prozent($z['anteil_ausgaben'])) . '</td>'
        . '<td class="num">' . ($z['einnahmen'] > 0 ? e(money($z['einnahmen'])) : '<span class="muted">–</span>') . '</td>'
        . '<td class="num small muted">' . e($prozent($z['anteil_einnahmen'])) . '</td>'
        . '<td class="num small">' . (int)$z['anzahl'] . '</td></tr>';
    return $html;
};
?>
<div class="pagehead">
  <div>
    <h1>Objektkonten <?= (int)$jahr ?></h1>
    <p>Die zweite Sicht auf die Buchungen: Ausgaben und Einnahmen je Objektkonto, in dem mehrere Kategorien zusammenlaufen.
      Gezählt wird, was gebucht ist (bezahlt oder offen) – wie in der Übersicht. Die Posten je Konto lassen sich aufklappen.</p>
  </div>
  <div class="btnrow">
    <?php if (can('manage_budget')): ?><a class="btn btn--sec" href="<?= e(url('objektkonten')) ?>">Objektkonten pflegen</a><?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('budget', ['jahr' => $jahr])) ?>">Zur Übersicht</a>
  </div>
</div>

<?= render_partial('partials/budget_tabs', ['jahr' => $jahr]) ?>

<div class="tabs">
  <?php foreach ($jahre as $j): ?>
    <a class="tab<?= $j === $jahr ? ' is-active' : '' ?>" href="<?= e(url('budget_objektkonten', ['jahr' => $j])) ?>"><?= (int)$j ?></a>
  <?php endforeach; ?>
</div>

<div class="stats">
  <div class="stat"><div class="stat__label">Ausgaben <?= (int)$jahr ?></div><div class="stat__value"><?= e(money_rounded($a['summe']['ausgaben'], false)) ?></div><div class="stat__hint">gebucht, alle Konten</div></div>
  <div class="stat"><div class="stat__label">Einnahmen <?= (int)$jahr ?></div><div class="stat__value"><?= e(money_rounded($a['summe']['einnahmen'], false)) ?></div><div class="stat__hint">gebucht, alle Konten</div></div>
  <div class="stat"><div class="stat__label">Saldo</div><div class="stat__value" style="color:<?= $saldo < 0 ? 'var(--bad)' : 'inherit' ?>"><?= e(money_rounded($saldo, false)) ?></div><div class="stat__hint">Einnahmen minus Ausgaben</div></div>
  <div class="stat"><div class="stat__label">Objektkonten</div><div class="stat__value"><?= (int)$konten ?></div><div class="stat__hint">aktiv</div></div>
</div>

<section class="card">
  <?php if (!$a['zeilen'] && !$a['ohne']['posten']): ?>
    <div class="empty"><?= $konten === 0 ? 'Noch kein Objektkonto angelegt.' : 'Für ' . (int)$jahr . ' ist noch nichts gebucht.' ?>
      <?php if (can('manage_budget') && $konten === 0): ?><br><a href="<?= e(url('objektkonto_edit')) ?>">Erstes Objektkonto anlegen</a><?php endif; ?></div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Objektkonto</th><th class="num">Ausgaben</th><th class="num">Anteil</th><th class="num">Einnahmen</th><th class="num">Anteil</th><th class="num">Buchungen</th></tr></thead>
        <tbody>
          <?php foreach ($a['zeilen'] as $z): ?>
            <?= $zeile($z, objektkonto_titel($z['konto']) . ((int)($z['konto']['is_active'] ?? 1) !== 1 ? ' (stillgelegt)' : ''), (string)$z['konto']['color']) ?>
          <?php endforeach; ?>
          <?php if ($a['ohne']['posten']): ?>
            <?= $zeile($a['ohne'], 'Ohne Objektkonto', '#94a3b8') ?>
          <?php endif; ?>
        </tbody>
        <tfoot><tr><th>Gesamt</th><th class="num"><?= e(money($a['summe']['ausgaben'])) ?></th><th class="num small">100 %</th><th class="num"><?= e(money($a['summe']['einnahmen'])) ?></th><th class="num small">100 %</th><th></th></tr></tfoot>
      </table>
    </div>
    <?php if ($a['ohne']['posten'] && can('manage_budget')): ?>
      <p class="small muted" style="margin-top:.6rem">„Ohne Objektkonto" enthält Kategorien, die noch keinem Konto zugeordnet sind, und Buchungen ohne Kategorie.
        Zuordnen unter <a href="<?= e(url('objektkonten')) ?>">Objektkonten pflegen</a>.</p>
    <?php endif; ?>
  <?php endif; ?>
</section>
