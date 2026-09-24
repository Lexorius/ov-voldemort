<?php
/** @var int $jahr @var array $jahre @var array $toepfe @var float $summe
 *  @var float $gesamt @var int $vorjahr */
$rest = $gesamt - $summe;
?>
<div class="pagehead">
  <div>
    <h1>Budgettöpfe <?= (int)$jahr ?></h1>
    <p>Töpfe unterteilen das Jahresbudget nach Zweck – etwa Ausstattung, Liegenschaft oder
       Jugendarbeit. Sie sind freiwillig: Ohne Töpfe läuft alles gegen das Jahresbudget.</p>
  </div>
  <div class="btnrow">
    <a class="btn" href="<?= e(url('budget_edit', ['jahr' => $jahr])) ?>">+ Topf</a>
    <a class="btn btn--sec" href="<?= e(url('budget', ['jahr' => $jahr])) ?>">Zur Übersicht</a>
  </div>
</div>

<?= render_partial('partials/budget_tabs', ['jahr' => $jahr]) ?>

<div class="tabs">
  <?php foreach ($jahre as $j): ?>
    <a class="tab<?= $j === $jahr ? ' is-active' : '' ?>"
       href="<?= e(url('budget_pots', ['jahr' => $j])) ?>"><?= (int)$j ?></a>
  <?php endforeach; ?>
</div>

<div class="stats">
  <div class="stat">
    <div class="stat__label">Jahresbudget</div>
    <div class="stat__value"><?= e(money_rounded($gesamt, false)) ?></div>
    <div class="stat__hint">Zuweisung für <?= (int)$jahr ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">In Töpfen</div>
    <div class="stat__value"><?= e(money_rounded($summe, false)) ?></div>
    <div class="stat__hint"><?= count($toepfe) ?> Topf/Töpfe</div>
  </div>
  <div class="stat">
    <div class="stat__label">Nicht verteilt</div>
    <div class="stat__value" style="color:<?= $rest < 0 ? 'var(--bad)' : 'inherit' ?>">
      <?= e(money_rounded($rest, false)) ?></div>
    <div class="stat__hint"><?= $rest < 0
        ? 'Die Töpfe übersteigen das Jahresbudget'
        : 'bleibt ohne Topf' ?></div>
  </div>
</div>

<section class="card">
  <div class="card__head">
    <h2>Töpfe für <?= (int)$jahr ?></h2>
    <?php if ($vorjahr > 0): ?>
      <form method="post" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="kopieren">
        <input type="hidden" name="von" value="<?= (int)$jahr - 1 ?>">
        <button class="btn btn--sec btn--sm" type="submit"
                data-confirm="<?= (int)$vorjahr ?> Topf/Töpfe aus <?= (int)$jahr - 1 ?> nach <?= (int)$jahr ?> übernehmen? Gleichnamige Töpfe bleiben unberührt, die Beträge musst du danach prüfen.">Aus <?= (int)$jahr - 1 ?> übernehmen</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!$toepfe): ?>
    <div class="empty">Für <?= (int)$jahr ?> ist noch kein Topf angelegt.
      <br><a href="<?= e(url('budget_edit', ['jahr' => $jahr])) ?>">Ersten Topf anlegen</a>
      <?php if ($vorjahr > 0): ?> oder die <?= (int)$vorjahr ?> Töpfe aus
        <?= (int)$jahr - 1 ?> übernehmen.<?php endif; ?>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr>
          <th>Topf</th><th>Gilt für</th>
          <th style="text-align:right">Betrag netto</th>
          <th style="text-align:right">verplant</th>
          <th style="text-align:right">ausgegeben</th>
          <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($toepfe as $b): ?>
          <?php
            $soll = (float)$b['betrag_netto'];
            $belegt = (float)$b['verplant'] + (float)$b['ausgegeben'];
          ?>
          <tr<?= (int)$b['is_active'] !== 1 ? ' class="is-muted"' : '' ?>>
            <td>
              <a href="<?= e(url('budget_edit', ['id' => $b['id']])) ?>"><strong><?= e((string)$b['name']) ?></strong></a>
              <?php if ((int)$b['is_active'] !== 1): ?>
                <span class="badge badge--muted">stillgelegt</span>
              <?php endif; ?>
              <?php if (trim((string)$b['beschreibung']) !== ''): ?>
                <div class="small muted"><?= e(mb_strimwidth((string)$b['beschreibung'], 0, 90, '…')) ?></div>
              <?php endif; ?>
            </td>
            <td class="small">
              <?= e((string)($b['kategorie_label'] ?: 'alle Kategorien')) ?>
              <div class="muted"><?= e((string)($b['fachgruppe_label'] ?: 'ortsverbandsweit')) ?></div>
            </td>
            <td style="text-align:right"><?= e(money($soll)) ?></td>
            <td style="text-align:right" class="small">
              <?= e(money((float)$b['verplant'])) ?>
              <div class="muted"><?= (int)$b['wuensche'] ?> Wunsch/Wünsche</div>
            </td>
            <td style="text-align:right" class="small">
              <span<?= $soll > 0 && $belegt > $soll ? ' style="color:var(--bad);font-weight:700"' : '' ?>>
                <?= e(money((float)$b['ausgegeben'])) ?></span>
              <div class="muted"><?= (int)$b['buchungen'] ?> Buchung(en)</div>
            </td>
            <td style="text-align:right" class="nowrap">
              <a class="btn btn--sec btn--sm" href="<?= e(url('budget_edit', ['id' => $b['id']])) ?>">Bearbeiten</a>
              <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="aktiv">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <button class="btn btn--sec btn--sm" type="submit"
                        <?= (int)$b['is_active'] === 1
                            ? 'data-confirm="Topf stilllegen? Er lässt sich dann nicht mehr auswählen; vorhandene Zuordnungen bleiben."'
                            : '' ?>><?= (int)$b['is_active'] === 1 ? 'Stilllegen' : 'Aktivieren' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
          <th colspan="2"><?= count($toepfe) ?> Topf/Töpfe</th>
          <th style="text-align:right"><?= e(money($summe)) ?></th>
          <th colspan="3"></th>
        </tr></tfoot>
      </table>
    </div>
    <p class="small muted">Löschen geht im Topf selbst. Wer einen Topf nur aus der Auswahl nehmen
      will, legt ihn still – dann bleiben alle bisherigen Zuordnungen erhalten.</p>
  <?php endif; ?>
</section>
