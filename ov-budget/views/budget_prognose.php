<?php
/** @var int $jahr @var array $jahre @var array $prognose @var bool $darf */
$p = $prognose;
$k = $p['kategorien'];
$z = $p['zaehler'];
$s = $p['serien'] ?? ['zeilen' => [], 'summe' => ['bisher' => 0.0, 'rest' => 0.0, 'hochrechnung' => 0.0]];
$zahlen = $p['zahlen'];
$monate = $k['monate'];
$fenster = $k['fenster'];
$fensterText = $fenster ? date('m/Y', strtotime($fenster[0] . '-01')) . ' – ' . date('m/Y', strtotime(end($fenster) . '-01')) : '';
$punkt = static fn(string $farbe): string => '<span style="display:inline-block;width:.7rem;height:.7rem;border-radius:50%;background:' . e($farbe ?: '#94a3b8') . ';margin-right:.3rem"></span>';
$schwankungBadge = static fn(string $t): string => '<span class="badge badge--outline" style="color:' . match ($t) { 'regelmäßig' => '#15803d', 'schwankend' => '#a16207', 'stark schwankend' => '#b91c1c', default => '#64748b' } . '">' . e($t) . '</span>';
?>
<div class="pagehead">
  <div>
    <h1>Hochrechnung <?= (int)$jahr ?></h1>
    <p>Was das Jahr voraussichtlich kostet: Rechnungen, die an der Ausgabe als <strong>wiederkehrend</strong> markiert sind
      (Telefon, Versicherung), laufen mit dem zuletzt gebuchten Betrag weiter; alle übrigen Ausgaben je Kategorie aus dem
      Monatsdurchschnitt dieses Jahres (bisher gebucht durch vergangene Monate) – schwankende wie Tanken also gemittelt;
      Kategorien mit Buchungen in weniger als drei Monaten werden nicht hochgerechnet, da zählt nur das Gebuchte.
      Dazu Energie und Wasser aus den Zählern. Im jungen Jahr zählt das Fenster <?= e($fensterText) ?>.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('budget', ['jahr' => $jahr])) ?>">Zur Übersicht</a>
  </div>
</div>

<?= render_partial('partials/budget_tabs', ['jahr' => $jahr]) ?>

<div class="tabs">
  <?php foreach ($jahre as $j): ?>
    <a class="tab<?= $j === $jahr ? ' is-active' : '' ?>" href="<?= e(url('budget_prognose', ['jahr' => $j])) ?>"><?= (int)$j ?></a>
  <?php endforeach; ?>
</div>

<div class="stats">
  <div class="stat"><div class="stat__label">Verfügbar <?= (int)$jahr ?></div><div class="stat__value"><?= e(money_rounded($zahlen['verfuegbar'], false)) ?></div><div class="stat__hint">Jahresbudget plus Einnahmen</div></div>
  <div class="stat"><div class="stat__label">Ausgaben bisher</div><div class="stat__value"><?= e(money_rounded($zahlen['ausgaben'], false)) ?></div><div class="stat__hint"><?= e(number_format($monate['vergangen'], 1, ',', '.')) ?> von 12 Monaten um</div></div>
  <div class="stat"><div class="stat__label">Hochrechnung Jahresende</div><div class="stat__value"><?= e(money_rounded($p['hochrechnung'], false)) ?></div><div class="stat__hint"><?= $s['summe']['hochrechnung'] > 0 ? e(money_rounded($s['summe']['hochrechnung'], false)) . ' wiederkehrend · ' : '' ?><?= e(money_rounded($k['summe']['hochrechnung'], false)) ?> übrige<?= $z['summe'] > 0 ? ' · ' . e(money_rounded($z['summe'] - $z['erloes'], false)) . ' Energie und Wasser' : '' ?></div></div>
  <div class="stat"><div class="stat__label"><?= $p['frei'] >= 0 ? 'Voraussichtlich frei' : 'Voraussichtlich überzogen um' ?></div>
    <div class="stat__value" style="<?= $p['frei'] < 0 ? 'color:var(--bad)' : '' ?>"><?= e(money_rounded(abs($p['frei']), false)) ?></div>
    <div class="stat__hint"><?= $zahlen['geplant'] > 0 ? 'nach geplanten Vorhaben (' . e(money_rounded($zahlen['geplant'], false)) . ') noch <strong' . ($p['frei_mit_planung'] < 0 ? ' style="color:var(--bad)"' : '') . '>' . e(money_rounded($p['frei_mit_planung'], false)) . '</strong>' : 'am Jahresende' ?></div></div>
</div>

<?php if ($monate['rest'] <= 0): ?>
  <div class="alert alert--info">Das Jahr <?= (int)$jahr ?> ist um – die Hochrechnung entspricht den gebuchten Ausgaben. Die Durchschnitte zeigen, womit im nächsten Jahr zu rechnen ist.</div>
<?php endif; ?>

<section class="card">
  <div class="card__head"><h2>Wiederkehrende Rechnungen</h2><span class="muted small"><?= count($s['zeilen']) ?> Serien · an der Ausgabe als monatlich, vierteljährlich oder jährlich markiert</span></div>
  <?php if (!$s['zeilen']): ?>
    <div class="empty">Noch keine Ausgabe als wiederkehrend markiert. Beim Anlegen oder Bearbeiten einer Ausgabe unter „Beleg" den Rhythmus wählen – etwa die Telefonrechnung monatlich, die Versicherung jährlich.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Rechnung</th><th>Rhythmus</th><th class="num">Zuletzt</th><th class="num">Bisher <?= (int)$jahr ?></th><th class="num">Noch offen</th><th class="num">Rest</th><th class="num">Jahresende</th></tr></thead>
        <tbody>
        <?php foreach ($s['zeilen'] as $r): ?>
          <tr>
            <td><strong><?= e($r['bezeichnung']) ?></strong><div class="small muted"><?= e(implode(' · ', array_filter([$r['lieferant'], $r['kategorie_label']]))) ?></div></td>
            <td><span class="badge badge--outline">↻ <?= e($r['rhythmus_label']) ?></span></td>
            <td class="num"><?= e(money($r['betrag'])) ?><div class="small muted"><?= e(de_date($r['letzte'])) ?></div></td>
            <td class="num"><?= $r['bisher'] > 0 ? e(money($r['bisher'])) . '<div class="small muted">' . (int)$r['anzahl_jahr'] . '×</div>' : '<span class="muted">–</span>' ?></td>
            <td class="num"><?= (int)$r['offen'] ?>×</td>
            <td class="num"><?= $r['rest'] > 0 ? e(money($r['rest'])) : '<span class="muted">–</span>' ?></td>
            <td class="num"><strong><?= e(money($r['hochrechnung'])) ?></strong></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th>Summe</th><th></th><th></th><th class="num"><?= e(money($s['summe']['bisher'])) ?></th><th></th><th class="num"><?= e(money($s['summe']['rest'])) ?></th><th class="num"><?= e(money($s['summe']['hochrechnung'])) ?></th></tr></tfoot>
      </table>
    </div>
  <?php endif; ?>
</section>

<form method="post" action="<?= e(url('budget_prognose', ['jahr' => $jahr])) ?>">
<?= csrf_field() ?>
<section class="card">
  <div class="card__head"><h2>Übrige Ausgaben je Kategorie</h2><span class="muted small"><?= count($k['zeilen']) ?> Kategorien · ohne die markierten wiederkehrenden Rechnungen · gebucht = bezahlt oder offen</span></div>
  <?php if (!$k['zeilen']): ?>
    <div class="empty">Keine übrigen Ausgaben in den letzten zwölf Monaten.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Kategorie</th><th class="num">Bisher <?= (int)$jahr ?></th><th class="num">Ø je Monat</th><th>Verlauf</th><th>Hochrechnen als</th><th class="num">Rest bis Jahresende</th><th class="num">Jahresende</th></tr></thead>
        <tbody>
        <?php foreach ($k['zeilen'] as $r): $st = $r['statistik']; ?>
          <tr>
            <td><?= $punkt($r['color']) ?><strong><?= e($r['label']) ?></strong>
              <?php if ($r['empfehlung'] === 'fest'): ?><div class="small muted">läuft regelmäßig – als fester Betrag von <?= e(money($st['durchschnitt'])) ?> je Monat eintragen?</div><?php endif; ?>
              <?php if ($r['notiz'] !== '' && !$darf): ?><div class="small muted"><?= e($r['notiz']) ?></div><?php endif; ?></td>
            <td class="num"><?= $r['bisher'] > 0 ? e(money($r['bisher'])) : '<span class="muted">–</span>' ?></td>
            <td class="num"><?= match ($r['basis']) {
                'jahr'    => e(money($r['schnitt'])) . '<div class="small muted">' . e(number_format($monate['vergangen'], 1, ',', '.')) . ' Monate dieses Jahr</div>',
                'fenster' => e(money($r['schnitt'])) . '<div class="small muted">letzte ' . (int)$st['monate'] . ' Monate</div>',
                default   => '<span class="muted">–</span><div class="small muted">Buchungen in ' . (int)$st['monate_mit'] . ' Monat(en) – nicht hochgerechnet</div>',
            } ?></td>
            <td><?= $schwankungBadge($r['schwankung_text']) ?></td>
            <td class="nowrap">
              <?php if ($darf): ?>
                <select name="art[<?= (int)$r['id'] ?>]" aria-label="Art">
                  <?php foreach (PROGNOSE_ARTEN as $wert => $text): ?><option value="<?= e($wert) ?>"<?= $r['art'] === $wert ? ' selected' : '' ?>><?= e($text) ?></option><?php endforeach; ?>
                </select>
                <input type="text" name="betrag[<?= (int)$r['id'] ?>]" inputmode="decimal" value="<?= $r['betrag_monat'] > 0 ? e(number_format($r['betrag_monat'], 2, ',', '.')) : '' ?>" placeholder="€/Monat" style="width:6.5rem" aria-label="Betrag je Monat">
                <input type="text" name="notiz[<?= (int)$r['id'] ?>]" value="<?= e($r['notiz']) ?>" placeholder="Notiz" maxlength="200" style="width:9rem" aria-label="Notiz">
              <?php else: ?>
                <?= e(PROGNOSE_ARTEN[$r['art']]) ?><?= $r['art'] === 'fest' ? ' · ' . e(money($r['betrag_monat'])) : '' ?>
              <?php endif; ?>
            </td>
            <td class="num"><?= $r['rest'] > 0 ? e(money($r['rest'])) . '<div class="small muted">' . e(money($r['je_monat'])) . ' × ' . e(number_format($monate['rest'], 1, ',', '.')) . '</div>' : '<span class="muted">–</span>' ?></td>
            <td class="num"><strong><?= e(money($r['hochrechnung'])) ?></strong></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th>Summe</th><th class="num"><?= e(money($k['summe']['bisher'])) ?></th><th></th><th></th><th></th><th class="num"><?= e(money($k['summe']['rest'])) ?></th><th class="num"><?= e(money($k['summe']['hochrechnung'])) ?></th></tr></tfoot>
      </table>
    </div>
    <?php if ($darf): ?><div class="btnrow mt"><button class="btn" type="submit">Speichern</button></div><?php endif; ?>
  <?php endif; ?>
</section>
</form>

<section class="card">
  <div class="card__head"><h2>Energie und Wasser aus den Zählern</h2><span class="muted small">Hauptzähler mit Tarif</span></div>
  <?php if (!$z['zeilen']): ?>
    <div class="empty">Kein Zähler mit Tarif – oder das Verbrauchsmodul ist nicht in Gebrauch.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Zähler</th><th class="num">Bisher <?= (int)$jahr ?></th><th class="num">Ø je Monat</th><th class="num">Fehlende Monate</th><th class="num">Jahresende</th></tr></thead>
        <tbody>
        <?php foreach ($z['zeilen'] as $r): ?>
          <tr>
            <td><strong><?= e($r['name']) ?></strong> <span class="muted small"><?= e(ucfirst($r['art'])) ?><?= $r['erloes'] ? ' · Erlös (Einspeisung)' : '' ?></span>
              <?php if ($r['ohne_tarif'] > 0): ?><div class="small muted"><?= (int)$r['ohne_tarif'] ?> Monat(e) ohne Tarif – nicht gerechnet</div><?php endif; ?></td>
            <td class="num"><?= $r['erloes'] ? '− ' : '' ?><?= e(money($r['bisher'])) ?></td>
            <td class="num"><?= $r['monate_mit'] > 0 ? e(money($r['durchschnitt'])) . '<div class="small muted">aus ' . (int)$r['monate_mit'] . ' Monat(en)</div>' : '<span class="muted">–</span>' ?></td>
            <td class="num"><?= $r['rest'] > 0 ? e(money($r['rest'])) : '<span class="muted">–</span>' ?></td>
            <td class="num"><strong><?= $r['erloes'] ? '− ' : '' ?><?= e(money($r['hochrechnung'])) ?></strong></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th>Summe</th><th></th><th></th><th></th><th class="num"><?= e(money($z['summe'] - $z['erloes'])) ?></th></tr></tfoot>
      </table>
    </div>
    <p class="small muted">Werden die Abschläge für Strom, Gas oder Wasser auch als Ausgaben gebucht (etwa Kategorie „Nebenkosten"), zählen sie oben und hier – dann die Kategorie oben auf „nicht hochrechnen" stellen, damit nichts doppelt zählt.</p>
  <?php endif; ?>
</section>
