<?php
/** @var array $einsatz @var array $tage @var array $summen @var array $fahrzeuge
 *  @var array $buchungen @var array $geld @var bool $mitGeld */

$id = (int)$einsatz['id'];
$darf = can('manage_einsaetze');
$buchen = $mitGeld && can('manage_budget');
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)$einsatz['titel']) ?></h1>
    <p><?php if (trim((string)$einsatz['nummer']) !== ''): ?><span class="mono"><?= e((string)$einsatz['nummer']) ?></span> · <?php endif; ?>
      <?= e(einsatz_zeitraum_text($einsatz)) ?>
      <?= trim((string)$einsatz['ort']) !== '' ? ' · ' . e((string)$einsatz['ort']) : '' ?></p>
    <p><?= einsatz_status_badge((string)$einsatz['status']) ?>
      <?php if (!empty($einsatz['art_label'])): ?><?= badge(['label' => $einsatz['art_label'], 'color' => $einsatz['art_color']]) ?><?php endif; ?></p>
  </div>
  <div class="btnrow">
    <?php if ($darf): ?>
      <a class="btn" href="<?= e(url('einsatz_edit', ['id' => $id])) ?>">Bearbeiten</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('einsaetze')) ?>">Alle Einsätze</a>
  </div>
</div>

<?php if (trim((string)($einsatz['beschreibung'] ?? '')) !== ''): ?>
  <div class="card"><?= nl2br(e((string)$einsatz['beschreibung'])) ?></div>
<?php endif; ?>

<div class="stats">
  <div class="stat">
    <div class="stat__label">Einsatztage</div>
    <div class="stat__value"><?= (int)$summen['tage'] ?></div>
    <div class="stat__hint"><?= (int)$summen['zeilen'] > (int)$summen['tage'] ? (int)$summen['zeilen'] . ' Schichten' : 'vom ' . e(de_date(substr((string)$einsatz['beginn'], 0, 10))) ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Einsatzstunden</div>
    <div class="stat__value"><?= e(einsatz_stunden_text((int)$summen['minuten'])) ?></div>
    <div class="stat__hint"><?= (int)$summen['ohne_dauer'] > 0
        ? ((int)$summen['ohne_dauer'] === 1 ? 'eine Zeile' : (int)$summen['ohne_dauer'] . ' Zeilen') . ' noch ohne Ende'
        : 'über alle Tage' ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Helferstunden</div>
    <div class="stat__value"><?= e(einsatz_stunden_text((int)$summen['helferminuten'])) ?></div>
    <div class="stat__hint">Dauer mal Stärke<?= (int)$summen['staerke_max'] > 0 ? ' · Stärke bis ' . e((string)$summen['staerke_max_text']) : '' ?></div>
  </div>
  <?php if ($mitGeld): ?>
    <div class="stat">
      <div class="stat__label">Ausgaben</div>
      <div class="stat__value"><?= e(money((float)$geld['ausgaben'])) ?></div>
      <div class="stat__hint"><?= (float)$geld['ausgaben_geplant'] > 0 ? 'dazu geplant ' . e(money((float)$geld['ausgaben_geplant'])) : 'bezahlt und gebucht' ?></div>
    </div>
    <div class="stat">
      <div class="stat__label">Einnahmen</div>
      <div class="stat__value"><?= e(money((float)$geld['einnahmen'])) ?></div>
      <div class="stat__hint"><?= (float)$geld['einnahmen_offen'] > 0 ? 'noch unterwegs ' . e(money((float)$geld['einnahmen_offen'])) : 'eingegangen' ?></div>
    </div>
  <?php endif; ?>
</div>

<div class="card" id="tage">
  <div class="card__head">
    <h2>Tage und Schichten</h2>
    <?php if ($darf): ?><a class="btn btn--sec btn--sm" href="<?= e(url('einsatz_edit', ['id' => $id])) ?>#tage">Tage bearbeiten</a><?php endif; ?>
  </div>
  <?php if (!$tage): ?>
    <div class="empty">Noch kein Tag eingetragen.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Tag</th><th>Zeit</th><th class="num">Dauer</th><th>Stärke</th><th class="num">Helferstunden</th><th>Tätigkeit</th></tr></thead>
        <tbody>
        <?php foreach ($tage as $t): $min = einsatz_tag_minuten($t); $st = einsatz_tag_staerke($t);
              $von = einsatz_zeit($t['von'] ?? ''); $bis = einsatz_zeit($t['bis'] ?? ''); ?>
          <tr>
            <td class="nowrap"><?= e(einsatz_tag_text((string)$t['datum'])) ?></td>
            <td class="nowrap small"><?php if (is_string($von)): ?><?= e($von) ?> – <?= is_string($bis) ? e($bis) . ($bis <= $von ? ' <span class="muted" title="endet am Folgetag">+1</span>' : '') : '<span class="muted">offen</span>' ?><?php else: ?><span class="muted">ganztägig</span><?php endif; ?></td>
            <td class="num"><?= $min !== null ? e(einsatz_stunden_text($min)) : '–' ?></td>
            <td class="nowrap"><?= $st['gesamt'] > 0 ? '<span class="mono">' . e($st['text']) . '</span>' : '–' ?></td>
            <td class="num"><?= $min !== null && $st['gesamt'] > 0 ? e(einsatz_stunden_text($min * $st['gesamt'])) : '–' ?></td>
            <td class="small"><?= nl2br(e((string)$t['taetigkeit'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
          <th colspan="2"><?= (int)$summen['tage'] === 1 ? '1 Einsatztag' : (int)$summen['tage'] . ' Einsatztage' ?></th>
          <th class="num"><?= e(einsatz_stunden_text((int)$summen['minuten'])) ?></th>
          <th></th>
          <th class="num"><?= e(einsatz_stunden_text((int)$summen['helferminuten'])) ?></th>
          <th></th>
        </tr></tfoot>
      </table>
    </div>
    <p class="small muted" style="margin-bottom:0">Stärke wie gemeldet: Führer / Unterführer / Helfer / gesamt. Helferstunden sind Dauer mal Gesamtstärke der Zeile.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Eckdaten</h2>
  <dl class="dl">
    <div class="dl__item"><div class="dl__label">Einsatznummer</div>
      <div class="dl__value"><?= trim((string)$einsatz['nummer']) !== '' ? '<span class="mono">' . e((string)$einsatz['nummer']) . '</span>' : '–' ?></div></div>
    <div class="dl__item"><div class="dl__label">Anforderer / Kostenträger</div>
      <div class="dl__value"><?= e(trim((string)$einsatz['anforderer']) !== '' ? (string)$einsatz['anforderer'] : '–') ?></div></div>
    <div class="dl__item"><div class="dl__label">Einsatzort</div>
      <div class="dl__value"><?= e(trim((string)$einsatz['ort']) !== '' ? (string)$einsatz['ort'] : '–') ?></div></div>
    <div class="dl__item"><div class="dl__label">Fachgruppe</div>
      <div class="dl__value"><?= e((string)($einsatz['fachgruppe_label'] ?? '') !== '' ? (string)$einsatz['fachgruppe_label'] : 'ortsverbandsweit') ?></div></div>
    <?php if (can('view_vehicles')): ?>
    <div class="dl__item"><div class="dl__label">Fahrzeuge</div>
      <div class="dl__value"><?php if (!$fahrzeuge): ?>–<?php else: foreach ($fahrzeuge as $i => $v): ?><?= $i > 0 ? ' · ' : '' ?><a href="<?= e(url('vehicle', ['id' => $v['id']])) ?>"><?= e((string)$v['bezeichnung']) ?></a><?php endforeach; endif; ?></div></div>
    <?php endif; ?>
    <div class="dl__item"><div class="dl__label">Erfasst</div>
      <div class="dl__value"><?= e(de_datetime((string)$einsatz['created_at'])) ?><?= !empty($einsatz['ersteller']) ? ' · ' . e((string)$einsatz['ersteller']) : '' ?></div></div>
  </dl>
  <?php if (trim((string)($einsatz['notiz'] ?? '')) !== ''): ?>
    <p class="small muted"><?= nl2br(e((string)$einsatz['notiz'])) ?></p>
  <?php endif; ?>
</div>

<?php if ($mitGeld): ?>
<div class="card" id="geld">
  <div class="card__head">
    <h2>Ausgaben und Einnahmen</h2>
    <?php if ($buchen): ?>
      <div class="btnrow">
        <a class="btn btn--sec btn--sm" href="<?= e(url('expense_edit', ['art' => 'ausgabe', 'einsatz_id' => $id])) ?>">+ Ausgabe</a>
        <a class="btn btn--sec btn--sm" href="<?= e(url('expense_edit', ['art' => 'einnahme', 'einsatz_id' => $id])) ?>">+ Einnahme</a>
      </div>
    <?php endif; ?>
  </div>
  <?php if (!$buchungen): ?>
    <div class="empty">Für diesen Einsatz ist noch nichts gebucht.
      <?= $buchen ? 'Mit „+ Ausgabe" und „+ Einnahme" entsteht die Buchung im Budget und trägt den Bezug schon mit.'
                  : 'Buchungen bekommen den Bezug im Budget beim Erfassen oder Bearbeiten.' ?></div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Datum</th><th>Bezeichnung</th><th>Kategorie</th><th class="num">Betrag</th></tr></thead>
        <tbody>
        <?php foreach ($buchungen as $b): $einnahme = (string)$b['art'] === 'einnahme'; ?>
          <tr<?= buchung_zeile_klasse((string)($b['status'] ?? 'bezahlt')) ?>>
            <td class="nowrap small"><?= e(de_date((string)$b['datum'])) ?></td>
            <td><?php if ($buchen): ?><a href="<?= e(url('expense_edit', ['id' => $b['id']])) ?>"><?= e((string)$b['bezeichnung']) ?></a><?php else: ?><?= e((string)$b['bezeichnung']) ?><?php endif; ?>
              <?= buchung_status_badge((string)($b['status'] ?? 'bezahlt'), $einnahme ? 'einnahme' : 'ausgabe') ?>
              <?php if (trim((string)$b['lieferant']) !== ''): ?><div class="small muted"><?= e((string)$b['lieferant']) ?></div><?php endif; ?>
              <?php if ($einnahme && ($weg = einnahme_weg($b)) !== ''): ?><div class="small muted"><?= e($weg) ?></div><?php endif; ?></td>
            <td><?= badge($b['kategorie_label'] ? ['label' => $b['kategorie_label'], 'color' => $b['kategorie_color']] : null, '–') ?></td>
            <td class="num" style="<?= $einnahme ? 'color:var(--ok)' : '' ?>"><strong><?= $einnahme ? '+' : '−' ?><?= e(money((float)$b['betrag_brutto'])) ?></strong></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="3">Ausgaben <?= e(money((float)$geld['ausgaben'])) ?>, Einnahmen <?= e(money((float)$geld['einnahmen'])) ?></th>
            <th class="num"><?= e(money((float)$geld['saldo'])) ?></th>
          </tr>
          <?php if ((float)$geld['ausgaben_geplant'] > 0 || (float)$geld['einnahmen_offen'] > 0): ?>
          <tr>
            <td colspan="3" class="small muted">Mit dem, was noch aussteht – geplante Ausgaben <?= e(money((float)$geld['ausgaben_geplant'])) ?>,
              Einnahmen unterwegs <?= e(money((float)$geld['einnahmen_offen'])) ?></td>
            <td class="num small muted"><?= e(money((float)$geld['saldo_erwartet'])) ?></td>
          </tr>
          <?php endif; ?>
        </tfoot>
      </table>
    </div>
    <p class="small muted" style="margin-bottom:0">In der Summe zählt wie im Budget, was bezahlt oder gebucht ist –
      auch über den Jahreswechsel, wenn die Erstattung erst im Folgejahr kommt.</p>
  <?php endif; ?>
</div>
<?php endif; ?>
