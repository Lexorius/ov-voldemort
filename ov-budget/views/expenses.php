<?php
/** @var string $art @var array $rows @var array $stats @var array $filters
 *  @var int $jahr @var array $jahre @var float $jahresbudget
 *  @var float $jahresSumme @var float $gegenSumme @var array $budgets */
$istEinnahme = $art === 'einnahme';
$titel = BUCHUNGSARTEN[$art];

// Beschriftungen unterscheiden sich je Richtung
$intro = $istEinnahme
    ? 'Was dem Ortsverband zufließt – Kostenerstattung für Einsätze, technische Hilfeleistung, Spenden und Ähnliches.'
    : 'Alles, was tatsächlich geflossen ist – Haus, Nebenkosten, Getränke, Tanken und so weiter.';

// Kennzahl rechts: bei Einnahmen die Deckung, bei Ausgaben der freie Rest
$verfuegbar = $jahresbudget + ($istEinnahme ? $jahresSumme : $gegenSumme);
$rest = $verfuegbar - ($istEinnahme ? $gegenSumme : $jahresSumme);
$linkArgs = ['jahr' => $jahr, 'art' => $art];
?>
<div class="pagehead">
  <div>
    <h1><?= e($titel) ?> <?= (int)$jahr ?></h1>
    <p><?= e($intro) ?></p>
  </div>
  <div class="btnrow">
    <?php if (can('manage_budget')): ?>
      <a class="btn" href="<?= e(url('expense_edit', ['jahr' => $jahr, 'art' => $art])) ?>">
        + <?= $istEinnahme ? 'Einnahme' : 'Ausgabe' ?></a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('expenses_export', array_diff_key($_GET, ['p' => 1]) + $linkArgs)) ?>">CSV</a>
  </div>
</div>

<?= render_partial('partials/budget_tabs', ['jahr' => $jahr]) ?>

<div class="tabs">
  <?php foreach ($jahre as $j): ?>
    <a class="tab<?= $j === $jahr ? ' is-active' : '' ?>"
       href="<?= e(url('expenses', ['jahr' => $j, 'art' => $art])) ?>"><?= (int)$j ?></a>
  <?php endforeach; ?>
</div>

<div class="stats">
  <div class="stat"><div class="stat__label">Angezeigt</div><div class="stat__value"><?= (int)$stats['anzahl'] ?></div>
    <div class="stat__hint"><?= e(money($stats['brutto'], false)) ?> brutto</div></div>
  <div class="stat"><div class="stat__label">Davon netto</div><div class="stat__value"><?= e(money($stats['netto'], false)) ?></div></div>
  <div class="stat"><div class="stat__label"><?= e($titel) ?> <?= (int)$jahr ?></div>
    <div class="stat__value" style="<?= $istEinnahme ? 'color:var(--ok)' : '' ?>"><?= e(money($jahresSumme, false)) ?></div>
    <div class="stat__hint">gesamtes Jahr</div></div>
  <div class="stat"><div class="stat__label"><?= $rest >= 0 ? 'Noch frei' : 'Überzogen' ?></div>
    <div class="stat__value" style="<?= $rest < 0 ? 'color:var(--bad)' : '' ?>"><?= e(money(abs($rest), false)) ?></div>
    <div class="stat__hint">Budget <?= e(money($jahresbudget, false)) ?> plus Einnahmen,
      abzüglich Ausgaben</div></div>
</div>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="expenses">
  <input type="hidden" name="jahr" value="<?= (int)$jahr ?>">
  <input type="hidden" name="art" value="<?= e($art) ?>">
  <div class="filters">
    <div class="field">
      <label for="q">Suche</label>
      <input type="search" id="q" name="q" value="<?= e((string)$filters['q']) ?>"
             placeholder="Bezeichnung, <?= $istEinnahme ? 'Auftraggeber, Einsatz-Nr.' : 'Lieferant, Beleg' ?>">
    </div>
    <div class="field">
      <label for="kategorie_id">Kategorie</label>
      <select id="kategorie_id" name="kategorie_id"><?= list_options(buchung_list_key($art), $filters['kategorie_id'], 'alle') ?></select>
    </div>
    <div class="field">
      <label for="fachgruppe_id">Fachgruppe</label>
      <select id="fachgruppe_id" name="fachgruppe_id"><?= list_options('fachgruppe', $filters['fachgruppe_id'], 'alle') ?></select>
    </div>
    <div class="field">
      <label for="von">Von</label>
      <input type="date" id="von" name="von" value="<?= e((string)$filters['von']) ?>">
    </div>
    <div class="field">
      <label for="bis">Bis</label>
      <input type="date" id="bis" name="bis" value="<?= e((string)$filters['bis']) ?>">
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <button class="btn btn--sec" type="submit">Filtern</button>
    </div>
  </div>
  <?php if ($filters['q'] || $filters['kategorie_id'] || $filters['fachgruppe_id'] || $filters['von'] || $filters['bis']): ?>
    <div class="chips mt"><a class="chip" href="<?= e(url('expenses', $linkArgs)) ?>">Filter zurücksetzen</a></div>
  <?php endif; ?>
</form>

<?php if (!$rows): ?>
  <div class="card"><div class="empty">Keine <?= e($titel) ?> gefunden.
    <?php if (can('manage_budget')): ?>
      <br><a href="<?= e(url('expense_edit', ['jahr' => $jahr, 'art' => $art])) ?>">
        Erste <?= $istEinnahme ? 'Einnahme' : 'Ausgabe' ?> erfassen</a>
    <?php endif; ?>
  </div></div>
<?php else: ?>
  <div class="card only-mobile">
    <div class="itemlist">
      <?php foreach ($rows as $r): ?>
        <a class="item" style="border-left-color:<?= e($r['kategorie_color'] ?: ($istEinnahme ? '#15803d' : '#94a3b8')) ?>"
           href="<?= can('manage_budget') ? e(url('expense_edit', ['id' => $r['id']])) : '#' ?>">
          <div class="item__top">
            <div style="min-width:0">
              <div class="item__title"><?= e($r['bezeichnung']) ?></div>
              <div class="item__sub"><?= e(de_date($r['datum'])) ?>
                <?php if ($r['lieferant']): ?> · <?= e($r['lieferant']) ?><?php endif; ?>
                <?php if ($r['referenz']): ?> · <?= e($r['referenz']) ?><?php endif; ?></div>
            </div>
            <div class="item__amount" style="<?= $istEinnahme ? 'color:var(--ok)' : '' ?>">
              <?= $istEinnahme ? '+' : '−' ?><?= e(money((float)$r['betrag_brutto'])) ?></div>
          </div>
          <div class="item__meta">
            <?= badge($r['kategorie_label'] ? ['label' => $r['kategorie_label'], 'color' => $r['kategorie_color']] : null, 'ohne Kategorie') ?>
            <?php if ($r['fachgruppe_label']): ?><span class="badge badge--outline"><?= e($r['fachgruppe_label']) ?></span><?php endif; ?>
            <?php if ($r['budget_name']): ?><span class="badge badge--outline"><?= e($r['budget_name']) ?></span><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card hide-mobile">
    <div class="tablewrap">
      <table class="data">
        <thead>
          <tr><th>Datum</th><th>Bezeichnung</th><th>Kategorie</th><th>Fachgruppe</th>
              <th><?= $istEinnahme ? 'Einsatz-Nr.' : 'Topf' ?></th><th>Beleg</th>
              <th class="num">Netto</th><th class="num">Brutto</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="nowrap small"><?= e(de_date($r['datum'])) ?></td>
            <td>
              <strong><?= e($r['bezeichnung']) ?></strong>
              <?php if ($r['lieferant']): ?><div class="small muted"><?= e($r['lieferant']) ?></div><?php endif; ?>
              <?php if ($r['wunsch_bezeichnung']): ?>
                <div class="small muted">zu Wunsch: <?= e($r['wunsch_bezeichnung']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= badge($r['kategorie_label'] ? ['label' => $r['kategorie_label'], 'color' => $r['kategorie_color']] : null, '–') ?></td>
            <td class="small"><?= e($r['fachgruppe_label'] ?: '–') ?></td>
            <td class="small"><?= e(($istEinnahme ? $r['referenz'] : $r['budget_name']) ?: '–') ?></td>
            <td class="small mono"><?= e($r['beleg_nr'] ?: '–') ?></td>
            <td class="num"><?= e(money((float)$r['betrag_netto'], false)) ?></td>
            <td class="num" style="<?= $istEinnahme ? 'color:var(--ok)' : '' ?>">
              <strong><?= e(money((float)$r['betrag_brutto'], false)) ?></strong></td>
            <td><?php if (can('manage_budget')): ?>
              <a class="btn btn--sec btn--sm" href="<?= e(url('expense_edit', ['id' => $r['id']])) ?>">Bearbeiten</a>
            <?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="6"><strong>Summe (<?= (int)$stats['anzahl'] ?>)</strong></td>
            <td class="num"><strong><?= e(money($stats['netto'], false)) ?></strong></td>
            <td class="num"><strong><?= e(money($stats['brutto'], false)) ?></strong></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
<?php endif; ?>
