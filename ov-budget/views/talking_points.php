<?php
/** @var array $rows @var array $filters @var array $geplant @var array $diveraThemen */
$label = tp_label();
$diveraThemen ??= [];
?>
<div class="pagehead">
  <div>
    <h1>Themenspeicher</h1>
    <p>Eingebrachte <?= e($label) ?>, die noch keiner Besprechung zugeordnet sind, und vertagte Themen
       aus früheren Besprechungen.</p>
  </div>
  <div class="btnrow">
    <?php if (can('create_talking_point')): ?>
      <a class="btn" href="<?= e(url('talking_point_edit')) ?>">+ <?= e($label) ?></a>
    <?php endif; ?>
    <?php if ($diveraThemen && can('manage_meetings')): ?>
      <form method="post" action="<?= e(url('meeting_action')) ?>" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="divera_themen">
        <button class="btn btn--sec" type="submit" title="Neue Einreichungen aus dem Divera-Formular jetzt holen">Aus Divera abrufen</button>
      </form>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('meetings')) ?>">Besprechungen</a>
  </div>
</div>

<?php if ($diveraThemen): ?>
  <p class="small muted">Themen lassen sich auch in Divera über das Formular
    <?= implode(', ', array_map(static fn($f) => '„' . e($f['name']) . '"', $diveraThemen)) ?> einreichen;
    sie erscheinen dann hier<?php
    $zuletzt = max(array_map(static fn($f) => (string)$f['last_sync'], $diveraThemen));
    if ($zuletzt !== ''): ?> (zuletzt abgerufen <?= e(de_datetime($zuletzt)) ?>)<?php endif; ?>.</p>
<?php endif; ?>

<?= render_partial('partials/tp_tabs', ['tab' => 'offen']) ?>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="talking_points">
  <div class="filters">
    <div class="field">
      <label for="q">Suche</label>
      <input type="search" id="q" name="q" value="<?= e((string)$filters['q']) ?>">
    </div>
    <div class="field">
      <label for="fachgruppe_id">Fachgruppe</label>
      <select id="fachgruppe_id" name="fachgruppe_id"><?= list_options('fachgruppe', $filters['fachgruppe_id'], 'alle') ?></select>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <button class="btn btn--sec" type="submit">Filtern</button>
    </div>
  </div>
</form>

<?php if (!$rows): ?>
  <div class="card"><div class="empty">Der Themenspeicher ist leer.</div></div>
<?php else: ?>

  <?php if ($geplant): ?>
    <form method="post" action="<?= e(url('meeting_action')) ?>" id="zuordnen">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="assign">
  <?php endif; ?>

  <div class="itemlist">
    <?php foreach ($rows as $t): ?>
      <div class="item" style="border-left-color:<?= e($t['prio_color'] ?: '#94a3b8') ?>">
        <div class="item__top">
          <div style="display:flex;gap:.6rem;min-width:0">
            <?php if ($geplant): ?>
              <input type="checkbox" name="tp_ids[]" value="<?= (int)$t['id'] ?>" aria-label="auswählen" style="margin-top:.2rem">
            <?php endif; ?>
            <div style="min-width:0">
              <div class="item__title"><?= e($t['titel']) ?></div>
              <div class="item__sub">
                <?php if ($t['status_slug'] === 'vertagt'): ?>
                  vertagt aus „<?= e((string)$t['meeting_titel']) ?>" vom <?= e(de_date($t['meeting_datum'])) ?>
                <?php else: ?>
                  eingebracht <?= e(de_date($t['created_at'])) ?><?= $t['einbringer'] ? ' von ' . e($t['einbringer']) : '' ?><?= !empty($t['aus_divera']) ? ' · über Divera' : '' ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php if (tp_editable($t)): ?>
            <a class="btn btn--sec btn--sm" href="<?= e(url('talking_point_edit', ['id' => $t['id']])) ?>">Bearbeiten</a>
          <?php endif; ?>
        </div>
        <?php if ($t['beschreibung']): ?>
          <div class="small muted" style="margin-top:.4rem"><?= nl2br(e(mb_substr((string)$t['beschreibung'], 0, 300))) ?></div>
        <?php endif; ?>
        <div class="item__meta">
          <?= $t['prio_label'] ? badge(['label' => $t['prio_label'], 'color' => $t['prio_color']]) : '' ?>
          <?php if ($t['status_slug'] === 'vertagt'): ?>
            <?= badge(['label' => $t['status_label'], 'color' => $t['status_color']]) ?>
          <?php endif; ?>
          <?php if ($t['fachgruppe_label']): ?><span class="badge badge--outline"><?= e($t['fachgruppe_label']) ?></span><?php endif; ?>
          <?php if ($t['dauer_min']): ?><span class="badge badge--outline"><?= (int)$t['dauer_min'] ?> Min.</span><?php endif; ?>
          <?php if ($t['ergebnis']): ?><span class="small muted">bisher: <?= e(mb_substr((string)$t['ergebnis'], 0, 80)) ?></span><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($geplant): ?>
      <div class="card mt">
        <div class="grid2">
          <div class="field">
            <label for="meeting_id">Ausgewählte setzen auf</label>
            <select id="meeting_id" name="meeting_id" required>
              <?php foreach ($geplant as $m): ?>
                <option value="<?= (int)$m['id'] ?>"><?= e(de_date($m['datum']) . ' · ' . $m['titel']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>&nbsp;</label>
            <button class="btn" type="submit">Auf die Tagesordnung</button>
          </div>
        </div>
      </div>
    </form>
  <?php endif; ?>

<?php endif; ?>
