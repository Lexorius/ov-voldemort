<?php
/** @var int $jahr @var array $jahre @var array $budgets @var array $ohneTopf
 *  @var ?array $jahresbudget @var float $ausgabenBrutto @var float $ausgabenNetto
 *  @var float $einnahmenBrutto @var float $einnahmenNetto
 *  @var array $kategorien @var array $einnahmeKategorien
 *  @var array $monate @var array $monateEin @var array $jeTopf @var array $letzte */
$warn = setting_int('budget_warn_prozent', 90);
$gesamt = (float)($jahresbudget['betrag'] ?? 0);

// Verfügbar ist die Zuweisung plus alles, was der OV selbst eingenommen hat
$verfuegbar = $gesamt + $einnahmenBrutto;
$rest = $verfuegbar - $ausgabenBrutto;
$quote = $verfuegbar > 0 ? min(100, $ausgabenBrutto / $verfuegbar * 100) : 0;
$quoteCls = ($verfuegbar > 0 && $ausgabenBrutto > $verfuegbar) ? 'is-over' : ($quote >= $warn ? 'is-warn' : '');

$summeToepfe = array_sum(array_map(static fn($b) => (float)$b['betrag_netto'], $budgets));
$verplant = array_sum(array_map(static fn($b) => (float)$b['verplant'], $budgets));
$offenOhne = array_sum(array_map(static fn($w) => (float)$w['netto_gesamt'], $ohneTopf));

$maxMonat = max(array_merge([0.0], array_values($monate), array_values($monateEin)));
$monatsnamen = ['', 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

/** Kategorieblock für eine Richtung */
$kategorieBlock = static function (array $liste, float $summe, string $art) use ($jahr): string {
    if (!$liste) {
        return '<div class="empty">Nichts erfasst.</div>';
    }
    $max = max(array_map(static fn($k) => (float)$k['brutto'], $liste));
    $html = '';
    foreach ($liste as $k) {
        $b = (float)$k['brutto'];
        $breite = $max > 0 ? $b / $max * 100 : 0;
        $anteil = $summe > 0 ? $b / $summe * 100 : 0;
        $name = $k['id']
            ? '<a href="' . e(url('expenses', ['jahr' => $jahr, 'art' => $art, 'kategorie_id' => $k['id']])) . '">'
                . e($k['label']) . '</a>'
            : '<span class="muted">ohne Kategorie</span>';
        $html .= '<div style="margin-bottom:.8rem">'
            . '<div style="display:flex;justify-content:space-between;gap:.6rem;flex-wrap:wrap">'
            . '<span>' . $name . ' <span class="muted small">(' . (int)$k['anzahl'] . ')</span></span>'
            . '<span class="small nowrap"><strong>' . e(money($b, false)) . '</strong> '
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
  </div>
  <div class="btnrow">
    <?php if (can('manage_budget')): ?>
      <a class="btn" href="<?= e(url('expense_edit', ['jahr' => $jahr, 'art' => 'ausgabe'])) ?>">+ Ausgabe</a>
      <a class="btn btn--ok" href="<?= e(url('expense_edit', ['jahr' => $jahr, 'art' => 'einnahme'])) ?>">+ Einnahme</a>
      <a class="btn btn--sec" href="<?= e(url('budget_year_edit', ['jahr' => $jahr])) ?>">Jahresbudget</a>
    <?php endif; ?>
  </div>
</div>

<?= render_partial('partials/budget_tabs', ['jahr' => $jahr]) ?>

<div class="tabs">
  <?php foreach ($jahre as $j): ?>
    <a class="tab<?= $j === $jahr ? ' is-active' : '' ?>" href="<?= e(url('budget', ['jahr' => $j])) ?>"><?= (int)$j ?></a>
  <?php endforeach; ?>
</div>

<?php if ($gesamt <= 0 && $einnahmenBrutto <= 0): ?>
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
    <div class="stat__value"><?= e(money($gesamt, false)) ?></div>
    <div class="stat__hint">Zuweisung für <?= (int)$jahr ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Einnahmen</div>
    <div class="stat__value" style="color:var(--ok)">+<?= e(money($einnahmenBrutto, false)) ?></div>
    <div class="stat__hint">Einsätze, THG und Übriges</div>
  </div>
  <div class="stat">
    <div class="stat__label">Ausgaben</div>
    <div class="stat__value">−<?= e(money($ausgabenBrutto, false)) ?></div>
    <div class="stat__hint"><?= e(money($ausgabenNetto, false)) ?> netto</div>
  </div>
  <div class="stat">
    <div class="stat__label"><?= $rest >= 0 ? 'Noch frei' : 'Überzogen um' ?></div>
    <div class="stat__value" style="<?= $rest < 0 ? 'color:var(--bad)' : '' ?>"><?= e(money(abs($rest), false)) ?></div>
    <div class="stat__hint">von <?= e(money($verfuegbar, false)) ?> verfügbar</div>
  </div>
</div>

<section class="card">
  <div class="card__head">
    <h2>Mittel <?= (int)$jahr ?></h2>
    <span class="small">
      <?= e(money($gesamt, false)) ?> Budget
      <?php if ($einnahmenBrutto > 0): ?>+ <?= e(money($einnahmenBrutto, false)) ?> Einnahmen<?php endif; ?>
      = <strong><?= e(money($verfuegbar)) ?></strong>
    </span>
  </div>
  <?php if ($verfuegbar > 0): ?>
    <div class="bar" style="height:14px">
      <div class="bar__fill <?= $quoteCls ?>" style="width:<?= number_format($quote, 1, '.', '') ?>%"></div>
    </div>
    <p class="small muted" style="margin:.5rem 0 0">
      <?= e(money($ausgabenBrutto, false)) ?> ausgegeben (<?= number_format($quote, 0) ?>&nbsp;%).
      Wenn zusätzlich alle offenen Wünsche beschafft würden, kämen
      <strong><?= e(money($verplant + $offenOhne)) ?></strong> netto hinzu.
    </p>
  <?php else: ?>
    <div class="empty">Weder Budget noch Einnahmen erfasst.</div>
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
    <?= $kategorieBlock($kategorien, $ausgabenBrutto, 'ausgabe') ?>
  </section>

  <section class="card">
    <div class="card__head">
      <h2>Einnahmen nach Kategorie</h2>
      <?php if (can('view_expenses')): ?>
        <a class="btn btn--sec btn--sm" href="<?= e(url('expenses', ['jahr' => $jahr, 'art' => 'einnahme'])) ?>">Alle</a>
      <?php endif; ?>
    </div>
    <?= $kategorieBlock($einnahmeKategorien, $einnahmenBrutto, 'einnahme') ?>
  </section>
</div>

<section class="card">
  <div class="card__head">
    <h2>Verlauf über das Jahr</h2>
    <span class="small muted">
      <span class="legend legend--ein"></span> Einnahmen
      <span class="legend legend--aus"></span> Ausgaben
    </span>
  </div>
  <?php if ($maxMonat <= 0): ?>
    <div class="empty">Noch nichts erfasst.</div>
  <?php else: ?>
    <div class="months">
      <?php foreach ($monate as $m => $aus):
          $ein = (float)($monateEin[$m] ?? 0);
          $hAus = $maxMonat > 0 ? max(1, $aus / $maxMonat * 100) : 1;
          $hEin = $maxMonat > 0 ? max(1, $ein / $maxMonat * 100) : 1;
      ?>
        <div class="months__col">
          <div class="months__pair">
            <div class="months__bar months__bar--ein" style="height:<?= number_format($hEin, 1, '.', '') ?>%"
                 title="<?= e($monatsnamen[$m] . ' Einnahmen: ' . money($ein)) ?>"></div>
            <div class="months__bar months__bar--aus" style="height:<?= number_format($hAus, 1, '.', '') ?>%"
                 title="<?= e($monatsnamen[$m] . ' Ausgaben: ' . money($aus)) ?>"></div>
          </div>
          <div class="months__label"><?= e($monatsnamen[$m]) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="small muted" style="margin:.6rem 0 0">Höchster Monatswert: <?= e(money($maxMonat)) ?></p>
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
              <?= $ein ? '+' : '−' ?><?= e(money((float)$b['betrag_brutto'], false)) ?></td>
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
    <span class="muted small"><?= e(money($summeToepfe)) ?> geplant</span>
  </div>
  <?php if (!$budgets): ?>
    <div class="empty">Für <?= (int)$jahr ?> ist noch kein Budgettopf angelegt.
      Töpfe sind optional – sie unterteilen das Jahresbudget nach Zweck.
      <?php if (can('manage_budget')): ?>
        <br><a href="<?= e(url('budget_edit', ['jahr' => $jahr])) ?>">Topf anlegen</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php foreach ($budgets as $b):
        $soll = (float)$b['betrag_netto'];
        $ist = (float)$b['verplant'];
        $ausgegeben = (float)($jeTopf[(int)$b['id']]['netto'] ?? 0);
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
            <?= e(money($ausgegeben, false)) ?> ausgegeben ·
            <?= e(money($ist, false)) ?> geplant / <?= e(money($soll)) ?>
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
    <span class="badge badge--outline"><?= e(money($offenOhne)) ?></span>
  </div>
  <?php if (!$ohneTopf): ?>
    <div class="empty">Alle offenen Wünsche sind einem Topf zugeordnet.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Wunsch</th><th>Fachgruppe</th><th>Dringlichkeit</th><th class="num">Netto</th></tr></thead>
        <tbody>
        <?php foreach ($ohneTopf as $w): ?>
          <tr>
            <td><a href="<?= e(url('wish', ['id' => $w['id']])) ?>"><?= e($w['bezeichnung']) ?></a></td>
            <td><?= e($w['fachgruppe_label'] ?: '–') ?></td>
            <td><?= badge($w['dring_label'] ? ['label' => $w['dring_label'], 'color' => $w['dring_color']] : null) ?></td>
            <td class="num"><?= e(money((float)$w['netto_gesamt'], false)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
