<?php
/** @var array $user @var int $jahr @var array $wuensche @var array $statsW
 *  @var array $budgets @var array $zahlen @var float $budgetVerplant
 *  @var array $todos @var int $todosGesamt @var int $ueberfaellig @var array $zuBestellen @var array $fahrzeuge */
$warnProzent = setting_int('budget_warn_prozent', 90);
$quote = (float)$zahlen['quote'];
$quoteCls = ($zahlen['verfuegbar'] > 0 && $zahlen['ausgaben'] > $zahlen['verfuegbar']) ? 'is-over'
    : ($quote >= $warnProzent ? 'is-warn' : '');
?>
<div class="pagehead">
  <div>
    <h1>Moin <?= e(explode(' ', trim((string)($user['display_name'] ?: $user['username'])))[0]) ?>!</h1>
    <p>Haushaltsjahr <?= (int)$jahr ?> · <?= e((string)setting('ov_name', '')) ?></p>
  </div>
  <div class="btnrow">
    <a class="btn" href="<?= e(url('wish_edit')) ?>">+ Wunsch</a>
    <?php if (can('create_todo')): ?>
      <a class="btn btn--sec" href="<?= e(url('todo_edit')) ?>">+ Aufgabe</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($zuBestellen): ?>
  <div class="alert alert--info">
    <strong><?= count($zuBestellen) ?> <?= count($zuBestellen) === 1 ? 'Wunsch ist' : 'Wünsche sind' ?> freigegeben – bitte bestellen</strong>
    (<?= e(money(array_sum(array_map(static fn($w) => (float)$w['netto_gesamt'], $zuBestellen)))) ?>).
    <a href="<?= e(url('budget')) ?>#bestellung">Zur Liste</a>
  </div>
<?php endif; ?>

<div class="stats">
  <div class="stat">
    <div class="stat__label">Offene Wünsche</div>
    <div class="stat__value"><?= (int)$statsW['anzahl'] ?></div>
    <div class="stat__hint"><?= e(money($statsW['netto_offen'])) ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Davon "nice to have"</div>
    <div class="stat__value"><?= e(money($statsW['nice'], false)) ?></div>
    <div class="stat__hint">verzichtbar</div>
  </div>
  <div class="stat">
    <div class="stat__label"><?= $zahlen['frei'] >= 0 ? 'Budget frei' : 'Budget überzogen' ?> <?= (int)$jahr ?></div>
    <div class="stat__value" style="<?= $zahlen['frei'] < 0 ? 'color:var(--bad)' : '' ?>"><?= e(money(abs($zahlen['frei']), false)) ?></div>
    <div class="stat__hint">von <?= e(money($zahlen['verfuegbar'], false)) ?> verfügbar (<?= number_format($quote, 0) ?>&nbsp;% ausgegeben)</div>
  </div>
  <div class="stat">
    <div class="stat__label">Meine Aufgaben</div>
    <div class="stat__value"><?= (int)$todosGesamt ?></div>
    <div class="stat__hint">
      <?php if ($ueberfaellig > 0): ?>
        <span style="color:var(--bad);font-weight:700"><?= (int)$ueberfaellig ?> überfällig</span>
      <?php else: ?>alles im Zeitplan<?php endif; ?>
    </div>
  </div>
</div>

<div class="grid2">
  <section class="card">
    <div class="card__head">
      <h2>Oben auf der Wunschliste</h2>
      <a class="btn btn--sec btn--sm" href="<?= e(url('wishes')) ?>">Alle</a>
    </div>
    <?php if (!$wuensche): ?>
      <div class="empty">Noch keine offenen Wünsche.<br>
        <a href="<?= e(url('wish_edit')) ?>">Jetzt den ersten eintragen</a>
      </div>
    <?php else: ?>
      <div class="itemlist">
        <?php foreach ($wuensche as $w): ?>
          <?= render_partial('partials/wish_item', ['w' => $w]) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card__head">
      <h2>Meine Aufgaben</h2>
      <a class="btn btn--sec btn--sm" href="<?= e(url('todos')) ?>">Alle</a>
    </div>
    <?php if (!$todos): ?>
      <div class="empty">Keine offenen Aufgaben. 🎉</div>
    <?php else: ?>
      <div class="itemlist">
        <?php foreach ($todos as $t): ?>
          <?= render_partial('partials/todo_item', ['t' => $t]) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php if ($fahrzeuge): ?>
  <section class="card">
    <div class="card__head">
      <h2>Fahrzeuge im Blick</h2>
      <a class="btn btn--sec btn--sm" href="<?= e(url('vehicles')) ?>">Alle</a>
    </div>
    <div class="itemlist">
      <?php foreach ($fahrzeuge as $w): $v = $w['fahrzeug']; ?>
        <a class="item" href="<?= e(url('vehicle', ['id' => $v['id']])) ?>"
           style="border-left-color:<?= $w['ausfall'] > 0 ? '#b91c1c' : '#a16207' ?>">
          <div class="item__top"><div style="min-width:0">
            <div class="item__title"><?= e($v['bezeichnung']) ?></div>
            <div class="item__sub"><?= e(implode(' · ', array_filter([$v['funkrufname'], $v['kennzeichen']]))) ?></div>
          </div></div>
          <div class="item__meta">
            <?php if ($w['ausfall'] > 0): ?>
              <span class="badge" style="background:#b91c1c">steht still</span>
            <?php endif; ?>
            <?php foreach ($w['fristen'] as $f): ?>
              <span class="badge" style="background:<?= $f['status'] === 'abgelaufen' ? '#b91c1c' : '#a16207' ?>">
                <?= e($f['label']) ?> <?= $f['status'] === 'abgelaufen' ? 'abgelaufen' : 'bis ' . e(de_date($f['datum'])) ?>
              </span>
            <?php endforeach; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<section class="card">
  <div class="card__head">
    <h2>Budget <?= (int)$jahr ?></h2>
    <a class="btn btn--sec btn--sm" href="<?= e(url('budget')) ?>">Budget</a>
  </div>
  <?php if ($zahlen['verfuegbar'] <= 0): ?>
    <div class="empty">Für <?= (int)$jahr ?> ist noch kein Jahresbudget hinterlegt.
      <?php if (can('manage_budget')): ?>
        <br><a href="<?= e(url('budget_year_edit', ['jahr' => $jahr])) ?>">Jahresbudget eintragen</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div style="display:flex;justify-content:space-between;gap:.6rem;flex-wrap:wrap">
      <span class="small">
        <?= e(money($zahlen['budget'], false)) ?> Budget
        <?php if ($zahlen['einnahmen'] > 0): ?>+ <?= e(money($zahlen['einnahmen'], false)) ?> Einnahmen<?php endif; ?>
        = <strong><?= e(money($zahlen['verfuegbar'])) ?></strong>
      </span>
      <span class="small nowrap"><?= e(money($zahlen['ausgaben'], false)) ?> ausgegeben ·
        <strong><?= e(money($zahlen['frei'])) ?></strong> <?= $zahlen['frei'] >= 0 ? 'frei' : 'überzogen' ?></span>
    </div>
    <div class="bar" style="height:14px"><div class="bar__fill <?= $quoteCls ?>" style="width:<?= number_format($quote, 1, '.', '') ?>%"></div></div>
    <?php if ($budgetVerplant > 0): ?>
      <p class="small muted" style="margin:.5rem 0 0">Dazu <?= e(money($budgetVerplant)) ?> in offenen Wünschen verplant.</p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (count($budgets) > 1): ?>
    <h3 class="mt">Budgettöpfe</h3>
    <?php foreach ($budgets as $b):
        $soll = (float)$b['betrag_netto'];
        $ist = (float)$b['verplant'];
        $pct = $soll > 0 ? min(100, ($ist / $soll) * 100) : 0;
        $cls = $soll > 0 && $ist > $soll ? 'is-over' : ($pct >= $warnProzent ? 'is-warn' : '');
    ?>
      <div style="margin-bottom:.9rem">
        <div style="display:flex;justify-content:space-between;gap:.6rem;flex-wrap:wrap">
          <strong><?= e($b['name']) ?></strong>
          <span class="small"><?= e(money($ist, false)) ?> / <?= e(money($soll)) ?></span>
        </div>
        <div class="bar"><div class="bar__fill <?= $cls ?>" style="width:<?= number_format($pct, 1, '.', '') ?>%"></div></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
