<?php
/** @var int $jahr @var array $jahre @var array $budgets @var array $ohneTopf */
$warn = setting_int('budget_warn_prozent', 90);
$summe = array_sum(array_map(static fn($b) => (float)$b['betrag_netto'], $budgets));
$verplant = array_sum(array_map(static fn($b) => (float)$b['verplant'], $budgets));
$abgeschlossen = array_sum(array_map(static fn($b) => (float)$b['abgeschlossen'], $budgets));
$offenOhne = array_sum(array_map(static fn($w) => (float)$w['netto_gesamt'], $ohneTopf));
?>
<div class="pagehead">
  <div>
    <h1>Budget <?= (int)$jahr ?></h1>
    <p>Pseudo-Budget zur internen Planung – die Zahlen bilden keine offizielle Haushaltsstelle ab.</p>
  </div>
  <?php if (can('manage_budget')): ?>
    <a class="btn" href="<?= e(url('budget_edit', ['jahr' => $jahr])) ?>">+ Topf anlegen</a>
  <?php endif; ?>
</div>

<div class="tabs">
  <?php foreach ($jahre as $j): ?>
    <a class="tab<?= $j === $jahr ? ' is-active' : '' ?>" href="<?= e(url('budget', ['jahr' => $j])) ?>"><?= (int)$j ?></a>
  <?php endforeach; ?>
</div>

<div class="stats">
  <div class="stat"><div class="stat__label">Budget gesamt</div><div class="stat__value"><?= e(money($summe, false)) ?></div><div class="stat__hint">netto</div></div>
  <div class="stat"><div class="stat__label">Verplant (offen)</div><div class="stat__value"><?= e(money($verplant, false)) ?></div>
    <div class="stat__hint"><?= $summe > 0 ? number_format($verplant / $summe * 100, 0) : 0 ?>&nbsp;% des Budgets</div></div>
  <div class="stat"><div class="stat__label">Abgeschlossen</div><div class="stat__value"><?= e(money($abgeschlossen, false)) ?></div><div class="stat__hint">beschafft oder abgelehnt</div></div>
  <div class="stat"><div class="stat__label">Ohne Topf</div><div class="stat__value"><?= e(money($offenOhne, false)) ?></div><div class="stat__hint"><?= count($ohneTopf) ?> offene Wünsche</div></div>
</div>

<?php if (!$budgets): ?>
  <div class="card"><div class="empty">Für <?= (int)$jahr ?> ist noch kein Budgettopf angelegt.</div></div>
<?php else: ?>
  <div class="itemlist">
    <?php foreach ($budgets as $b):
        $soll = (float)$b['betrag_netto'];
        $ist = (float)$b['verplant'];
        $pct = $soll > 0 ? min(100, $ist / $soll * 100) : 0;
        $cls = ($soll > 0 && $ist > $soll) ? 'is-over' : ($pct >= $warn ? 'is-warn' : '');
        $rest = $soll - $ist;
    ?>
      <div class="card" style="margin-bottom:0">
        <div class="card__head">
          <div>
            <h2 style="margin:0"><?= e($b['name']) ?><?php if (!(int)$b['is_active']): ?>
              <span class="badge badge--muted">inaktiv</span><?php endif; ?></h2>
            <div class="muted small">
              <?= e($b['kategorie_label'] ?: 'alle Kategorien') ?> ·
              <?= e($b['fachgruppe_label'] ?: 'ortsverbandsweit') ?> ·
              <?= (int)$b['wuensche'] ?> Wünsche
            </div>
          </div>
          <div class="btnrow">
            <a class="btn btn--sec btn--sm" href="<?= e(url('wishes', ['budget_id' => $b['id'], 'alle' => '1'])) ?>">Wünsche</a>
            <?php if (can('manage_budget')): ?>
              <a class="btn btn--sec btn--sm" href="<?= e(url('budget_edit', ['id' => $b['id']])) ?>">Bearbeiten</a>
            <?php endif; ?>
          </div>
        </div>

        <div style="display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap" class="small">
          <span><strong><?= e(money($ist, false)) ?></strong> verplant von <?= e(money($soll)) ?></span>
          <span style="<?= $rest < 0 ? 'color:var(--bad);font-weight:700' : '' ?>">
            <?= $rest >= 0 ? 'noch frei: ' : 'Überzeichnung: ' ?><?= e(money(abs($rest))) ?>
          </span>
        </div>
        <div class="bar"><div class="bar__fill <?= $cls ?>" style="width:<?= number_format($pct, 1, '.', '') ?>%"></div></div>
        <?php if ($b['beschreibung']): ?>
          <p class="small muted" style="margin:.6rem 0 0"><?= nl2br(e($b['beschreibung'])) ?></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<section class="card mt">
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
