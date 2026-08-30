<?php
/** @var array $user @var int $jahr @var array $wuensche @var array $statsW
 *  @var array $budgets @var float $budgetSumme @var float $budgetVerplant
 *  @var array $todos @var int $todosGesamt @var int $ueberfaellig */
$warnProzent = setting_int('budget_warn_prozent', 90);
$auslastung = $budgetSumme > 0 ? ($budgetVerplant / $budgetSumme) * 100 : 0;
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

<div class="stats">
  <div class="stat">
    <div class="stat__label">Offene Wünsche</div>
    <div class="stat__value"><?= (int)$statsW['anzahl'] ?></div>
    <div class="stat__hint"><?= e(money($statsW['netto_offen'])) ?> netto</div>
  </div>
  <div class="stat">
    <div class="stat__label">Davon "nice to have"</div>
    <div class="stat__value"><?= e(money($statsW['nice'], false)) ?></div>
    <div class="stat__hint">netto, verzichtbar</div>
  </div>
  <div class="stat">
    <div class="stat__label">Budget <?= (int)$jahr ?></div>
    <div class="stat__value"><?= e(money($budgetSumme, false)) ?></div>
    <div class="stat__hint"><?= e(money($budgetVerplant, false)) ?> verplant (<?= number_format($auslastung, 0) ?>&nbsp;%)</div>
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

<section class="card">
  <div class="card__head">
    <h2>Budgettöpfe <?= (int)$jahr ?></h2>
    <a class="btn btn--sec btn--sm" href="<?= e(url('budget')) ?>">Budget</a>
  </div>
  <?php if (!$budgets): ?>
    <div class="empty">Für <?= (int)$jahr ?> ist noch kein Budget hinterlegt.
      <?php if (can('manage_budget')): ?>
        <br><a href="<?= e(url('budget_edit')) ?>">Topf anlegen</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
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
