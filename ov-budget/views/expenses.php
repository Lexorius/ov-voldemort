<?php
/** @var string $art @var array $rows @var array $stats @var array $filters
 *  @var int $jahr @var array $jahre @var float $jahresbudget
 *  @var float $jahresSumme @var float $gegenSumme @var array $budgets @var ?array $einnahmenStand @var ?array $wartend */
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
    <div class="stat__hint">Buchungen</div></div>
  <div class="stat"><div class="stat__label">Summe angezeigt</div><div class="stat__value"><?= e(money($stats['summe'], false)) ?></div></div>
  <div class="stat"><div class="stat__label"><?= e($titel) ?> <?= (int)$jahr ?></div>
    <div class="stat__value" style="<?= $istEinnahme ? 'color:var(--ok)' : '' ?>"><?= e(money($jahresSumme, false)) ?></div>
    <div class="stat__hint">gesamtes Jahr</div></div>
  <div class="stat"><div class="stat__label"><?= $rest >= 0 ? 'Noch frei' : 'Überzogen' ?></div>
    <div class="stat__value" style="<?= $rest < 0 ? 'color:var(--bad)' : '' ?>"><?= e(money(abs($rest), false)) ?></div>
    <div class="stat__hint">Budget <?= e(money($jahresbudget, false)) ?> plus Einnahmen,
      abzüglich Ausgaben</div></div>
</div>

<?php if ($istEinnahme && $einnahmenStand !== null): $es = $einnahmenStand; ?>
<section class="card" id="einnahmen-stand">
  <div class="card__head">
    <h2>Stand der Einnahmen <?= (int)$jahr ?></h2>
    <span class="small muted">Forderungen <strong><?= e(money($es['forderungen'])) ?></strong> · zugesagt
      <strong><?= e(money($es['zugesagt'])) ?></strong> · eingegangen <strong><?= e(money($es['eingegangen'])) ?></strong></span>
  </div>
  <div class="stats">
    <?php foreach ($es['stufen'] as $key => $st): ?>
      <a class="stat" href="<?= e(url('expenses', $linkArgs + ['status' => $key])) ?>" style="text-decoration:none<?= ($filters['status'] ?? '') === $key ? ';outline:2px solid var(--accent)' : '' ?>">
        <div class="stat__label"><?= e(explode(' –', $st['label'])[0]) ?></div>
        <div class="stat__value" style="<?= $key === 'bezahlt' ? 'color:var(--ok)' : ($key === 'zugesagt' ? 'color:#15803d' : (in_array($key, EINNAHME_FORDERUNG, true) ? 'color:#b45309' : '')) ?>"><?= e(money($st['summe'], false)) ?></div>
        <div class="stat__hint"><?= (int)$st['anzahl'] ?> Buchung<?= (int)$st['anzahl'] === 1 ? '' : 'en' ?></div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if (!empty($wartend['ueber30'])): $w = $wartend; $schlimmste = $w['rot'] > 0 ? 'rot' : ($w['orange'] > 0 ? 'orange' : 'gelb'); ?>
    <div class="alert" style="margin:.8rem 0 0;border-left:4px solid <?= EINNAHME_WARN_FARBEN[$schlimmste] ?>">
      <strong><?= (int)$w['ueber30'] ?> Abrechnung<?= (int)$w['ueber30'] === 1 ? ' wartet' : 'en warten' ?> länger als 30 Tage auf Geld</strong>
      (<?= e(money($w['summe_ueber30'])) ?>): <?= e(einnahmen_wartend_text($w)) ?>.
      <?php foreach (array_slice(array_filter($w['liste'], static fn($x) => $x['alter']['stufe'] !== 'gruen'), 0, 5) as $x): ?>
        <div class="small"><span style="color:<?= EINNAHME_WARN_FARBEN[$x['alter']['stufe']] ?>">●</span>
          <?php if (can('manage_budget')): ?><a href="<?= e(url('expense_edit', ['id' => $x['id']])) ?>"><?= e((string)$x['bezeichnung']) ?></a><?php else: ?><?= e((string)$x['bezeichnung']) ?><?php endif; ?>
          · <?= e(money((float)$x['betrag_brutto'])) ?> · <?= e(explode(' –', EINNAHME_STUFEN[(string)$x['status']])[0]) ?> · seit <?= (int)$x['alter']['tage'] ?> Tagen</div>
      <?php endforeach; ?>
      <div class="small muted" style="margin-top:.3rem">Gezählt ab dem Tag der Abrechnung, sonst des Bescheids. Gelb ab 30, orange ab 60, rot ab 90 Tagen.</div>
    </div>
  <?php endif; ?>
  <p class="small muted" style="margin:.6rem 0 0">
    Haben sollten wir <strong><?= e(money($es['forderungen'] + $es['zugesagt'] + $es['eingegangen'])) ?></strong>
    (abgerechnet, gestellt, zugesagt und eingegangen)<?= $es['erwartet'] > 0 ? ', dazu ' . e(money($es['erwartet'])) . ' erwartet ohne Abrechnung' : '' ?>.
    <?php if ($es['kuerzung_anzahl'] > 0): ?>
      Bei <?= (int)$es['kuerzung_anzahl'] ?> Abrechnung<?= $es['kuerzung_anzahl'] === 1 ? '' : 'en' ?> mit Bescheid:
      abgerechnet <?= e(money($es['abgerechnet_summe'])) ?>, gestellt <?= e(money($es['gestellt_summe'])) ?> –
      <?= $es['kuerzung'] > 0 ? '<span style="color:var(--bad)">' . e(money($es['kuerzung'])) . ' gekürzt</span>' : ($es['kuerzung'] < 0 ? e(money(-$es['kuerzung'])) . ' mehr als abgerechnet' : 'ohne Abweichung') ?>.
    <?php endif; ?>
  </p>
</section>
<?php endif; ?>

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
      <label for="status">Stand</label>
      <select id="status" name="status">
        <option value="">alle</option>
        <?php foreach ($istEinnahme ? EINNAHME_STUFEN : BUCHUNG_STATUS as $key => $label): ?>
          <option value="<?= e($key) ?>"<?= (string)($filters['status'] ?? '') === $key ? ' selected' : '' ?>><?= e(explode(' –', $label)[0]) ?></option>
        <?php endforeach; ?>
      </select>
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
  <?php if ($filters['q'] || $filters['kategorie_id'] || $filters['fachgruppe_id'] || $filters['von'] || $filters['bis'] || !empty($filters['status'])): ?>
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
              <div class="item__title"><?= e($r['bezeichnung']) ?><?= $istEinnahme ? ' ' . einnahme_alter_badge(einnahme_alter($r)) : '' ?></div>
              <div class="item__sub"><?= e(de_date($r['datum'])) ?>
                <?php if ($r['lieferant']): ?> · <?= e($r['lieferant']) ?><?php endif; ?>
                <?php if ($r['referenz']): ?> · <?= e($r['referenz']) ?><?php endif; ?></div>
            </div>
            <div class="item__amount" style="<?= $istEinnahme ? 'color:var(--ok)' : '' ?>">
              <?= $istEinnahme ? '+' : '−' ?><?= e(money((float)$r['betrag_brutto'])) ?></div>
          </div>
          <div class="item__meta">
            <?= buchung_status_badge((string)($r['status'] ?? 'bezahlt')) ?>
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
              <th class="num">Betrag</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="nowrap small"><?= e(de_date($r['datum'])) ?></td>
            <td>
              <strong><?= e($r['bezeichnung']) ?></strong> <?= buchung_status_badge((string)($r['status'] ?? 'bezahlt')) ?><?= $istEinnahme ? ' ' . einnahme_alter_badge(einnahme_alter($r)) : '' ?>
              <?php if ($r['lieferant']): ?><div class="small muted"><?= e($r['lieferant']) ?></div><?php endif; ?>
              <?php if ($istEinnahme && ($weg = einnahme_weg($r)) !== ''): ?><div class="small muted"><?= e($weg) ?></div><?php endif; ?>
              <?php if (!empty($r['wuensche_namen'])): ?>
                <div class="small muted">zu Wunsch: <?= e((string)$r['wuensche_namen']) ?></div>
              <?php elseif ($r['wunsch_bezeichnung']): ?>
                <div class="small muted">zu Wunsch: <?= e($r['wunsch_bezeichnung']) ?></div>
              <?php endif; ?>
              <?php if (!empty($r['fahrzeuge_namen'])): ?><div class="small muted">Fahrzeug: <?= e((string)$r['fahrzeuge_namen']) ?></div><?php endif; ?>
              <?php if (!empty($r['bestellung_nummer'])): ?><div class="small muted">Bestellung <span class="mono"><?= e((string)$r['bestellung_nummer']) ?></span></div><?php endif; ?>
              <?php if (!empty($r['veranstaltung_titel'])): ?>
                <div class="small muted">zu Veranstaltung: <?= e((string)$r['veranstaltung_titel']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= badge($r['kategorie_label'] ? ['label' => $r['kategorie_label'], 'color' => $r['kategorie_color']] : null, '–') ?></td>
            <td class="small"><?= e($r['fachgruppe_label'] ?: '–') ?></td>
            <td class="small"><?= e(($istEinnahme ? $r['referenz'] : $r['budget_name']) ?: '–') ?></td>
            <td class="small mono"><?= e($r['beleg_nr'] ?: '–') ?></td>
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
            <td class="num"><strong><?= e(money($stats['summe'], false)) ?></strong></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
<?php endif; ?>
