<?php
/** @var int $jahr @var array $jahre @var array $budgets @var array $ohneTopf
 *  @var array $zahlen @var array $offenGeplant @var array $veranstaltungen @var ?array $verbrauchHinweis @var array $verlauf
 *  @var array $kategorien @var array $einnahmeKategorien
 *  @var array $monate @var array $monateEin @var array $jeTopf @var array $letzte
 *  @var array $zuBestellen @var array $zurFreigabe */
$warn = setting_int('budget_warn_prozent', 90);
$gesamt = $zahlen['budget'];
$einnahmen = $zahlen['einnahmen'];
$ausgaben = $zahlen['ausgaben'];
// Verfügbar ist die Zuweisung plus alles, was der OV selbst eingenommen hat
$verfuegbar = $zahlen['verfuegbar'];
$rest = $zahlen['frei'];
$quote = $zahlen['quote'];
$quoteCls = ($verfuegbar > 0 && $ausgaben > $verfuegbar) ? 'is-over' : ($quote >= $warn ? 'is-warn' : '');

$summeToepfe = array_sum(array_map(static fn($b) => (float)$b['betrag_netto'], $budgets));
$verplant = array_sum(array_map(static fn($b) => (float)$b['verplant'], $budgets));
$offenOhne = array_sum(array_map(static fn($w) => (float)$w['netto_gesamt'], $ohneTopf));

$summeBestellen = array_sum(array_map(static fn($w) => (float)$w['netto_gesamt'], $zuBestellen));
$zurFreigabeMax = 15;

/** Zeile der Freigabeliste mit passendem Knopf */
$freigabeZeile = static function (array $w, string $aktion): string {
    $html = '<tr><td><a href="' . e(url('wish', ['id' => $w['id']])) . '">' . e($w['bezeichnung']) . '</a>'
        . '<div class="small muted">'
        . e(implode(' · ', array_filter([
            $w['fachgruppe_label'],
            $w['budget_name'] ? 'Topf: ' . $w['budget_name'] : '',
            $aktion === 'bestellt' && $w['freigegeben_am']
                ? 'freigegeben ' . de_date(substr((string)$w['freigegeben_am'], 0, 10)) . ($w['freigeber'] ? ' von ' . $w['freigeber'] : '')
                : '',
            $w['lieferant'] ? 'bei ' . $w['lieferant'] : '',
        ])))
        . '</div></td>'
        . '<td>' . ($aktion === 'freigeben'
            ? badge($w['status_label'] ? ['label' => $w['status_label'], 'color' => $w['status_color']] : null)
            : badge($w['dring_label'] ? ['label' => $w['dring_label'], 'color' => $w['dring_color']] : null)) . '</td>'
        . '<td class="num nowrap">' . e(money((float)$w['netto_gesamt'], false)) . '</td>';

    $knopf = '';
    if ($aktion === 'freigeben') {
        $grund = wish_release_denied($w);
        if ($grund === null) {
            $knopf = '<button class="btn btn--ok btn--sm" type="submit" name="action" value="freigeben" data-confirm="'
                . e('„' . $w['bezeichnung'] . '“ für ' . money((float)$w['netto_gesamt']) . ' zur Bestellung freigeben?')
                . '">Freigegeben, bitte bestellen</button>';
        } elseif (order_rights_for_user()['freigeben']) {
            // Grundsätzlich berechtigt, aber nicht für diesen Wunsch – kurz sagen, warum
            $knopf = '<span class="small muted" title="' . e($grund) . '">'
                . (str_contains($grund, 'Freigabegrenze') ? 'über deiner Grenze' : 'nicht durch dich') . '</span>';
        }
    } elseif (can('order_wish')) {
        $knopf = '<button class="btn btn--sec btn--sm" type="submit" name="action" value="bestellt">Ist bestellt</button>';
    }

    if ($knopf !== '' && str_starts_with($knopf, '<button')) {
        $html .= '<td class="nowrap"><form method="post" action="' . e(url('wish_action')) . '" class="inline-form">'
            . csrf_field()
            . '<input type="hidden" name="id" value="' . (int)$w['id'] . '">'
            . '<input type="hidden" name="back" value="' . e(current_url() . '#bestellung') . '">'
            . $knopf . '</form></td>';
    } elseif ($knopf !== '') {
        $html .= '<td class="nowrap">' . $knopf . '</td>';
    }
    return $html . '</tr>';
};

$maxMonat = max(array_merge([0.0], array_values($monate), array_values($monateEin)));
$zugesagt = (float)($zahlen['einnahmen_zugesagt'] ?? 0);
$forderungen = (float)($zahlen['einnahmen_forderungen'] ?? 0);
$ausgabenOffen = (float)($zahlen['ausgaben_offen'] ?? 0);
$ausgabenGeplant = (float)($zahlen['geplant_buchungen'] ?? 0);
$stichtagInfo = ['stichtag' => $zahlen['stichtag'] ?? null, 'tage' => $zahlen['stichtag_tage'] ?? null, 'gesperrt' => $zahlen['gesperrt'] ?? false];
$mitZusagen = (float)($zahlen['verfuegbar_mit_zusagen'] ?? $verfuegbar);
// Höchster Monat über alle Stapel – Einnahmen samt Zusagen und Forderungen
$stapelEin = [];
$stapelAus = [];
for ($m = 1; $m <= 12; $m++) {
    $stapelEin[$m] = (float)($verlauf['eingegangen'][$m] ?? 0) + (float)($verlauf['zugesagt'][$m] ?? 0) + (float)($verlauf['forderungen'][$m] ?? 0);
    $stapelAus[$m] = (float)($verlauf['bezahlt'][$m] ?? 0) + (float)($verlauf['offen'][$m] ?? 0) + (float)($verlauf['geplant'][$m] ?? 0);
}
$maxMonat = max(array_merge([0.0], array_values($stapelEin), array_values($stapelAus)));
$monatsnamen = ['', 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

/** Kategorieblock für eine Richtung */
$kategorieBlock = static function (array $liste, float $summe, string $art) use ($jahr): string {
    if (!$liste) {
        return '<div class="empty">Nichts erfasst.</div>';
    }
    $max = max(array_map(static fn($k) => (float)$k['betrag'], $liste));
    $html = '';
    foreach ($liste as $k) {
        $b = (float)$k['betrag'];
        $breite = $max > 0 ? $b / $max * 100 : 0;
        $anteil = $summe > 0 ? $b / $summe * 100 : 0;
        $name = $k['id']
            ? '<a href="' . e(url('expenses', ['jahr' => $jahr, 'art' => $art, 'kategorie_id' => $k['id']])) . '">'
                . e($k['label']) . '</a>'
            : '<span class="muted">ohne Kategorie</span>';
        $html .= '<div style="margin-bottom:.8rem">'
            . '<div style="display:flex;justify-content:space-between;gap:.6rem;flex-wrap:wrap">'
            . '<span>' . $name . ' <span class="muted small">(' . (int)$k['anzahl'] . ')</span></span>'
            . '<span class="small nowrap"><strong>' . e(money_rounded($b, false)) . '</strong> '
            . '<span class="muted">' . number_format($anteil, 0) . '&nbsp;%</span></span>'
            . '</div><div class="bar"><div class="bar__fill" style="width:'
            . number_format($breite, 1, '.', '') . '%;background:' . e($k['color'] ?: '#94a3b8') . '"></div></div></div>';
    }
    return $html;
};
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)setting('budget_modul_name', 'Budget')) ?> <?= (int)$jahr ?></h1>
    <p><?= nl2br(e((string)setting('budget_intro', ''))) ?></p>
    <?php if (($hinweis = rounding_note()) !== ''): ?>
      <p class="muted small" style="margin:.35rem 0 0">
        <span class="badge badge--outline">~ <?= e($hinweis) ?></span>
      </p>
    <?php endif; ?>
  </div>
  <div class="btnrow">
    <?php if (can('manage_budget')): ?>
      <a class="btn" href="<?= e(url('expense_edit', ['jahr' => $jahr, 'art' => 'ausgabe'])) ?>">+ Ausgabe</a>
      <a class="btn btn--ok" href="<?= e(url('expense_edit', ['jahr' => $jahr, 'art' => 'einnahme'])) ?>">+ Einnahme</a>
      <a class="btn btn--sec" href="<?= e(url('budget_year_edit', ['jahr' => $jahr])) ?>">Jahresbudget</a>
      <a class="btn btn--sec" href="<?= e(url('budget_pots', ['jahr' => $jahr])) ?>">Budgettöpfe verwalten</a>
    <?php endif; ?>
  </div>
</div>

<?= render_partial('partials/budget_tabs', ['jahr' => $jahr]) ?>

<div class="tabs">
  <?php foreach ($jahre as $j): ?>
    <a class="tab<?= $j === $jahr ? ' is-active' : '' ?>" href="<?= e(url('budget', ['jahr' => $j])) ?>"><?= (int)$j ?></a>
  <?php endforeach; ?>
</div>

<?php if ($gesamt <= 0 && $einnahmen <= 0): ?>
  <div class="alert alert--info">
    Für <?= (int)$jahr ?> ist noch kein Gesamtbudget hinterlegt.
    <?php if (can('manage_budget')): ?>
      <a href="<?= e(url('budget_year_edit', ['jahr' => $jahr])) ?>">Jetzt eintragen</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="stats">
  <div class="stat">
    <div class="stat__label">Jahresbudget</div>
    <div class="stat__value"><?= e(money_rounded($gesamt, false)) ?></div>
    <div class="stat__hint"><?php if ($stichtagInfo['stichtag']): ?>
        <?php if ($stichtagInfo['gesperrt']): ?><span style="color:var(--bad);font-weight:700">geschlossen seit <?= e(de_date($stichtagInfo['stichtag'])) ?></span>
        <?php else: ?>Stichtag <?= e(de_date($stichtagInfo['stichtag'])) ?> · <span<?= $stichtagInfo['tage'] <= 14 ? ' style="color:var(--bad);font-weight:700"' : ($stichtagInfo['tage'] <= 30 ? ' style="color:#b45309;font-weight:700"' : '') ?>>noch <?= (int)$stichtagInfo['tage'] ?> Tag<?= (int)$stichtagInfo['tage'] === 1 ? '' : 'e' ?></span><?php endif; ?>
      <?php else: ?>Zuweisung für <?= (int)$jahr ?><?php endif; ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Einnahmen</div>
    <div class="stat__value" style="color:var(--ok)">+<?= e(money_rounded($einnahmen, false)) ?></div>
    <div class="stat__hint"><?php
      $teile = [];
      if ($zugesagt > 0) { $teile[] = 'dazu <span style="color:#15803d">' . e(money_rounded($zugesagt, false)) . ' zugesagt</span>, noch nicht da'; }
      if (($zahlen['einnahmen_forderungen'] ?? 0) > 0) { $teile[] = '<span style="color:#b45309">' . e(money_rounded($zahlen['einnahmen_forderungen'], false)) . ' abgerechnet oder gestellt</span>, zählt noch nicht'; }
      $w = $zahlen['abrechnungen_wartend'] ?? null;
      if ($w && $w['ueber30'] > 0) {
          $farbe = EINNAHME_WARN_FARBEN[$w['rot'] > 0 ? 'rot' : ($w['orange'] > 0 ? 'orange' : 'gelb')];
          $teile[] = '<a href="' . e(url('expenses', ['jahr' => $jahr, 'art' => 'einnahme']) . '#einnahmen-stand') . '" style="color:' . $farbe . ';font-weight:700">'
              . (int)$w['ueber30'] . ' Abrechnung' . ($w['ueber30'] === 1 ? '' : 'en') . ' wartet über 30 Tage</a> (' . e(einnahmen_wartend_text($w)) . ')';
      }
      echo $teile ? implode(' · ', $teile) : 'Einsätze, THG und Übriges';
    ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Ausgaben gebucht</div>
    <div class="stat__value">−<?= e(money_rounded($ausgaben, false)) ?></div>
    <div class="stat__hint"><?php
      $teile = [];
      if ($ausgabenOffen > 0) { $teile[] = 'davon <span style="color:#b45309">' . e(money_rounded($ausgabenOffen, false)) . ' noch nicht bezahlt</span>'; }
      if ($ausgabenGeplant > 0) { $teile[] = 'dazu ' . e(money_rounded($ausgabenGeplant, false)) . ' geplant'; }
      echo $teile ? implode(' · ', $teile) : 'bezahlt oder Rechnung liegt vor';
    ?></div>
  </div>
  <div class="stat">
    <div class="stat__label"><?= $rest >= 0 ? 'Noch frei' : 'Überzogen um' ?></div>
    <div class="stat__value" style="<?= $rest < 0 ? 'color:var(--bad)' : '' ?>"><?= e(money_rounded(abs($rest), false)) ?></div>
    <div class="stat__hint"><?= $zahlen['geplant'] > 0
        ? 'nach Planung noch <strong' . ($zahlen['frei_nach_planung'] < 0 ? ' style="color:var(--bad)"' : '') . '>' . e(money_rounded($zahlen['frei_nach_planung'], false)) . '</strong> von ' . e(money_rounded($verfuegbar, false))
        : 'von ' . e(money_rounded($verfuegbar, false)) . ' verfügbar' ?><?= $zugesagt > 0 ? ' · mit Zusagen ' . e(money_rounded($zahlen['frei_mit_zusagen'] ?? ($rest + $zugesagt), false)) : '' ?></div>
  </div>
</div>

<?php if ($stichtagInfo['stichtag'] && ($stichtagInfo['gesperrt'] || $stichtagInfo['tage'] <= 30)): ?>
  <div class="alert <?= $stichtagInfo['gesperrt'] ? 'alert--error' : 'alert--warn' ?>">
    <?php if ($stichtagInfo['gesperrt']): ?>
      <strong>Das Haushaltsjahr <?= (int)$jahr ?> ist seit dem <?= e(de_date($stichtagInfo['stichtag'])) ?> geschlossen.</strong>
      Neue Ausgaben lassen sich nur noch als geplant vormerken.
    <?php else: ?>
      <strong>Noch <?= (int)$stichtagInfo['tage'] ?> Tag<?= (int)$stichtagInfo['tage'] === 1 ? '' : 'e' ?> bis zum Stichtag <?= e(de_date($stichtagInfo['stichtag'])) ?>.</strong>
      Danach darf kein Geld mehr auf das Jahresbudget ausgegeben werden – noch frei: <?= e(money_rounded($rest)) ?><?= $zahlen['geplant'] > 0 ? ', nach Planung ' . e(money_rounded($zahlen['frei_nach_planung'])) : '' ?>.
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($zahlen['geplant'] > 0 || $offenGeplant): ?>
<section class="card" id="planung">
  <div class="card__head">
    <h2>Offen und geplant</h2>
    <span class="small muted">geplant <strong><?= e(money_rounded($zahlen['geplant'])) ?></strong>
      <?php if ($zahlen['geplant_veranstaltungen'] > 0): ?>· davon <?= e(money_rounded($zahlen['geplant_veranstaltungen'])) ?> aus <?= (int)$zahlen['geplant_anzahl'] ?> Veranstaltung(en)<?= $zahlen['geplant_verpflegung'] > 0 ? ', Verpflegung ' . e(money_rounded($zahlen['geplant_verpflegung'])) : '' ?><?php endif; ?></span>
  </div>
  <?php if (!empty($veranstaltungen['liste'])): ?>
    <h3>Veranstaltungen <?= (int)$jahr ?></h3>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Veranstaltung</th><th class="num">geplant</th><th class="num">davon Verpflegung</th><th class="num">schon gebucht</th><th class="num">noch zu erwarten</th></tr></thead>
        <tbody>
        <?php foreach ($veranstaltungen['liste'] as $v): ?>
          <tr>
            <td><a href="<?= e(url('event', ['id' => $v['id']])) ?>"><?= e($v['titel']) ?></a> <span class="small muted"><?= e(de_date(substr($v['beginn'], 0, 10))) ?></span></td>
            <td class="num"><?= e(money_rounded($v['geplant'], false)) ?></td>
            <td class="num"><?= $v['verpflegung'] > 0 ? e(money_rounded($v['verpflegung'], false)) : '<span class="muted">–</span>' ?></td>
            <td class="num"><?= $v['gebucht'] > 0 ? e(money_rounded($v['gebucht'], false)) : '<span class="muted">–</span>' ?></td>
            <td class="num"><strong><?= e(money_rounded($v['rest'], false)) ?></strong></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php if ($offenGeplant): ?>
    <h3 class="mt">Buchungen ohne Zahlung</h3>
    <div class="tablewrap">
      <table class="data">
        <tbody>
        <?php foreach ($offenGeplant as $b): $ein = $b['art'] === 'einnahme'; ?>
          <tr>
            <td class="nowrap small"><?= e(de_date($b['datum'])) ?></td>
            <td>
              <?php if (can('manage_budget')): ?>
                <a href="<?= e(url('expense_edit', ['id' => $b['id']])) ?>"><?= e($b['bezeichnung']) ?></a>
              <?php else: ?><?= e($b['bezeichnung']) ?><?php endif; ?>
              <?= buchung_status_badge((string)$b['status']) ?>
              <?php if (!empty($b['veranstaltung_titel'])): ?><span class="small muted">· <?= e((string)$b['veranstaltung_titel']) ?></span><?php endif; ?>
            </td>
            <td class="small"><?= e($b['kategorie_label'] ?: '–') ?></td>
            <td class="num nowrap" style="<?= $ein ? 'color:var(--ok)' : '' ?>">
              <?= $ein ? '+' : '−' ?><?= e(money_rounded((float)$b['betrag_brutto'], false)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <p class="small muted" style="margin:.6rem 0 0">Offene Rechnungen stecken schon in Einnahmen und Ausgaben. Geplante
    Buchungen und noch nicht gebuchte Veranstaltungskosten kommen erst beim Buchen dazu.</p>
</section>
<?php endif; ?>

<?php if ($verbrauchHinweis && ($verbrauchHinweis['kosten'] > 0 || $verbrauchHinweis['hochrechnung'])): $vh = $verbrauchHinweis; ?>
  <div class="alert alert--info">
    <strong>Nebenkosten aus dem Verbrauch:</strong>
    <?= (int)$vh['vorjahr'] ?> kosteten Strom, Gas und Wasser nach den Tarifen <?= e(money_rounded($vh['kosten'])) ?><?= $vh['laeuft'] ? ' bis heute' : '' ?><?php
      $teile = [];
      foreach ($vh['je_art'] as $art => $k) { $teile[] = METER_ARTEN[$art]['label'] . ' ' . money_rounded($k, false); }
      ?><?= $teile ? ' (' . e(implode(', ', $teile)) . ')' : '' ?>.
    <?php if ($vh['hochrechnung'] !== null): ?>
      Aufs ganze Jahr hochgerechnet etwa <strong><?= e(money_rounded($vh['hochrechnung'])) ?></strong> – ein Anhalt für die Töpfe <?= (int)$jahr ?>.
    <?php endif; ?>
    <?php if ($vh['ohne_tarif'] > 0): ?><span class="muted">(<?= (int)$vh['ohne_tarif'] ?> Zähler ohne vollständigen Tarif)</span><?php endif; ?>
    <a href="<?= e(url('verbrauch', ['jahr' => $vh['vorjahr']])) ?>">Zum Verbrauch</a>
  </div>
<?php endif; ?>

<section class="card">
  <div class="card__head">
    <h2>Mittel <?= (int)$jahr ?></h2>
    <span class="small">
      <?= e(money_rounded($gesamt, false)) ?> Budget
      <?php if ($einnahmen > 0): ?>+ <?= e(money_rounded($einnahmen, false)) ?> eingegangen<?php endif; ?>
      = <strong><?= e(money_rounded($verfuegbar)) ?></strong> verfügbar
    </span>
  </div>
  <?php if ($verfuegbar > 0): ?>
    <?php
      $bezahltAnteil = $verfuegbar > 0 ? min(100.0, max(0.0, $ausgaben - $ausgabenOffen) / $verfuegbar * 100) : 0.0;
      $offenAnteil = $verfuegbar > 0 ? min(100.0 - $bezahltAnteil, $ausgabenOffen / $verfuegbar * 100) : 0.0;
      $geplantAnteil = $verfuegbar > 0 ? min(100.0 - $bezahltAnteil - $offenAnteil, $zahlen['geplant'] / $verfuegbar * 100) : 0.0;
    ?>
    <div class="bar" style="height:14px;display:flex">
      <div class="bar__fill <?= $quoteCls ?>" style="width:<?= number_format($bezahltAnteil, 1, '.', '') ?>%;border-radius:0"
           title="<?= e('bezahlt: ' . money_rounded($ausgaben - $ausgabenOffen)) ?>"></div>
      <?php if ($offenAnteil > 0): ?>
        <div style="width:<?= number_format($offenAnteil, 1, '.', '') ?>%;background:repeating-linear-gradient(135deg,#b45309 0 4px,#f59e0b 4px 8px)"
             title="<?= e('gebucht, noch nicht bezahlt: ' . money_rounded($ausgabenOffen)) ?>"></div>
      <?php endif; ?>
      <?php if ($geplantAnteil > 0): ?>
        <div style="width:<?= number_format($geplantAnteil, 1, '.', '') ?>%;background:repeating-linear-gradient(135deg,#94a3b8 0 3px,transparent 3px 6px);border:1px dashed #94a3b8;box-sizing:border-box"
             title="<?= e('geplant: ' . money_rounded($zahlen['geplant'])) ?>"></div>
      <?php endif; ?>
    </div>
    <p class="small muted" style="margin:.5rem 0 0">
      <?= e(money_rounded($ausgaben, false)) ?> gebucht (<?= number_format($quote, 0) ?>&nbsp;%)<?= $ausgabenOffen > 0
          ? ', davon <span style="color:#b45309">' . e(money_rounded($ausgabenOffen, false)) . ' noch nicht bezahlt</span>' : '' ?><?= $zahlen['geplant'] > 0
          ? ', dazu ' . e(money_rounded($zahlen['geplant'], false)) . ' geplant' : '' ?>.
      Wenn zusätzlich alle offenen Wünsche beschafft würden, kämen
      <strong><?= e(money_rounded($verplant + $offenOhne)) ?></strong> hinzu.
    </p>
    <?php if ($zugesagt > 0 || $forderungen > 0): ?>
      <div style="margin-top:.8rem;padding-top:.6rem;border-top:1px dashed var(--border)">
        <div style="display:flex;justify-content:space-between;gap:.6rem;flex-wrap:wrap" class="small">
          <span><strong>Zugesagt, noch nicht da:</strong> <?= e(money_rounded($zugesagt)) ?>
            <?php if ($forderungen > 0): ?>· <span style="color:#b45309">abgerechnet oder gestellt: <?= e(money_rounded($forderungen)) ?></span><?php endif; ?></span>
          <span>mit Zusagen <strong><?= e(money_rounded($mitZusagen)) ?></strong> verfügbar</span>
        </div>
        <?php $z = $mitZusagen > 0 ? $zugesagt / $mitZusagen * 100 : 0; $f = $mitZusagen > 0 ? min(100 - $z, $forderungen / $mitZusagen * 100) : 0; ?>
        <div class="bar" style="height:8px;display:flex;margin-top:.3rem">
          <div style="width:<?= number_format(max(0, 100 - $z - $f), 1, '.', '') ?>%;background:var(--border)" title="<?= e('verfügbar: ' . money_rounded($verfuegbar)) ?>"></div>
          <div style="width:<?= number_format($z, 1, '.', '') ?>%;background:#15803d" title="<?= e('zugesagt: ' . money_rounded($zugesagt)) ?>"></div>
          <?php if ($f > 0): ?><div style="width:<?= number_format($f, 1, '.', '') ?>%;background:repeating-linear-gradient(135deg,#b45309 0 4px,#fcd34d 4px 8px)" title="<?= e('Forderungen: ' . money_rounded($forderungen)) ?>"></div><?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="empty">Weder Budget noch Einnahmen erfasst.</div>
  <?php endif; ?>
</section>

<section class="card" id="bestellung">
  <div class="card__head">
    <h2>Freigegeben – bitte bestellen</h2>
    <?php if ($zuBestellen): ?>
      <span class="badge" style="background:#ea580c"><?= count($zuBestellen) ?> · <?= e(money($summeBestellen)) ?></span>
    <?php endif; ?>
  </div>
  <?php if (!$zuBestellen): ?>
    <div class="empty">Nichts offen – alle freigegebenen Wünsche sind bestellt.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <tbody><?php foreach ($zuBestellen as $w): ?><?= $freigabeZeile($w, 'bestellt') ?><?php endforeach; ?></tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($zurFreigabe): ?>
    <h3 class="mt">Warten auf Freigabe</h3>
    <div class="tablewrap">
      <table class="data">
        <tbody>
        <?php foreach (array_slice($zurFreigabe, 0, $zurFreigabeMax) as $w): ?><?= $freigabeZeile($w, 'freigeben') ?><?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (count($zurFreigabe) > $zurFreigabeMax): ?>
      <p class="small muted">Die <?= $zurFreigabeMax ?> wichtigsten von <?= count($zurFreigabe) ?> –
        <a href="<?= e(url('wishes')) ?>">alle Wünsche</a></p>
    <?php endif; ?>
  <?php endif; ?>
</section>

<div class="grid2">
  <section class="card">
    <div class="card__head">
      <h2>Ausgaben nach Kategorie</h2>
      <?php if (can('view_expenses')): ?>
        <a class="btn btn--sec btn--sm" href="<?= e(url('expenses', ['jahr' => $jahr, 'art' => 'ausgabe'])) ?>">Alle</a>
      <?php endif; ?>
    </div>
    <?= $kategorieBlock($kategorien, $ausgaben, 'ausgabe') ?>
  </section>

  <section class="card">
    <div class="card__head">
      <h2>Einnahmen nach Kategorie</h2>
      <?php if (can('view_expenses')): ?>
        <a class="btn btn--sec btn--sm" href="<?= e(url('expenses', ['jahr' => $jahr, 'art' => 'einnahme'])) ?>">Alle</a>
      <?php endif; ?>
    </div>
    <?= $kategorieBlock($einnahmeKategorien, $einnahmen, 'einnahme') ?>
  </section>
</div>

<section class="card">
  <div class="card__head">
    <h2>Verlauf über das Jahr</h2>
    <span class="small muted">
      <span class="legend legend--ein"></span> eingegangen
      <span class="legend legend--zusage"></span> zugesagt
      <span class="legend legend--forderung"></span> abgerechnet / gestellt
      <span class="legend legend--aus"></span> bezahlt
      <span class="legend legend--offen"></span> gebucht, noch nicht bezahlt
      <span class="legend legend--geplant"></span> geplant
    </span>
  </div>
  <?php if ($maxMonat <= 0): ?>
    <div class="empty">Noch nichts erfasst.</div>
  <?php else: ?>
    <div class="months">
      <?php
      $h = static fn(float $v): string => number_format($maxMonat > 0 ? $v / $maxMonat * 100 : 0, 1, '.', '');
      for ($m = 1; $m <= 12; $m++):
          $e1 = (float)($verlauf['eingegangen'][$m] ?? 0); $e2 = (float)($verlauf['zugesagt'][$m] ?? 0); $e3 = (float)($verlauf['forderungen'][$m] ?? 0);
          $a1 = (float)($verlauf['bezahlt'][$m] ?? 0); $a2 = (float)($verlauf['offen'][$m] ?? 0); $a3 = (float)($verlauf['geplant'][$m] ?? 0);
      ?>
        <div class="months__col">
          <div class="months__pair">
            <div class="months__stack" title="<?= e($monatsnamen[$m] . ' – eingegangen ' . money_rounded($e1) . ($e2 > 0 ? ', zugesagt ' . money_rounded($e2) : '') . ($e3 > 0 ? ', abgerechnet/gestellt ' . money_rounded($e3) : '')) ?>">
              <?php if ($e3 > 0): ?><div class="months__seg months__seg--forderung" style="height:<?= $h($e3) ?>%"></div><?php endif; ?>
              <?php if ($e2 > 0): ?><div class="months__seg months__seg--zusage" style="height:<?= $h($e2) ?>%"></div><?php endif; ?>
              <div class="months__seg months__seg--ein" style="height:<?= $h($e1) ?>%"></div>
            </div>
            <div class="months__stack" title="<?= e($monatsnamen[$m] . ' – bezahlt ' . money_rounded($a1) . ($a2 > 0 ? ', gebucht ' . money_rounded($a2) : '') . ($a3 > 0 ? ', geplant ' . money_rounded($a3) : '')) ?>">
              <?php if ($a3 > 0): ?><div class="months__seg months__seg--geplant" style="height:<?= $h($a3) ?>%"></div><?php endif; ?>
              <?php if ($a2 > 0): ?><div class="months__seg months__seg--offen" style="height:<?= $h($a2) ?>%"></div><?php endif; ?>
              <div class="months__seg months__seg--aus" style="height:<?= $h($a1) ?>%"></div>
            </div>
          </div>
          <div class="months__label"><?= e($monatsnamen[$m]) ?></div>
        </div>
      <?php endfor; ?>
    </div>
    <p class="small muted" style="margin:.6rem 0 0">Höchster Monatswert: <?= e(money_rounded($maxMonat)) ?></p>
  <?php endif; ?>

  <?php if ($letzte): ?>
    <h3 class="mt">Zuletzt gebucht</h3>
    <div class="tablewrap">
      <table class="data">
        <tbody>
        <?php foreach ($letzte as $b): $ein = $b['art'] === 'einnahme'; ?>
          <tr>
            <td class="nowrap small"><?= e(de_date($b['datum'])) ?></td>
            <td>
              <?php if (can('manage_budget')): ?>
                <a href="<?= e(url('expense_edit', ['id' => $b['id']])) ?>"><?= e($b['bezeichnung']) ?></a>
              <?php else: ?><?= e($b['bezeichnung']) ?><?php endif; ?>
            </td>
            <td class="small"><?= e($b['kategorie_label'] ?: '–') ?></td>
            <td class="num nowrap" style="<?= $ein ? 'color:var(--ok)' : '' ?>">
              <?= $ein ? '+' : '−' ?><?= e(money_rounded((float)$b['betrag_brutto'], false)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="card">
  <div class="card__head">
    <h2>Budgettöpfe</h2>
    <div class="btnrow">
      <span class="muted small"><?= e(money_rounded($summeToepfe)) ?> geplant</span>
      <?php if (can('manage_budget')): ?>
        <a class="btn btn--sec btn--sm" href="<?= e(url('budget_pots', ['jahr' => $jahr])) ?>">Verwalten</a>
        <a class="btn btn--sec btn--sm" href="<?= e(url('budget_edit', ['jahr' => $jahr])) ?>">+ Topf</a>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!$budgets): ?>
    <div class="empty">Für <?= (int)$jahr ?> ist noch kein Budgettopf angelegt.
      Töpfe sind optional – sie unterteilen das Jahresbudget nach Zweck.
      <?php if (can('manage_budget')): ?>
        <br><a href="<?= e(url('budget_edit', ['jahr' => $jahr])) ?>">Topf anlegen</a>
        oder <a href="<?= e(url('budget_pots', ['jahr' => $jahr])) ?>">Töpfe verwalten</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php foreach ($budgets as $b):
        $soll = (float)$b['betrag_netto'];
        $ist = (float)$b['verplant'];
        $ausgegeben = (float)($jeTopf[(int)$b['id']]['betrag'] ?? 0);
        $pct = $soll > 0 ? min(100, ($ist + $ausgegeben) / $soll * 100) : 0;
        $cls = ($soll > 0 && ($ist + $ausgegeben) > $soll) ? 'is-over' : ($pct >= $warn ? 'is-warn' : '');
    ?>
      <div style="margin-bottom:1rem">
        <div style="display:flex;justify-content:space-between;gap:.6rem;flex-wrap:wrap">
          <span>
            <strong><?= e($b['name']) ?></strong>
            <?php if (!(int)$b['is_active']): ?><span class="badge badge--muted">inaktiv</span><?php endif; ?>
            <span class="muted small"><?= e($b['kategorie_label'] ?: 'alle Kategorien') ?></span>
          </span>
          <span class="small nowrap">
            <?= e(money_rounded($ausgegeben, false)) ?> ausgegeben ·
            <?= e(money_rounded($ist, false)) ?> geplant / <?= e(money_rounded($soll)) ?>
            <?php if (can('manage_budget')): ?>
              · <a href="<?= e(url('budget_edit', ['id' => $b['id']])) ?>">bearbeiten</a>
            <?php endif; ?>
          </span>
        </div>
        <div class="bar"><div class="bar__fill <?= $cls ?>" style="width:<?= number_format($pct, 1, '.', '') ?>%"></div></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<section class="card">
  <div class="card__head">
    <h2>Offene Wünsche ohne Budgettopf</h2>
    <span class="badge badge--outline"><?= e(money_rounded($offenOhne)) ?></span>
  </div>
  <?php if (!$ohneTopf): ?>
    <div class="empty">Alle offenen Wünsche sind einem Topf zugeordnet.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Wunsch</th><th>Fachgruppe</th><th>Dringlichkeit</th><th class="num">Betrag</th></tr></thead>
        <tbody>
        <?php foreach ($ohneTopf as $w): ?>
          <tr>
            <td><a href="<?= e(url('wish', ['id' => $w['id']])) ?>"><?= e($w['bezeichnung']) ?></a></td>
            <td><?= e($w['fachgruppe_label'] ?: '–') ?></td>
            <td><?= badge($w['dring_label'] ? ['label' => $w['dring_label'], 'color' => $w['dring_color']] : null) ?></td>
            <td class="num"><?= e(money_rounded((float)$w['netto_gesamt'], false)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
