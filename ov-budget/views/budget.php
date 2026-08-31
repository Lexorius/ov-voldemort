<?php
/** @var int $jahr @var array $jahre @var array $budgets @var array $ohneTopf
 *  @var ?array $jahresbudget @var float $ausgabenBrutto @var float $ausgabenNetto
 *  @var array $kategorien @var array $monate @var array $jeTopf @var array $letzte */
$warn = setting_int('budget_warn_prozent', 90);
$gesamt = (float)($jahresbudget['betrag'] ?? 0);
$rest = $gesamt - $ausgabenBrutto;
$quote = $gesamt > 0 ? min(100, $ausgabenBrutto / $gesamt * 100) : 0;
$quoteCls = ($gesamt > 0 && $ausgabenBrutto > $gesamt) ? 'is-over' : ($quote >= $warn ? 'is-warn' : '');

$summeToepfe = array_sum(array_map(static fn($b) => (float)$b['betrag_netto'], $budgets));
$verplant = array_sum(array_map(static fn($b) => (float)$b['verplant'], $budgets));
$offenOhne = array_sum(array_map(static fn($w) => (float)$w['netto_gesamt'], $ohneTopf));
$maxMonat = max(array_merge([0.0], array_values($monate)));
$monatsnamen = ['', 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)setting('budget_modul_name', 'Budget')) ?> <?= (int)$jahr ?></h1>
    <p><?= nl2br(e((string)setting('budget_intro', ''))) ?></p>
  </div>
  <div class="btnrow">
    <?php if (can('manage_budget')): ?>
      <a class="btn" href="<?= e(url('expense_edit', ['jahr' => $jahr])) ?>">+ Ausgabe</a>
      <a class="btn btn--sec" href="<?= e(url('budget_year_edit', ['jahr' => $jahr])) ?>">Jahresbudget</a>
      <a class="btn btn--sec" href="<?= e(url('budget_edit', ['jahr' => $jahr])) ?>">+ Topf</a>
    <?php endif; ?>
  </div>
</div>

<?= render_partial('partials/budget_tabs', ['jahr' => $jahr]) ?>

<div class="tabs">
  <?php foreach ($jahre as $j): ?>
    <a class="tab<?= $j === $jahr ? ' is-active' : '' ?>" href="<?= e(url('budget', ['jahr' => $j])) ?>"><?= (int)$j ?></a>
  <?php endforeach; ?>
</div>

<?php if ($gesamt <= 0): ?>
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
    <div class="stat__hint">für <?= (int)$jahr ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Ausgegeben</div>
    <div class="stat__value"><?= e(money($ausgabenBrutto, false)) ?></div>
    <div class="stat__hint"><?= e(money($ausgabenNetto, false)) ?> netto</div>
  </div>
  <div class="stat">
    <div class="stat__label"><?= $rest >= 0 ? 'Noch frei' : 'Überzogen um' ?></div>
    <div class="stat__value" style="<?= $rest < 0 ? 'color:var(--bad)' : '' ?>"><?= e(money(abs($rest), false)) ?></div>
    <div class="stat__hint"><?= $gesamt > 0 ? number_format($quote, 0) . '&nbsp;% verbraucht' : 'ohne Budgetvorgabe' ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Offene Wünsche</div>
    <div class="stat__value"><?= e(money($verplant + $offenOhne, false)) ?></div>
    <div class="stat__hint">netto, noch nicht ausgegeben</div>
  </div>
</div>

<?php if ($gesamt > 0): ?>
  <section class="card">
    <div class="card__head">
      <h2>Verbrauch <?= (int)$jahr ?></h2>
      <span class="small"><?= e(money($ausgabenBrutto, false)) ?> von <?= e(money($gesamt)) ?></span>
    </div>
    <div class="bar" style="height:14px"><div class="bar__fill <?= $quoteCls ?>" style="width:<?= number_format($quote, 1, '.', '') ?>%"></div></div>
    <p class="small muted" style="margin:.5rem 0 0">
      Wenn zusätzlich alle offenen Wünsche beschafft würden, kämen
      <strong><?= e(money($verplant + $offenOhne)) ?></strong> netto hinzu.
    </p>
  </section>
<?php endif; ?>

<div class="grid2">
  <section class="card">
    <div class="card__head">
      <h2>Ausgaben nach Kategorie</h2>
      <?php if (can('view_expenses')): ?>
        <a class="btn btn--sec btn--sm" href="<?= e(url('expenses', ['jahr' => $jahr])) ?>">Alle Ausgaben</a>
      <?php endif; ?>
    </div>
    <?php if (!$kategorien): ?>
      <div class="empty">Für <?= (int)$jahr ?> sind noch keine Ausgaben erfasst.</div>
    <?php else: ?>
      <?php $maxKat = max(array_map(static fn($k) => (float)$k['brutto'], $kategorien)); ?>
      <?php foreach ($kategorien as $k):
          $b = (float)$k['brutto'];
          $breite = $maxKat > 0 ? $b / $maxKat * 100 : 0;
          $anteil = $ausgabenBrutto > 0 ? $b / $ausgabenBrutto * 100 : 0;
      ?>
        <div style="margin-bottom:.8rem">
          <div style="display:flex;justify-content:space-between;gap:.6rem;flex-wrap:wrap">
            <span>
              <?php if ($k['id']): ?>
                <a href="<?= e(url('expenses', ['jahr' => $jahr, 'kategorie_id' => $k['id']])) ?>"><?= e($k['label']) ?></a>
              <?php else: ?>
                <span class="muted">ohne Kategorie</span>
              <?php endif; ?>
              <span class="muted small">(<?= (int)$k['anzahl'] ?>)</span>
            </span>
            <span class="small nowrap"><strong><?= e(money($b, false)) ?></strong>
              <span class="muted"><?= number_format($anteil, 0) ?>&nbsp;%</span></span>
          </div>
          <div class="bar">
            <div class="bar__fill" style="width:<?= number_format($breite, 1, '.', '') ?>%;background:<?= e($k['color'] ?: '#94a3b8') ?>"></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Verlauf über das Jahr</h2>
    <?php if ($maxMonat <= 0): ?>
      <div class="empty">Noch keine Ausgaben erfasst.</div>
    <?php else: ?>
      <div class="months">
        <?php foreach ($monate as $m => $betrag):
            $h = $maxMonat > 0 ? max(2, $betrag / $maxMonat * 100) : 2; ?>
          <div class="months__col" title="<?= e($monatsnamen[$m] . ': ' . money($betrag)) ?>">
            <div class="months__bar" style="height:<?= number_format($h, 1, '.', '') ?>%"></div>
            <div class="months__label"><?= e($monatsnamen[$m]) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="small muted" style="margin:.6rem 0 0">Höchster Monat: <?= e(money($maxMonat)) ?></p>
    <?php endif; ?>

    <?php if ($letzte): ?>
      <h3 class="mt">Zuletzt erfasst</h3>
      <div class="tablewrap">
        <table class="data">
          <tbody>
          <?php foreach ($letzte as $e): ?>
            <tr>
              <td class="nowrap small"><?= e(de_date($e['datum'])) ?></td>
              <td>
                <?php if (can('manage_budget')): ?>
                  <a href="<?= e(url('expense_edit', ['id' => $e['id']])) ?>"><?= e($e['bezeichnung']) ?></a>
                <?php else: ?><?= e($e['bezeichnung']) ?><?php endif; ?>
              </td>
              <td class="num nowrap"><?= e(money((float)$e['betrag_brutto'], false)) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<section class="card">
  <div class="card__head">
    <h2>Budgettöpfe</h2>
    <span class="muted small"><?= e(money($summeToepfe)) ?> geplant</span>
  </div>
  <?php if (!$budgets): ?>
    <div class="empty">Für <?= (int)$jahr ?> ist noch kein Budgettopf angelegt.
      Töpfe sind optional – sie unterteilen das Jahresbudget nach Zweck.</div>
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
