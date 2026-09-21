<?php
/** Ein Talking Point mit Diskussion
 *  @var array $tp @var array $kommentare @var bool $offen @var array $user */
$label = tp_label();
$zurueck = $tp['meeting_id']
    ? url('meeting', ['id' => $tp['meeting_id']]) . '#tp' . (int)$tp['id']
    : url('talking_points');
?>
<div class="pagehead">
  <div>
    <p class="muted small"><?= e($label) ?></p>
    <h1><?= e($tp['titel']) ?></h1>
    <div class="item__meta">
      <?= badge($tp['status_label'] ? ['label' => $tp['status_label'], 'color' => $tp['status_color']] : null) ?>
      <?php if ($tp['prio_label']): ?><?= badge(['label' => $tp['prio_label'], 'color' => $tp['prio_color']]) ?><?php endif; ?>
      <?php if ($tp['fachgruppe_label']): ?><span class="badge badge--outline"><?= e($tp['fachgruppe_label']) ?></span><?php endif; ?>
    </div>
  </div>
  <div class="btnrow">
    <?php if (tp_editable($tp)): ?>
      <a class="btn btn--sec" href="<?= e(url('talking_point_edit', ['id' => $tp['id']])) ?>">Bearbeiten</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e($zurueck) ?>">Zurück</a>
  </div>
</div>

<div class="card">
  <dl class="dl">
    <div class="dl__item"><div class="dl__label">Besprechung</div>
      <div class="dl__value">
        <?php if ($tp['meeting_id']): ?>
          <a href="<?= e(url('meeting', ['id' => $tp['meeting_id']])) ?>#tp<?= (int)$tp['id'] ?>"><?= e((string)$tp['meeting_titel']) ?></a>
          am <?= e(de_date($tp['meeting_datum'])) ?>
        <?php else: ?>
          <span class="muted">noch im Themenspeicher</span>
        <?php endif; ?>
      </div></div>
    <div class="dl__item"><div class="dl__label">Eingebracht</div>
      <div class="dl__value"><?= e(de_date($tp['created_at'])) ?><?= $tp['einbringer'] ? ' von ' . e($tp['einbringer']) : '' ?></div></div>
    <?php if ($tp['verantwortlich']): ?>
      <div class="dl__item"><div class="dl__label">Verantwortlich</div>
        <div class="dl__value"><?= e($tp['verantwortlich']) ?></div></div>
    <?php endif; ?>
  </dl>
  <?php if ($tp['beschreibung']): ?>
    <div class="comment__body mt"><?= nl2br(e((string)$tp['beschreibung'])) ?></div>
  <?php endif; ?>
  <?php if ($tp['ergebnis']): ?>
    <div class="mt"><div class="dl__label">Ergebnis</div>
      <div class="comment__body"><?= nl2br(e((string)$tp['ergebnis'])) ?></div></div>
  <?php endif; ?>
</div>

<section class="card" id="anmerkungen">
  <div class="card__head">
    <h2>Anmerkungen und Diskussion</h2>
    <span class="small muted"><?= count($kommentare) ?></span>
  </div>

  <?php if (!$kommentare): ?>
    <p class="muted small"><?= $offen ? 'Noch keine Anmerkungen – gern die erste schreiben.' : 'Es gab keine Anmerkungen.' ?></p>
  <?php else: ?>
    <?php foreach ($kommentare as $k): ?>
      <div class="comment" id="anmerkung<?= (int)$k['id'] ?>">
        <div class="comment__meta">
          <strong><?= e($k['autor'] ?: 'unbekannt') ?></strong> · <?= e(de_datetime($k['created_at'])) ?>
          <?php if (tp_comment_deletable($k, $tp, $user)): ?>
            <form method="post" class="inline-form" style="margin-left:.4rem">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="anmerkung_loeschen">
              <input type="hidden" name="comment_id" value="<?= (int)$k['id'] ?>">
              <button class="btn btn--sec btn--sm" type="submit"
                      data-confirm="Diese Anmerkung entfernen?">Entfernen</button>
            </form>
          <?php endif; ?>
        </div>
        <div class="comment__body"><?= nl2br(e((string)$k['body'])) ?></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($offen): ?>
    <form method="post" class="form mt">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="anmerkung">
      <div class="field">
        <label for="body">Anmerkung</label>
        <textarea id="body" name="body" rows="4" required maxlength="5000"
                  placeholder="Ergänzung, Frage, Gegenvorschlag …"></textarea>
      </div>
      <div class="btnrow"><button class="btn" type="submit">Anmerkung speichern</button></div>
      <p class="small muted">Wer den Punkt eingebracht oder schon mitdiskutiert hat, wird benachrichtigt.</p>
    </form>
  <?php else: ?>
    <p class="small muted mt">Der Punkt ist abgeschlossen (<?= e((string)$tp['status_label']) ?>) – die Diskussion
      bleibt lesbar, neue Anmerkungen gehen nicht mehr.</p>
  <?php endif; ?>
</section>
