<?php
/**
 * Anwesenheit einer Besprechung.
 * @var array $meeting @var array $teilnehmer @var array $anwesenheit @var bool $verwalten
 * @var array $kandidaten @var array $kontakte @var string $kontaktSuche @var array $verteiler
 * @var ?array $quelle
 */
$stati = list_items('teilnahme_status');
$aktion = url('meeting_action');
?>
<section class="card" id="anwesenheit">
  <div class="card__head">
    <h2>Anwesenheit</h2>
    <span class="small">
      <?php if ($anwesenheit['anzahl'] === 0): ?>
        <span class="muted">niemand eingeladen</span>
      <?php else: ?>
        <span class="badge" style="background:#15803d"><?= (int)$anwesenheit['anwesend'] ?> da</span>
        <?php if ($anwesenheit['entschuldigt'] > 0): ?>
          <span class="badge" style="background:#a16207"><?= (int)$anwesenheit['entschuldigt'] ?> entschuldigt</span>
        <?php endif; ?>
        <?php if ($anwesenheit['fehlt'] > 0): ?>
          <span class="badge" style="background:#b91c1c"><?= (int)$anwesenheit['fehlt'] ?> fehlten</span>
        <?php endif; ?>
        <?php if ($anwesenheit['offen'] > 0): ?>
          <span class="badge badge--outline"><?= (int)$anwesenheit['offen'] ?> offen</span>
        <?php endif; ?>
      <?php endif; ?>
    </span>
  </div>

  <?php if (!$teilnehmer): ?>
    <div class="empty">
      Noch niemand auf der Liste.
      <?php if ($verwalten): ?>Unten Personen hinzufügen.<?php endif; ?>
    </div>
    <?php if ($verwalten && $quelle): ?>
      <form method="post" action="<?= e($aktion) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="attend_copy">
        <input type="hidden" name="meeting_id" value="<?= (int)$meeting['id'] ?>">
        <input type="hidden" name="von_meeting_id" value="<?= (int)$quelle['id'] ?>">
        <button class="btn btn--sec btn--sm" type="submit">
          Liste vom <?= e(de_date($quelle['datum'])) ?> übernehmen (<?= (int)$quelle['anzahl'] ?>)</button>
      </form>
    <?php endif; ?>

  <?php elseif (!$verwalten): ?>
    <div class="tablewrap">
      <table class="data">
        <tbody>
        <?php foreach ($teilnehmer as $t): ?>
          <tr>
            <td><?= e(attendance_name($t, true)) ?></td>
            <td><?= badge($t['status_label'] ? ['label' => $t['status_label'], 'color' => $t['status_color']] : null, 'eingeladen') ?></td>
            <td class="small muted"><?= e((string)$t['notiz']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  <?php else: ?>
    <div class="btnrow" style="margin-bottom:.8rem">
      <?php foreach ([ANWESEND_SLUG => 'Alle offenen: waren da', ENTSCHULDIGT_SLUG => 'Alle offenen: entschuldigt',
                      FEHLT_SLUG => 'Alle offenen: nicht erschienen'] as $slug => $text): ?>
        <form method="post" action="<?= e($aktion) ?>" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="attend_all">
          <input type="hidden" name="meeting_id" value="<?= (int)$meeting['id'] ?>">
          <input type="hidden" name="slug" value="<?= e($slug) ?>">
          <button class="btn btn--sec btn--sm" type="submit"><?= e($text) ?></button>
        </form>
      <?php endforeach; ?>
    </div>

    <form method="post" action="<?= e($aktion) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="attend_status">
      <input type="hidden" name="meeting_id" value="<?= (int)$meeting['id'] ?>">

      <?php foreach ($teilnehmer as $t): $id = (int)$t['id']; ?>
        <div class="attend">
          <div class="attend__name">
            <strong><?= e(attendance_name($t)) ?></strong>
            <?php $zusatz = $t['organisation'] ?: $t['fachgruppe_label']; ?>
            <?php if ($zusatz): ?><div class="small muted"><?= e((string)$zusatz) ?></div><?php endif; ?>
          </div>
          <div class="chips">
            <?php foreach ($stati as $s): $gewaehlt = (int)$t['status_id'] === (int)$s['id']; ?>
              <label class="chip chip--radio<?= $gewaehlt ? ' is-on' : '' ?>">
                <input type="radio" name="status[<?= $id ?>]" value="<?= (int)$s['id'] ?>"<?= $gewaehlt ? ' checked' : '' ?>>
                <span class="chip__dot" style="background:<?= e($s['color'] ?: '#94a3b8') ?>"></span><?= e($s['label']) ?>
              </label>
            <?php endforeach; ?>
          </div>
          <input type="text" name="notiz[<?= $id ?>]" value="<?= e((string)$t['notiz']) ?>"
                 placeholder="Bemerkung" aria-label="Bemerkung zu <?= e(attendance_name($t)) ?>">
          <label class="chip chip--del">
            <input type="checkbox" name="remove[]" value="<?= $id ?>"> entfernen
          </label>
        </div>
      <?php endforeach; ?>

      <div class="btnrow"><button class="btn" type="submit">Anwesenheit speichern</button></div>
    </form>
  <?php endif; ?>

  <?php if ($verwalten): ?>
    <details class="mt">
      <summary>Personen hinzufügen</summary>
      <form method="post" action="<?= e($aktion) ?>" class="form" style="margin-top:.8rem">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="attend_add">
        <input type="hidden" name="meeting_id" value="<?= (int)$meeting['id'] ?>">

        <?php if ($kandidaten): ?>
          <div class="field">
            <label>Aus dem Ortsverband</label>
            <div class="checkgrid">
              <?php foreach ($kandidaten as $k): ?>
                <label class="chip">
                  <input type="checkbox" name="user_ids[]" value="<?= (int)$k['id'] ?>">
                  <?= e($k['name']) ?><?php if ($k['fachgruppe_label']): ?> <span class="muted small"><?= e($k['fachgruppe_label']) ?></span><?php endif; ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($verteiler): ?>
          <div class="field">
            <label for="group_id">Ganzen Verteiler einladen</label>
            <select id="group_id" name="group_id">
              <option value="">– keiner –</option>
              <?php foreach ($verteiler as $v): ?>
                <option value="<?= (int)$v['id'] ?>"><?= e($v['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="field">
          <label for="namen">Weitere Namen</label>
          <textarea id="namen" name="namen" rows="2" placeholder="Eine Person je Zeile"></textarea>
        </div>

        <div class="btnrow"><button class="btn" type="submit">Hinzufügen</button></div>
      </form>

      <?php if (can('view_contacts')): ?>
        <form method="get" class="card card--tight" style="margin-top:.8rem">
          <input type="hidden" name="p" value="meeting">
          <input type="hidden" name="id" value="<?= (int)$meeting['id'] ?>">
          <div class="field">
            <label for="kontakt_suche">Kontakte suchen</label>
            <input type="search" id="kontakt_suche" name="kontakt_suche" value="<?= e($kontaktSuche) ?>"
                   placeholder="Name oder Organisation">
          </div>
          <button class="btn btn--sec btn--sm" type="submit">Suchen</button>
        </form>

        <?php if ($kontaktSuche !== ''): ?>
          <form method="post" action="<?= e($aktion) ?>" style="margin-top:.6rem">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="attend_add">
            <input type="hidden" name="meeting_id" value="<?= (int)$meeting['id'] ?>">
            <?php if (!$kontakte): ?>
              <div class="empty">Kein passender Kontakt gefunden.</div>
            <?php else: ?>
              <div class="checkgrid">
                <?php foreach ($kontakte as $c): ?>
                  <label class="chip">
                    <input type="checkbox" name="contact_ids[]" value="<?= (int)$c['id'] ?>">
                    <?= e(trim((string)$c['name']) ?: (string)$c['organisation']) ?>
                    <?php if ($c['name'] && $c['organisation']): ?><span class="muted small"><?= e((string)$c['organisation']) ?></span><?php endif; ?>
                  </label>
                <?php endforeach; ?>
              </div>
              <div class="btnrow mt"><button class="btn btn--sec btn--sm" type="submit">Ausgewählte Kontakte einladen</button></div>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </details>
  <?php endif; ?>
</section>
