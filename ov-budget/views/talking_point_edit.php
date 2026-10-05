<?php
/** @var array $tp @var array $errors @var array $geplant */
$isNew = empty($tp['id']);
$label = tp_label();
$verwalten = can('manage_meetings');
$zurueck = !empty($tp['meeting_id']) ? url('meeting', ['id' => $tp['meeting_id']]) : url('talking_points');
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? e($label) . ' einbringen' : 'Thema bearbeiten' ?></h1>
    <?php if ($isNew): ?>
      <p>Kurz und konkret: Worum geht es, und was soll die Besprechung klären oder entscheiden?</p>
    <?php endif; ?>
  </div>
  <a class="btn btn--sec" href="<?= e($zurueck) ?>">Abbrechen</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card form" action="<?= e(url('talking_point_edit', $isNew ? [] : ['id' => $tp['id']])) ?>">
  <?= csrf_field() ?>

  <div class="field">
    <label for="titel">Thema *</label>
    <input type="text" id="titel" name="titel" required maxlength="200" value="<?= e((string)$tp['titel']) ?>"
           placeholder="z.B. Dienstplan 1. Halbjahr, Ersatz Stromerzeuger">
  </div>
  <div class="field">
    <label for="beschreibung">Hintergrund</label>
    <textarea id="beschreibung" name="beschreibung" rows="4"
              placeholder="Was sollen alle vorher wissen? Was ist die Frage?"><?= e((string)$tp['beschreibung']) ?></textarea>
  </div>

  <div class="grid3">
    <div class="field">
      <label for="fachgruppe_id">Betrifft</label>
      <select id="fachgruppe_id" name="fachgruppe_id"><?= list_options('fachgruppe', (int)($tp['fachgruppe_id'] ?? 0), 'den ganzen OV') ?></select>
    </div>
    <div class="field">
      <label for="prioritaet_id">Dringlichkeit</label>
      <select id="prioritaet_id" name="prioritaet_id"><?= list_options('todo_prioritaet', (int)($tp['prioritaet_id'] ?? 0), '') ?></select>
    </div>
    <div class="field">
      <label for="dauer_min">Zeitbedarf (Minuten)</label>
      <input type="number" id="dauer_min" name="dauer_min" min="1" max="600" step="5"
             value="<?= e((string)($tp['dauer_min'] ?? '')) ?>"
             placeholder="<?= setting_int('tp_dauer_vorgabe', 10) ?>">
    </div>
  </div>

  <?php if ($isNew && $geplant): ?>
    <div class="field">
      <label for="meeting_id">Auf Besprechung setzen</label>
      <select id="meeting_id" name="meeting_id">
        <option value="">– erst in den Themenspeicher –</option>
        <?php foreach ($geplant as $m): ?>
          <option value="<?= (int)$m['id'] ?>"<?= (int)($tp['meeting_id'] ?? 0) === (int)$m['id'] ? ' selected' : '' ?>>
            <?= e(de_date($m['datum']) . ' · ' . $m['titel']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <?php $links ??= []; $links += ['termin' => [], 'event' => [], 'vehicle' => [], 'radio' => [], 'budget' => []];
        $auswahl ??= []; $auswahl += ['termine' => [], 'events' => [], 'vehicles' => [], 'radios' => [], 'budgets' => []]; ?>
  <fieldset>
    <legend>Bezüge</legend>
    <p class="small muted">Worüber gesprochen wird: Termine aus dem Kalender, Veranstaltungen, Fahrzeuge, Funkgeräte. Sie stehen am
      Punkt und im Protokoll – Veranstaltungen mit Budgettopf und Verpflegung – und umgekehrt in Fahrzeugakte, Gerät, Termin
      und Veranstaltung.</p>
    <input type="hidden" name="bezuege" value="1">
    <?php if ($auswahl['budgets']): ?>
      <div class="field">
        <label>Budgetübersicht <span class="muted small">(wie viel Geld da war, was ausgegeben wurde und wofür – mit Anteilen)</span></label>
        <div class="chips">
          <?php foreach ($auswahl['budgets'] as $j): ?>
            <label class="chip" style="cursor:pointer"><input type="checkbox" name="budgets[]" value="<?= (int)$j ?>"<?= in_array((int)$j, $links['budget'], true) ? ' checked' : '' ?>> Budget <?= (int)$j ?></label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <div class="grid2">
      <div class="field">
        <label>Veranstaltungen <span class="muted small">(anstehende)</span></label>
        <?php if (!$auswahl['events']): ?><p class="small muted">Keine anstehende Veranstaltung.</p>
        <?php else: ?>
        <div class="wahlliste">
          <?php foreach ($auswahl['events'] as $ev): ?>
            <label class="wahl"><input type="checkbox" name="events[]" value="<?= (int)$ev['id'] ?>"<?= in_array((int)$ev['id'], $links['event'], true) ? ' checked' : '' ?>>
              <?= e((string)$ev['titel']) ?> <span class="muted small">· <?= e(de_date(substr((string)$ev['beginn'], 0, 10))) ?></span></label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="field">
        <label>Termine <span class="muted small">(nächste Wochen)</span></label>
        <?php if (!$auswahl['termine']): ?><p class="small muted">Kein Termin im Kalender.</p>
        <?php else: ?>
        <div class="wahlliste">
          <?php foreach ($auswahl['termine'] as $t): ?>
            <label class="wahl"><input type="checkbox" name="termine[]" value="<?= (int)$t['id'] ?>"<?= in_array((int)$t['id'], $links['termin'], true) ? ' checked' : '' ?>>
              <?= e((string)$t['titel']) ?> <span class="muted small">· <?= e(de_date(substr((string)$t['beginn'], 0, 10))) ?><?= (int)$t['ganztag'] === 1 ? '' : ', ' . e(substr((string)$t['beginn'], 11, 5)) ?></span></label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="field">
        <label>Fahrzeuge</label>
        <?php if (!$auswahl['vehicles']): ?><p class="small muted">–</p>
        <?php else: ?>
        <div class="wahlliste">
          <?php foreach ($auswahl['vehicles'] as $v): ?>
            <label class="wahl"><input type="checkbox" name="vehicles[]" value="<?= (int)$v['id'] ?>"<?= in_array((int)$v['id'], $links['vehicle'], true) ? ' checked' : '' ?>>
              <?= e((string)$v['bezeichnung']) ?><?= $v['funkrufname'] ? ' <span class="muted small">' . e((string)$v['funkrufname']) . '</span>' : '' ?></label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="field">
        <label>Funkgeräte</label>
        <?php if (!$auswahl['radios']): ?><p class="small muted">–</p>
        <?php else: ?>
        <div class="wahlliste">
          <?php foreach ($auswahl['radios'] as $r): ?>
            <label class="wahl"><input type="checkbox" name="radios[]" value="<?= (int)$r['id'] ?>"<?= in_array((int)$r['id'], $links['radio'], true) ? ' checked' : '' ?>>
              <?= e((string)$r['bezeichnung']) ?><?= $r['funkrufname'] ? ' <span class="muted small">' . e((string)$r['funkrufname']) . '</span>' : '' ?></label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </fieldset>

  <?php if ($verwalten && !$isNew): ?>
    <fieldset>
      <legend>Ergebnis</legend>
      <div class="grid2">
        <div class="field">
          <label for="status_id">Status</label>
          <select id="status_id" name="status_id"><?= list_options('tp_status', (int)($tp['status_id'] ?? 0), '') ?></select>
        </div>
        <div class="field">
          <label for="verantwortlich">Verantwortlich</label>
          <input type="text" id="verantwortlich" name="verantwortlich" value="<?= e((string)($tp['verantwortlich'] ?? '')) ?>">
        </div>
      </div>
      <div class="field">
        <label for="ergebnis">Ergebnis / Beschluss</label>
        <textarea id="ergebnis" name="ergebnis" rows="3"><?= e((string)($tp['ergebnis'] ?? '')) ?></textarea>
      </div>
    </fieldset>
  <?php endif; ?>

  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Einbringen' : 'Speichern' ?></button>
    <a class="btn btn--sec" href="<?= e($zurueck) ?>">Abbrechen</a>
  </div>
</form>

<?php if (!$isNew): ?>
  <form method="post" class="card" action="<?= e(url('talking_point_edit', ['id' => $tp['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <button class="btn btn--danger" type="submit" data-confirm="Thema endgültig löschen?">Thema löschen</button>
  </form>
<?php endif; ?>
