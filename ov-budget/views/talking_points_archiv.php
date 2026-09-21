<?php
/** Themenarchiv: besprochene, beschlossene, abgelehnte, vertagte Themen
 *  @var array $rows @var bool $mehr @var array $filters */
$label = tp_label();
?>
<div class="pagehead">
  <div>
    <h1>Themenspeicher</h1>
    <p>Alle <?= e($label) ?>, die auf einer Besprechung standen oder abgeschlossen sind – mit Ergebnis und
       den daraus entstandenen Aufgaben.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('meetings')) ?>">Besprechungen</a>
  </div>
</div>

<?= render_partial('partials/tp_tabs', ['tab' => 'archiv']) ?>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="talking_points">
  <input type="hidden" name="tab" value="archiv">
  <div class="filters">
    <div class="field">
      <label for="q">Suche <span class="muted small">(auch im Ergebnis)</span></label>
      <input type="search" id="q" name="q" value="<?= e((string)$filters['q']) ?>">
    </div>
    <div class="field">
      <label for="status_id">Status</label>
      <select id="status_id" name="status_id"><?= list_options('tp_status', $filters['status_id'], 'alle', false) ?></select>
    </div>
    <div class="field">
      <label for="fachgruppe_id">Fachgruppe</label>
      <select id="fachgruppe_id" name="fachgruppe_id"><?= list_options('fachgruppe', $filters['fachgruppe_id'], 'alle') ?></select>
    </div>
    <div class="field">
      <label for="von">von</label>
      <input type="date" id="von" name="von" value="<?= e((string)$filters['von']) ?>">
    </div>
    <div class="field">
      <label for="bis">bis</label>
      <input type="date" id="bis" name="bis" value="<?= e((string)$filters['bis']) ?>">
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <button class="btn btn--sec" type="submit">Filtern</button>
    </div>
  </div>
</form>

<?php if (!$rows): ?>
  <div class="card"><div class="empty">Keine Themen gefunden.</div></div>
<?php else: ?>
  <div class="itemlist">
    <?php foreach ($rows as $t): ?>
      <div class="item" style="border-left-color:<?= e($t['status_color'] ?: '#94a3b8') ?>">
        <div class="item__top">
          <div style="min-width:0">
            <div class="item__title">
              <a href="<?= e(url('talking_point', ['id' => $t['id']])) ?>"><?= e($t['titel']) ?></a>
              <?php if ((int)$t['anmerkungen'] > 0): ?>
                <span class="small muted">· <?= (int)$t['anmerkungen'] ?> Anmerkung(en)</span>
              <?php endif; ?>
            </div>
            <div class="item__sub">
              <?php if ($t['meeting_id']): ?>
                <a href="<?= e(url('meeting', ['id' => $t['meeting_id']])) ?>#tp<?= (int)$t['id'] ?>"><?= e((string)$t['meeting_titel']) ?></a>
                am <?= e(de_date($t['meeting_datum'])) ?>
                <?php if ($t['meeting_status'] === 'geplant'): ?><span class="muted">(geplant)</span><?php endif; ?>
              <?php else: ?>
                ohne Besprechung · zuletzt geändert <?= e(de_date($t['updated_at'])) ?>
              <?php endif; ?>
              · eingebracht <?= e(de_date($t['created_at'])) ?><?= $t['einbringer'] ? ' von ' . e($t['einbringer']) : '' ?><?= !empty($t['aus_divera']) ? ' · über Divera' : '' ?>
            </div>
          </div>
          <?php if (tp_editable($t)): ?>
            <a class="btn btn--sec btn--sm" href="<?= e(url('talking_point_edit', ['id' => $t['id']])) ?>">Bearbeiten</a>
          <?php endif; ?>
        </div>

        <div class="item__meta">
          <?= $t['status_label'] ? badge(['label' => $t['status_label'], 'color' => $t['status_color']]) : '' ?>
          <?php if ($t['fachgruppe_label']): ?><span class="badge badge--outline"><?= e($t['fachgruppe_label']) ?></span><?php endif; ?>
          <?php if (!empty($t['vorgaenger_meeting_id'])): ?>
            <span class="small muted">vertagt aus
              <a href="<?= e(url('meeting', ['id' => $t['vorgaenger_meeting_id']])) ?>"><?= e((string)$t['vorgaenger_meeting_titel']) ?></a>
              vom <?= e(de_date($t['vorgaenger_meeting_datum'])) ?></span>
          <?php endif; ?>
          <?php if ($t['status_slug'] === 'vertagt'): ?>
            <?php if (!empty($t['nachfolger_meeting_id'])): ?>
              <span class="small muted">→ <a href="<?= e(url('meeting', ['id' => $t['nachfolger_meeting_id']])) ?>">weiter in der nächsten Besprechung</a></span>
            <?php else: ?>
              <span class="small muted">→ wieder im Themenspeicher</span>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <?php if ($t['ergebnis']): ?>
          <div class="comment__body small" style="margin-top:.4rem"><strong>Ergebnis:</strong> <?= nl2br(e((string)$t['ergebnis'])) ?>
            <?php if ($t['verantwortlich']): ?><div class="muted">verantwortlich: <?= e($t['verantwortlich']) ?></div><?php endif; ?>
          </div>
        <?php elseif ($t['beschreibung']): ?>
          <div class="small muted" style="margin-top:.4rem"><?= nl2br(e(mb_substr((string)$t['beschreibung'], 0, 300))) ?></div>
        <?php endif; ?>

        <?php if ($t['aufgaben']): ?>
          <div class="small" style="margin-top:.4rem">Aufgaben:
            <?php foreach ($t['aufgaben'] as $i => $a): ?><?= $i ? ', ' : '' ?><a href="<?= e(url('todo', ['id' => $a['id']])) ?>"<?= (int)$a['erledigt'] ? ' style="text-decoration:line-through"' : '' ?>><?= e((string)$a['titel']) ?></a><?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if ($mehr): ?>
    <p class="small muted mt">Es werden die neuesten <?= TP_ARCHIV_LIMIT ?> Themen gezeigt – für ältere bitte filtern.</p>
  <?php endif; ?>
<?php endif; ?>
