<?php
/** @var array $b @var array $wuensche @var array $buchungen @var float $gebucht @var bool $darf @var bool $buchen */
$summe = (float)$b['summe'];
$offen = $summe - $gebucht;
?>
<div class="pagehead">
  <div>
    <h1>Bestellung <span class="mono"><?= e((string)$b['nummer']) ?></span> <?= bestellung_badge((string)$b['status']) ?></h1>
    <p class="muted small"><?= e((string)$b['lieferant']) ?> · bestellt am <?= e(de_date((string)$b['bestellt_am'])) ?><?= $b['bestell_nr'] !== '' ? ' · Auftrag ' . e((string)$b['bestell_nr']) : '' ?><?= $b['erfasser'] ? ' · angelegt von ' . e((string)$b['erfasser']) : '' ?></p>
  </div>
  <div class="btnrow">
    <?php if ($darf): ?><a class="btn btn--sec" href="<?= e(url('bestellung_edit', ['id' => $b['id']])) ?>">Bearbeiten</a><?php endif; ?>
    <?php if ($buchen && in_array((string)$b['status'], ['bestellt', 'geliefert'], true)): ?>
      <a class="btn" href="<?= e(url('expense_edit', ['art' => 'ausgabe', 'bestellung_id' => $b['id']])) ?>">Rechnung erfassen</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('bestellungen')) ?>">Alle Bestellungen</a>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="stat__label">Wünsche</div><div class="stat__value"><?= count($wuensche) ?></div><div class="stat__hint">in dieser Bestellung</div></div>
  <div class="stat"><div class="stat__label">Bestellwert</div><div class="stat__value"><?= e(money($summe, false)) ?></div><div class="stat__hint">Summe der Wünsche</div></div>
  <div class="stat"><div class="stat__label">Gebucht</div><div class="stat__value"><?= e(money($gebucht, false)) ?></div>
    <div class="stat__hint"><?= $gebucht > 0 ? ($offen > 0.005 ? 'noch ' . e(money($offen)) . ' ohne Rechnung' : ($offen < -0.005 ? e(money(-$offen)) . ' über dem Bestellwert' : 'vollständig abgerechnet')) : 'noch keine Rechnung' ?></div></div>
</div>

<div class="grid2">
  <section class="card">
    <h2>Wünsche</h2>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Wunsch</th><th>Fachgruppe</th><th>Status</th><th class="num">Betrag</th></tr></thead>
        <tbody>
        <?php foreach ($wuensche as $w): ?>
          <tr>
            <td><a href="<?= e(url('wish', ['id' => $w['id']])) ?>"><?= e((string)$w['bezeichnung']) ?></a>
              <?php if ($w['fahrzeug']): ?><div class="small muted">Fahrzeug: <?= e((string)$w['fahrzeug']) ?></div><?php endif; ?></td>
            <td class="small"><?= e((string)($w['fachgruppe_label'] ?: '–')) ?></td>
            <td><?= badge($w['status_label'] ? ['label' => $w['status_label'], 'color' => $w['status_color']] : null, '–') ?></td>
            <td class="num"><?= e(money((float)$w['netto_gesamt'], false)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="3"><strong>Summe</strong></td><td class="num"><strong><?= e(money($summe, false)) ?></strong></td></tr></tfoot>
      </table>
    </div>
  </section>

  <section class="card">
    <h2>Rechnungen und Buchungen</h2>
    <?php if (!$buchungen): ?>
      <div class="empty">Noch keine Buchung zu dieser Bestellung.<?= $buchen ? ' „Rechnung erfassen" legt sie mit allen Wünschen an.' : '' ?></div>
    <?php else: ?>
      <div class="tablewrap">
        <table class="data">
          <tbody>
          <?php foreach ($buchungen as $r): ?>
            <tr>
              <td class="nowrap small"><?= e(de_date((string)$r['datum'])) ?></td>
              <td><?php if ($buchen): ?><a href="<?= e(url('expense_edit', ['id' => $r['id']])) ?>"><?= e((string)$r['bezeichnung']) ?></a><?php else: ?><?= e((string)$r['bezeichnung']) ?><?php endif; ?>
                <?= buchung_status_badge((string)($r['status'] ?? 'bezahlt')) ?>
                <?php if ($r['beleg_nr']): ?><div class="small muted mono"><?= e((string)$r['beleg_nr']) ?></div><?php endif; ?></td>
              <td class="num nowrap"><?= e(money((float)$r['betrag_brutto'], false)) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    <?php if (trim((string)$b['notiz']) !== ''): ?><p class="small muted mt"><?= nl2br(e((string)$b['notiz'])) ?></p><?php endif; ?>
  </section>
</div>

<?php if ($darf): ?>
  <div class="grid2">
    <form method="post" class="card form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="status">
      <h2>Stand</h2>
      <div class="field">
        <?php foreach (BESTELLUNG_STATUS as $k => $l): ?>
          <label class="wahl"><input type="radio" name="status" value="<?= e($k) ?>"<?= (string)$b['status'] === $k ? ' checked' : '' ?>> <?= e($l) ?></label>
        <?php endforeach; ?>
      </div>
      <div><button class="btn" type="submit">Stand setzen</button></div>
    </form>
    <form method="post" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <h2>Löschen</h2>
      <p class="small muted">Die Wünsche bleiben erhalten; eine noch offene Bestellung gibt sie als freigegeben zurück. Buchungen verlieren nur den Bezug.</p>
      <button class="btn btn--danger" type="submit" data-confirm="Bestellung wirklich löschen?">Bestellung löschen</button>
    </form>
  </div>
<?php endif; ?>
