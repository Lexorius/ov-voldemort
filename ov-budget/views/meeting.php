<?php
/** @var array $meeting @var array $punkte @var array $zeiten @var int $gesamt @var array $speicher
 *  @var array $teilnehmer @var array $anwesenheit @var array $kandidaten @var array $kontakte
 *  @var string $kontaktSuche @var array $verteiler @var ?array $quelle */
$verwalten = can('manage_meetings');
$geplant = $meeting['status'] === 'geplant';
$label = tp_label();

$offen = 0;
foreach ($punkte as $p) {
    if (!(int)$p['status_final'] && $p['status_slug'] !== 'vertagt') {
        $offen++;
    }
}

$ende = '';
if ($meeting['beginn'] && preg_match('/^(\d{1,2}):(\d{2})/', (string)$meeting['beginn'], $m)) {
    $min = (int)$m[1] * 60 + (int)$m[2] + $gesamt;
    $ende = sprintf('%02d:%02d', intdiv($min, 60) % 24, $min % 60);
}
?>
<div class="pagehead">
  <div>
    <h1><?= e($meeting['titel']) ?></h1>
    <p class="small">
      <?php if ($meeting['typ_label']): ?>
        <?= badge(['label' => $meeting['typ_label'], 'color' => $meeting['typ_color']]) ?>
      <?php endif; ?>
      <?= match ($meeting['status']) {
          'geplant'  => '<span class="badge" style="background:#0284c7">geplant</span>',
          'abgesagt' => '<span class="badge" style="background:#b91c1c">abgesagt</span>',
          default    => '<span class="badge" style="background:#15803d">abgeschlossen</span>',
      } ?>
      <?php if (!empty($meeting['series_id'])): ?>
        <span class="badge badge--outline" title="Wiederkehrende Besprechung">↻ <?= e(series_describe(meeting_series_rule($meeting))) ?></span>
      <?php endif; ?>
    </p>
    <?php if (!empty($meeting['serien_datum']) && $meeting['serien_datum'] !== $meeting['datum']): ?>
      <p class="small muted">Verschoben vom regulären Termin am <?= e(de_date($meeting['serien_datum'])) ?>.</p>
    <?php endif; ?>
    <p class="muted">
      <?= e(de_date($meeting['datum'])) ?>
      <?php if ($meeting['beginn']): ?>, <?= e(substr((string)$meeting['beginn'], 0, 5)) ?> Uhr<?php endif; ?>
      <?php if ($meeting['ort']): ?> · <?= e($meeting['ort']) ?><?php endif; ?>
      <?php if ($meeting['leitung']): ?> · Leitung: <?= e($meeting['leitung']) ?><?php endif; ?>
    </p>
    <?php if ($meeting['beschreibung']): ?><p><?= nl2br(e((string)$meeting['beschreibung'])) ?></p><?php endif; ?>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('meeting_print', ['id' => $meeting['id']])) ?>" target="_blank" rel="noopener">Tagesordnung</a>
    <a class="btn btn--sec" href="<?= e(url('meeting_print', ['id' => $meeting['id'], 'art' => 'protokoll'])) ?>" target="_blank" rel="noopener">Protokoll</a>
    <?php if ($verwalten): ?>
      <a class="btn btn--sec" href="<?= e(url('meeting_edit', ['id' => $meeting['id']])) ?>">Bearbeiten</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($meeting['status'] === 'abgesagt'): ?>
  <div class="alert alert--warn" style="display:flex;justify-content:space-between;gap:.8rem;align-items:center;flex-wrap:wrap">
    <span><strong>Dieser Termin fällt aus.</strong></span>
    <?php if ($verwalten): ?>
      <form method="post" action="<?= e(url('meeting_action')) ?>" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reinstate">
        <input type="hidden" name="meeting_id" value="<?= (int)$meeting['id'] ?>">
        <button class="btn btn--sec btn--sm" type="submit">Findet doch statt</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="stats">
  <div class="stat"><div class="stat__label"><?= e($label) ?></div><div class="stat__value"><?= count($punkte) ?></div></div>
  <div class="stat"><div class="stat__label">Noch offen</div><div class="stat__value"><?= $offen ?></div></div>
  <div class="stat"><div class="stat__label">Geplante Dauer</div><div class="stat__value" style="font-size:1.1rem"><?= e(minutes_human($gesamt)) ?></div></div>
  <div class="stat"><div class="stat__label">Voraussichtliches Ende</div>
    <div class="stat__value" style="font-size:1.1rem"><?= $ende !== '' ? e($ende) . ' Uhr' : '–' ?></div></div>
</div>

<section class="card">
  <div class="card__head">
    <h2>Tagesordnung</h2>
    <?php if ($geplant && can('create_talking_point')): ?>
      <a class="btn btn--sm" href="<?= e(url('talking_point_edit', ['meeting_id' => $meeting['id']])) ?>">+ <?= e($label) ?></a>
    <?php endif; ?>
  </div>

  <?php if (!$punkte): ?>
    <div class="empty">Noch nichts auf der Tagesordnung.
      <?php if ($verwalten && $speicher): ?><br>Unten aus dem Themenspeicher übernehmen.<?php endif; ?>
    </div>
  <?php else: ?>
    <div class="itemlist">
      <?php foreach ($punkte as $i => $p):
          $bearbeitbar = tp_editable($p);
          $farbe = $p['status_color'] ?: '#94a3b8';
      ?>
        <div class="card tp" id="tp<?= (int)$p['id'] ?>" style="margin:0;border-left:4px solid <?= e($farbe) ?>">
          <div class="item__top">
            <div style="min-width:0">
              <div class="muted small">
                TOP <?= $i + 1 ?>
                <?php if ($zeiten[$i] !== ''): ?> · <?= e($zeiten[$i]) ?> Uhr<?php endif; ?>
                · <?= e(minutes_human($p['dauer_min'] !== null ? (int)$p['dauer_min'] : setting_int('tp_dauer_vorgabe', 10))) ?>
              </div>
              <h3 style="margin:.15rem 0 0"><?= e($p['titel']) ?></h3>
              <div class="item__meta">
                <?= badge($p['status_label'] ? ['label' => $p['status_label'], 'color' => $p['status_color']] : null) ?>
                <?php if ($p['prio_label']): ?><?= badge(['label' => $p['prio_label'], 'color' => $p['prio_color']]) ?><?php endif; ?>
                <?php if ($p['fachgruppe_label']): ?><span class="badge badge--outline"><?= e($p['fachgruppe_label']) ?></span><?php endif; ?>
                <?php if ($p['einbringer']): ?><span class="small muted">eingebracht von <?= e($p['einbringer']) ?></span><?php endif; ?>
                <?php if ($p['vorgaenger_id']): ?><span class="badge badge--outline">vertagt übernommen</span><?php endif; ?>
              </div>
            </div>
            <?php if ($verwalten && $geplant): ?>
              <div class="btnrow" style="flex-wrap:nowrap">
                <?php foreach (['hoch' => '↑', 'runter' => '↓'] as $richtung => $pfeil): ?>
                  <form method="post" action="<?= e(url('meeting_action')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="move">
                    <input type="hidden" name="meeting_id" value="<?= (int)$meeting['id'] ?>">
                    <input type="hidden" name="tp_id" value="<?= (int)$p['id'] ?>">
                    <input type="hidden" name="richtung" value="<?= e($richtung) ?>">
                    <button class="btn btn--sec btn--sm" type="submit" title="nach <?= $richtung === 'hoch' ? 'oben' : 'unten' ?>"
                            <?= ($richtung === 'hoch' && $i === 0) || ($richtung === 'runter' && $i === count($punkte) - 1) ? 'disabled' : '' ?>><?= $pfeil ?></button>
                  </form>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($p['beschreibung']): ?>
            <div class="comment__body small" style="margin-top:.5rem"><?= e((string)$p['beschreibung']) ?></div>
          <?php endif; ?>

          <?php if ($verwalten): ?>
            <form method="post" action="<?= e(url('meeting_action')) ?>" class="form mt">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="result">
              <input type="hidden" name="meeting_id" value="<?= (int)$meeting['id'] ?>">
              <input type="hidden" name="tp_id" value="<?= (int)$p['id'] ?>">
              <div class="grid2">
                <div class="field">
                  <label for="st<?= (int)$p['id'] ?>">Status</label>
                  <select id="st<?= (int)$p['id'] ?>" name="status_id"><?= list_options('tp_status', (int)$p['status_id'], '') ?></select>
                </div>
                <div class="field">
                  <label for="ve<?= (int)$p['id'] ?>">Verantwortlich</label>
                  <input type="text" id="ve<?= (int)$p['id'] ?>" name="verantwortlich" value="<?= e((string)$p['verantwortlich']) ?>">
                </div>
              </div>
              <div class="field">
                <label for="er<?= (int)$p['id'] ?>">Ergebnis / Beschluss</label>
                <textarea id="er<?= (int)$p['id'] ?>" name="ergebnis" rows="3"
                          placeholder="Was wurde besprochen oder beschlossen?"><?= e((string)$p['ergebnis']) ?></textarea>
              </div>
              <div class="btnrow">
                <button class="btn btn--sm" type="submit">Festhalten</button>
                <?php if ($p['todo_id']): ?>
                  <a class="btn btn--sec btn--sm" href="<?= e(url('todo', ['id' => $p['todo_id']])) ?>">→ Aufgabe: <?= e(mb_substr((string)$p['todo_titel'], 0, 40)) ?></a>
                <?php endif; ?>
              </div>
            </form>

            <div class="btnrow mt">
              <?php if (!$p['todo_id']): ?>
                <form method="post" action="<?= e(url('meeting_action')) ?>" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="todo">
                  <input type="hidden" name="meeting_id" value="<?= (int)$meeting['id'] ?>">
                  <input type="hidden" name="tp_id" value="<?= (int)$p['id'] ?>">
                  <button class="btn btn--sec btn--sm" type="submit"
                          title="Das festgehaltene Ergebnis wird zur Beschreibung der Aufgabe">Aufgabe daraus machen</button>
                </form>
              <?php endif; ?>
              <?php if ($geplant): ?>
                <form method="post" action="<?= e(url('meeting_action')) ?>" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="unassign">
                  <input type="hidden" name="meeting_id" value="<?= (int)$meeting['id'] ?>">
                  <input type="hidden" name="tp_id" value="<?= (int)$p['id'] ?>">
                  <button class="btn btn--sec btn--sm" type="submit">Zurück in den Themenspeicher</button>
                </form>
              <?php endif; ?>
              <?php if ($bearbeitbar): ?>
                <a class="btn btn--sec btn--sm" href="<?= e(url('talking_point_edit', ['id' => $p['id']])) ?>">Bearbeiten</a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <?php if ($p['ergebnis']): ?>
              <div class="mt"><div class="dl__label">Ergebnis</div>
                <div class="comment__body"><?= e((string)$p['ergebnis']) ?></div></div>
            <?php endif; ?>
            <?php if ($bearbeitbar): ?>
              <div class="btnrow mt"><a class="btn btn--sec btn--sm" href="<?= e(url('talking_point_edit', ['id' => $p['id']])) ?>">Bearbeiten</a></div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($verwalten && $geplant && $speicher): ?>
  <section class="card">
    <h2>Aus dem Themenspeicher übernehmen</h2>
    <form method="post" action="<?= e(url('meeting_action')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="assign">
      <input type="hidden" name="meeting_id" value="<?= (int)$meeting['id'] ?>">
      <div class="tablewrap">
        <table class="data">
          <tbody>
          <?php foreach ($speicher as $s): ?>
            <tr>
              <td style="width:2.5rem"><input type="checkbox" name="tp_ids[]" value="<?= (int)$s['id'] ?>" id="sp<?= (int)$s['id'] ?>"></td>
              <td>
                <label for="sp<?= (int)$s['id'] ?>"><strong><?= e($s['titel']) ?></strong></label>
                <?php if ($s['status_slug'] === 'vertagt'): ?>
                  <div class="small muted">vertagt aus „<?= e((string)$s['meeting_titel']) ?>" vom <?= e(de_date($s['meeting_datum'])) ?></div>
                <?php endif; ?>
              </td>
              <td class="small"><?= e($s['fachgruppe_label'] ?: '') ?></td>
              <td><?= $s['prio_label'] ? badge(['label' => $s['prio_label'], 'color' => $s['prio_color']]) : '' ?></td>
              <td class="small muted"><?= e($s['einbringer'] ?: '') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="btnrow mt"><button class="btn" type="submit">Ausgewählte auf die Tagesordnung</button></div>
    </form>
  </section>
<?php endif; ?>

<?= render_partial('partials/attendance', [
    'meeting' => $meeting, 'teilnehmer' => $teilnehmer, 'anwesenheit' => $anwesenheit,
    'verwalten' => $verwalten, 'kandidaten' => $kandidaten, 'kontakte' => $kontakte,
    'kontaktSuche' => $kontaktSuche, 'verteiler' => $verteiler, 'quelle' => $quelle,
]) ?>

<section class="card" id="protokoll">
  <h2>Protokoll</h2>
  <?php if ($verwalten): ?>
    <form method="post" action="<?= e(url('meeting_action')) ?>" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="notes">
      <input type="hidden" name="meeting_id" value="<?= (int)$meeting['id'] ?>">
      <div class="grid2">
        <div class="field">
          <label for="teilnehmer">Teilnehmende als Freitext (Ergänzung zur Liste oben)</label>
          <textarea id="teilnehmer" name="teilnehmer" rows="3"
                    placeholder="Eine Person je Zeile oder durch Komma getrennt"><?= e((string)$meeting['teilnehmer']) ?></textarea>
        </div>
        <div class="field">
          <label for="protokoll_von">Protokoll</label>
          <input type="text" id="protokoll_von" name="protokoll_von" value="<?= e((string)$meeting['protokoll_von']) ?>">
        </div>
      </div>
      <div class="field">
        <label for="notizen">Allgemeine Notizen</label>
        <textarea id="notizen" name="notizen" rows="4"
                  placeholder="Was nicht zu einem einzelnen Thema gehört"><?= e((string)$meeting['notizen']) ?></textarea>
      </div>
      <div><button class="btn btn--sm" type="submit">Speichern</button></div>
    </form>

    <div class="btnrow mt">
      <form method="post" action="<?= e(url('meeting_action')) ?>" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="meeting_id" value="<?= (int)$meeting['id'] ?>">
        <?php if ($geplant): ?>
          <input type="hidden" name="action" value="close">
          <button class="btn btn--ok" type="submit"
                  data-confirm="Besprechung abschließen? Noch offene Themen werden als vertagt markiert.">Besprechung abschließen</button>
        <?php elseif ($meeting['status'] === 'abgesagt'): ?>
          <input type="hidden" name="action" value="reinstate">
          <button class="btn btn--sec" type="submit">Findet doch statt</button>
        <?php else: ?>
          <input type="hidden" name="action" value="reopen">
          <button class="btn btn--sec" type="submit">Wieder öffnen</button>
        <?php endif; ?>
      </form>
      <?php if ($geplant): ?>
        <form method="post" action="<?= e(url('meeting_action')) ?>" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="cancel">
          <input type="hidden" name="meeting_id" value="<?= (int)$meeting['id'] ?>">
          <button class="btn btn--sec" type="submit"
                  data-confirm="Termin absagen? Offene Themen wandern zurück in den Themenspeicher.">Termin absagen</button>
        </form>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <dl class="dl">
      <div class="dl__item"><div class="dl__label">Teilnehmende</div>
        <div class="dl__value"><?= nl2br(e((string)$meeting['teilnehmer'] ?: '–')) ?></div></div>
      <div class="dl__item"><div class="dl__label">Protokoll</div>
        <div class="dl__value"><?= e((string)$meeting['protokoll_von'] ?: '–') ?></div></div>
    </dl>
    <?php if ($meeting['notizen']): ?>
      <div class="comment__body mt"><?= e((string)$meeting['notizen']) ?></div>
    <?php endif; ?>
  <?php endif; ?>
</section>
