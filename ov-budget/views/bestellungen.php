<?php
/** @var array $liste @var array $filters @var float $summe @var array $zuBestellen */
?>
<div class="pagehead">
  <div>
    <h1>Bestellungen</h1>
    <p>Eine Bestellung fasst mehrere freigegebene Wünsche zusammen – ein Lieferant, ein Auftrag, eine Rechnung.
      Ist sie geliefert, gelten die Wünsche als beschafft.</p>
  </div>
  <div class="btnrow">
    <?php if (can('order_wish') && $zuBestellen): ?>
      <a class="btn" href="<?= e(url('bestellung_edit')) ?>">+ Bestellung</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('budget')) ?>#bestellung">Zum Budget</a>
  </div>
</div>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="bestellungen">
  <div class="filters">
    <div class="field">
      <label for="q">Suche</label>
      <input type="search" id="q" name="q" value="<?= e((string)$filters['q']) ?>" placeholder="Nummer, Lieferant, Auftragsnummer">
    </div>
    <div class="field">
      <label for="status">Stand</label>
      <select id="status" name="status">
        <option value="">offen (bestellt, geliefert)</option>
        <?php foreach (BESTELLUNG_STATUS as $k => $l): ?>
          <option value="<?= e($k) ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= e(explode(' –', $l)[0]) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <a class="btn btn--sec" href="<?= e(url('bestellungen', ['alle' => 1])) ?>">Alle</a>
    </div>
  </div>
</form>

<?php if (!$liste): ?>
  <div class="card"><div class="empty">Keine Bestellungen<?= $filters['offen'] ? ' offen' : '' ?>.
    <?php if ($zuBestellen && can('order_wish')): ?><br><?= count($zuBestellen) ?> freigegebene Wünsche warten – <a href="<?= e(url('bestellung_edit')) ?>">Bestellung anlegen</a>.<?php endif; ?></div></div>
<?php else: ?>
  <div class="card">
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Nummer</th><th>Lieferant</th><th>Bestellt am</th><th>Stand</th><th class="num">Wünsche</th><th class="num">Summe</th><th class="num">gebucht</th></tr></thead>
        <tbody>
        <?php foreach ($liste as $b): ?>
          <tr>
            <td><a href="<?= e(url('bestellung', ['id' => $b['id']])) ?>"><strong class="mono"><?= e((string)$b['nummer']) ?></strong></a>
              <?php if ($b['bestell_nr'] !== ''): ?><div class="small muted">Auftrag <?= e((string)$b['bestell_nr']) ?></div><?php endif; ?></td>
            <td><?= e((string)$b['lieferant']) ?></td>
            <td class="nowrap small"><?= e(de_date((string)$b['bestellt_am'])) ?></td>
            <td><?= bestellung_badge((string)$b['status']) ?></td>
            <td class="num"><?= (int)$b['wuensche'] ?></td>
            <td class="num"><?= e(money((float)$b['summe'], false)) ?></td>
            <td class="num"><?= (float)$b['gebucht'] > 0 ? e(money((float)$b['gebucht'], false)) : '<span class="muted">–</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="5"><strong><?= count($liste) ?> Bestellung<?= count($liste) === 1 ? '' : 'en' ?></strong></td><td class="num"><strong><?= e(money($summe, false)) ?></strong></td><td></td></tr></tfoot>
      </table>
    </div>
  </div>
<?php endif; ?>
