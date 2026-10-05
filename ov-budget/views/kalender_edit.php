<?php
/** @var array $termin @var array $errors @var bool $nurLesen @var array $user @var bool $leitung @var array $users */
$isNew = empty($termin['id']);
$ziel = kalender_ziel((string)($termin['ziel'] ?? 'user'));
$zielId = (int)($termin['ziel_id'] ?? 0);
$zurueck = url('kalender', ['monat' => substr((string)($termin['datum'] ?? date('Y-m-d')), 0, 7)]);
$ganztag = (int)($termin['ganztag'] ?? 0) === 1;
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Termin eintragen' : ($nurLesen ? 'Termin' : 'Termin bearbeiten') ?></h1>
    <?php if (!$isNew && !empty($termin['erfasser'])): ?><p class="muted small">eingetragen von <?= e((string)$termin['erfasser']) ?></p><?php endif; ?>
  </div>
  <a class="btn btn--sec" href="<?= e($zurueck) ?>">Zurück</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="form" action="<?= e(url('kalender_edit', $isNew ? [] : ['id' => $termin['id']])) ?>">
  <?= csrf_field() ?>
  <fieldset<?= $nurLesen ? ' disabled' : '' ?> style="border:0;padding:0;margin:0;min-width:0">
  <section class="card">
    <div class="field">
      <label for="titel">Titel *</label>
      <input type="text" id="titel" name="titel" required maxlength="200" value="<?= e((string)$termin['titel']) ?>" placeholder="z. B. Dienstabend, Fahrzeugpflege, Lehrgang">
    </div>
    <div class="grid3">
      <div class="field">
        <label for="datum">Datum *</label>
        <input type="date" id="datum" name="datum" required value="<?= e((string)$termin['datum']) ?>">
      </div>
      <div class="field">
        <label for="zeit">Uhrzeit</label>
        <input type="time" id="zeit" name="zeit" value="<?= e((string)($termin['zeit'] ?? '')) ?>"<?= $ganztag ? ' disabled' : '' ?> data-zeitfeld>
      </div>
      <div class="field field--check" style="align-self:end">
        <input type="checkbox" id="ganztag" name="ganztag" value="1"<?= $ganztag ? ' checked' : '' ?> data-ganztag>
        <label for="ganztag">ganztägig</label>
      </div>
      <div class="field">
        <label for="ende_datum">Ende (Datum)</label>
        <input type="date" id="ende_datum" name="ende_datum" value="<?= e((string)($termin['ende_datum'] ?? '')) ?>">
        <small>Leer = gleicher Tag.</small>
      </div>
      <div class="field">
        <label for="ende_zeit">Ende (Uhrzeit)</label>
        <input type="time" id="ende_zeit" name="ende_zeit" value="<?= e((string)($termin['ende_zeit'] ?? '')) ?>"<?= $ganztag ? ' disabled' : '' ?> data-zeitfeld>
      </div>
      <div class="field">
        <label for="ort">Ort</label>
        <input type="text" id="ort" name="ort" maxlength="150" value="<?= e((string)($termin['ort'] ?? '')) ?>">
      </div>
    </div>
    <div class="field">
      <label for="beschreibung">Beschreibung</label>
      <textarea id="beschreibung" name="beschreibung"><?= e((string)($termin['beschreibung'] ?? '')) ?></textarea>
    </div>
  </section>

  <section class="card">
    <h2>Für wen</h2>
    <div class="grid2">
      <div class="field">
        <label for="f-target-type">Gilt für</label>
        <select id="f-target-type" name="ziel">
          <?php foreach (KALENDER_ZIELE as $k => $lbl): ?>
            <?php if (!$leitung && $k === 'ov') { continue; } ?>
            <option value="<?= e($k) ?>"<?= $ziel === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
        <small><?= $leitung ? 'Die Leitung darf Termine für alle anlegen.' : 'Du darfst Termine für dich, deine Fachgruppe und deine Funktionen anlegen.' ?></small>
      </div>
      <div class="field" id="target-fachgruppe" hidden>
        <label for="ziel_fachgruppe">Fachgruppe</label>
        <select id="ziel_fachgruppe" name="ziel_fachgruppe">
          <?php if ($leitung): ?>
            <?= list_options('fachgruppe', $ziel === 'fachgruppe' ? $zielId : (int)($user['fachgruppe_id'] ?? 0)) ?>
          <?php else: ?>
            <?php $fg = (int)($user['fachgruppe_id'] ?? 0); ?>
            <?php if ($fg > 0): ?><option value="<?= $fg ?>" selected><?= e(list_label($fg)) ?></option>
            <?php else: ?><option value="">– du bist keiner Fachgruppe zugeordnet –</option><?php endif; ?>
          <?php endif; ?>
        </select>
      </div>
      <div class="field" id="target-funktion" hidden>
        <label for="ziel_funktion">Funktion</label>
        <select id="ziel_funktion" name="ziel_funktion">
          <?php if ($leitung): ?>
            <?= list_options('funktion', $ziel === 'funktion' ? $zielId : null) ?>
          <?php else: ?>
            <?php $fns = $user['functions'] ?? []; if (!$fns): ?><option value="">– du hast keine Funktion –</option><?php endif; ?>
            <?php foreach ($fns as $fid): ?><option value="<?= (int)$fid ?>"<?= $ziel === 'funktion' && $zielId === (int)$fid ? ' selected' : '' ?>><?= e(list_label((int)$fid)) ?></option><?php endforeach; ?>
          <?php endif; ?>
        </select>
      </div>
      <div class="field" id="target-user" hidden>
        <label for="ziel_user">Person</label>
        <select id="ziel_user" name="ziel_user">
          <?php if ($leitung): ?>
            <?php foreach ($users as $u2): ?>
              <option value="<?= (int)$u2['id'] ?>"<?= ($ziel === 'user' ? $zielId : (int)$user['id']) === (int)$u2['id'] ? ' selected' : '' ?>><?= e($u2['display_name'] ?: $u2['username']) ?></option>
            <?php endforeach; ?>
          <?php else: ?>
            <option value="<?= (int)$user['id'] ?>" selected><?= e((string)($user['display_name'] ?: $user['username'])) ?></option>
          <?php endif; ?>
        </select>
      </div>
      <div class="field">
        <label for="farbe">Farbe</label>
        <input type="color" id="farbe" name="farbe" value="<?= e((string)($termin['farbe'] ?: KALENDER_QUELLEN['termin']['color'])) ?>" style="width:4rem;height:2.4rem;padding:.1rem">
        <small>Zum Wiedererkennen im Monat.</small>
      </div>
    </div>
  </section>

  <?php if (!$nurLesen): ?>
  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Eintragen' : 'Speichern' ?></button>
    <a class="btn btn--sec" href="<?= e($zurueck) ?>">Abbrechen</a>
  </div>
  <?php endif; ?>
  </fieldset>
</form>

<?php if (!$isNew && can('view_meetings')): ?><?= render_partial('partials/tp_besprochen', ['punkte' => $besprochen ?? []]) ?><?php endif; ?>

<?php if (!$isNew && !$nurLesen): ?>
  <form method="post" class="card" action="<?= e(url('kalender_edit', ['id' => $termin['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <button class="btn btn--danger" type="submit" data-confirm="Diesen Termin löschen?">Termin löschen</button>
  </form>
<?php endif; ?>
