<?php
/** @var array $serie @var array $errors @var ?array $vorschau @var array $termine */
$isNew = empty($serie['id']);
$uhr = static fn($v) => $v ? substr((string)$v, 0, 5) : '';
$regel = (string)($serie['regel'] ?? 'woche');
$mitWochentag = static fn(string $d) => WOCHENTAGE[(int)date('N', strtotime($d))] . ', ' . de_date($d);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Wiederkehrende Besprechung' : 'Serie bearbeiten' ?></h1>
    <p>
      <?php if (!$isNew): ?>
        <strong><?= e(series_describe($serie)) ?></strong>
        <?php if (!(int)$serie['is_active']): ?> <span class="badge badge--muted">pausiert</span><?php endif; ?>
      <?php else: ?>
        Aus der Regel entstehen die Termine für die nächsten <?= series_horizon_days() ?> Tage als
        eigene Besprechungen – so lassen sich Themen schon auf die nächste setzen.
      <?php endif; ?>
    </p>
  </div>
  <a class="btn btn--sec" href="<?= e(url('meetings')) ?>">Zu den Besprechungen</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="form" action="<?= e(url('meeting_series_edit', $isNew ? [] : ['id' => $serie['id']])) ?>">
  <?= csrf_field() ?>

  <section class="card">
    <h2>Rhythmus</h2>
    <div class="grid3">
      <div class="field">
        <label for="f-regel">Wiederholung</label>
        <select id="f-regel" name="regel">
          <?php foreach (SERIE_REGELN as $k => $lbl): ?>
            <option value="<?= e($k) ?>"<?= $regel === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="intervall">Alle</label>
        <div style="display:flex;gap:.5rem;align-items:center">
          <input type="number" id="intervall" name="intervall" min="1" max="12" style="max-width:6rem"
                 value="<?= (int)($serie['intervall'] ?? 1) ?>">
          <span id="f-intervall-einheit"><?= $regel === 'woche' ? 'Woche(n)' : 'Monat(e)' ?></span>
        </div>
        <small>1 = jede Woche bzw. jeden Monat, 2 = jede zweite …</small>
      </div>
      <div class="field" data-regel="monat_wochentag">
        <label for="nte">Der wievielte</label>
        <select id="nte" name="nte">
          <?php foreach (SERIE_NTE as $k => $lbl): ?>
            <option value="<?= (int)$k ?>"<?= (int)($serie['nte'] ?? 1) === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" data-regel="woche monat_wochentag">
        <label for="wochentag">Wochentag</label>
        <select id="wochentag" name="wochentag">
          <?php foreach (WOCHENTAGE as $k => $lbl): ?>
            <option value="<?= (int)$k ?>"<?= (int)($serie['wochentag'] ?? 1) === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" data-regel="monat_tag">
        <label for="monatstag">Tag im Monat</label>
        <input type="number" id="monatstag" name="monatstag" min="1" max="31" value="<?= (int)($serie['monatstag'] ?? 1) ?>">
        <small>Gibt es den Tag nicht (31. im April), ist der Monatsletzte gemeint.</small>
      </div>
    </div>
    <p class="small muted mb0">Beispiele: „alle 2 Wochen montags" = wöchentlich, alle 2, Montag ·
      „jeden 2. Montag im Monat" = monatlich an einem Wochentag, alle 1, der 2., Montag.</p>
  </section>

  <section class="card">
    <h2>Termin</h2>
    <div class="grid2">
      <div class="field">
        <label for="titel">Titel *</label>
        <input type="text" id="titel" name="titel" required maxlength="200" value="<?= e((string)$serie['titel']) ?>"
               placeholder="z.B. Dienstbesprechung">
      </div>
      <div class="field">
        <label for="typ_id">Art</label>
        <select id="typ_id" name="typ_id"><?= list_options('besprechung_typ', (int)($serie['typ_id'] ?? 0)) ?></select>
      </div>
    </div>
    <div class="grid3">
      <div class="field">
        <label for="beginn">Beginn</label>
        <input type="time" id="beginn" name="beginn" value="<?= e($uhr($serie['beginn'] ?? null)) ?>">
      </div>
      <div class="field">
        <label for="ende">Ende</label>
        <input type="time" id="ende" name="ende" value="<?= e($uhr($serie['ende'] ?? null)) ?>">
      </div>
      <div class="field">
        <label for="ort">Ort</label>
        <input type="text" id="ort" name="ort" value="<?= e((string)$serie['ort']) ?>">
      </div>
      <div class="field">
        <label for="leitung">Leitung</label>
        <input type="text" id="leitung" name="leitung" value="<?= e((string)$serie['leitung']) ?>">
      </div>
    </div>
    <div class="field">
      <label for="teilnehmer">Eingeladen</label>
      <textarea id="teilnehmer" name="teilnehmer" rows="2"><?= e((string)$serie['teilnehmer']) ?></textarea>
    </div>
    <div class="field">
      <label for="beschreibung">Hinweise</label>
      <textarea id="beschreibung" name="beschreibung" rows="2"><?= e((string)$serie['beschreibung']) ?></textarea>
    </div>
  </section>

  <section class="card">
    <h2>Gültigkeit</h2>
    <div class="grid3">
      <div class="field">
        <label for="start_datum">Ab *</label>
        <input type="date" id="start_datum" name="start_datum" required value="<?= e((string)$serie['start_datum']) ?>">
        <small>Legt bei „alle 2 Wochen" auch fest, in welcher Woche es losgeht.</small>
      </div>
      <div class="field">
        <label for="end_datum">Bis</label>
        <input type="date" id="end_datum" name="end_datum" value="<?= e((string)($serie['end_datum'] ?? '')) ?>">
        <small>Leer lassen für unbefristet.</small>
      </div>
      <div class="field field--check">
        <input type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($serie['is_active']) ? 'checked' : '' ?>>
        <label for="is_active">Aktiv – ohne Haken werden keine neuen Termine angelegt</label>
      </div>
    </div>
  </section>

  <div class="btnrow">
    <button class="btn btn--sec" type="submit" name="action" value="preview">Termine vorab anzeigen</button>
    <button class="btn" type="submit" name="action" value="save"><?= $isNew ? 'Serie anlegen' : 'Speichern' ?></button>
  </div>
</form>

<?php if ($vorschau !== null): ?>
  <section class="card mt" id="vorschau">
    <h2>So fallen die nächsten Termine</h2>
    <p><strong><?= e(series_describe($serie)) ?></strong></p>
    <?php if (!$vorschau): ?>
      <div class="empty">Im nächsten Jahr ergibt sich kein Termin – Zeitraum prüfen.</div>
    <?php else: ?>
      <ul style="margin:0;padding-left:1.1rem">
        <?php foreach (array_slice($vorschau, 0, 8) as $d): ?><li><?= e($mitWochentag($d)) ?></li><?php endforeach; ?>
      </ul>
      <?php if (count($vorschau) > 8): ?><p class="small muted">… und <?= count($vorschau) - 8 ?> weitere im nächsten Jahr.</p><?php endif; ?>
      <p class="small muted mb0">Noch nicht gespeichert.</p>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if (!$isNew && $termine): ?>
  <section class="card mt">
    <h2>Kommende Termine</h2>
    <div class="tablewrap">
      <table class="data">
        <tbody>
        <?php foreach ($termine as $t): ?>
          <tr>
            <td><a href="<?= e(url('meeting', ['id' => $t['id']])) ?>"><?= e($mitWochentag($t['datum'])) ?></a>
              <?php if ($t['serien_datum'] && $t['datum'] !== $t['serien_datum']): ?>
                <div class="small muted">verschoben vom <?= e(de_date($t['serien_datum'])) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($t['status'] === 'abgesagt'): ?><span class="badge" style="background:#b91c1c">abgesagt</span>
              <?php elseif ($t['status'] === 'abgeschlossen'): ?><span class="badge" style="background:#15803d">abgeschlossen</span>
              <?php endif; ?>
            </td>
            <td class="small muted"><?= (int)$t['punkte'] ?> Themen</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="small muted mb0">Einzelne Termine lassen sich in der Besprechung verschieben oder absagen,
      ohne die Serie zu ändern.</p>
  </section>

  <form method="post" class="card" action="<?= e(url('meeting_series_edit', ['id' => $serie['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <p class="small muted">Leere künftige Termine verschwinden mit der Serie. Vergangene Besprechungen und
      Termine mit Themen oder Notizen bleiben als einzelne Besprechungen erhalten. Zum Pausieren
      reicht der Haken „Aktiv".</p>
    <button class="btn btn--danger" type="submit" data-confirm="Serie löschen?">Serie löschen</button>
  </form>
<?php endif; ?>
