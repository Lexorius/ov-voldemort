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

<form method="post" enctype="multipart/form-data" class="form" action="<?= e(url('kalender_edit', $isNew ? [] : ['id' => $termin['id']])) ?>">
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
        <label for="ort">Ort (frei)</label>
        <input type="text" id="ort" name="ort" maxlength="150" value="<?= e((string)($termin['ort'] ?? '')) ?>" placeholder="z. B. Übungsgelände Neckarwiese">
      </div>
    </div>
    <?= render_partial('partials/standorte_wahl', ['standorte' => $standorte ?? [], 'gewaehlt' => $standorteGewaehlt ?? [],
        'hinweis' => 'Aus den Stell- und Lagerplätzen – ein Termin kann an mehreren Plätzen stattfinden, etwa Gute Stube, Küche und Schulungsraum.']) ?>
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
    <?= render_partial('partials/wahl_liste', ['name' => 'fachgruppen', 'feld' => 'fachgruppen_feld', 'label' => 'Weitere Fachgruppen', 'optionen' => $fachgruppenOptionen ?? [], 'gewaehlt' => $fachgruppenGewaehlt ?? [],
        'leer' => '– Fachgruppe wählen –', 'hinweis' => 'Der Termin gilt dann auch für diese Fachgruppen – sie sehen ihn in ihrem Kalender. ' . (!empty($leitung) ? 'Die Leitung darf jede Fachgruppe dazunehmen.' : 'Du kannst deine eigene Fachgruppe dazunehmen.')]) ?>
  </section>

  <?php $fahrzeuge ??= []; $gewaehlt ??= []; $belegt ??= []; $bloecke = $fahrzeugBloecke ?? kalender_fahrzeuge_bloecke($fahrzeuge); if ($fahrzeuge): ?>
  <section class="card" id="fahrzeuge">
    <h2>Fahrzeuge</h2>
    <p class="small muted">Welche Fahrzeuge dieser Termin braucht – die Fahrzeugakte und die Startseite zeigen sie dann als verplant.
      Ein ⚠ heißt: zur gewählten Zeit schon in einem anderen Termin. Hinzufügen geht trotzdem, dann warnt die Seite vor der Doppelbuchung.</p>
    <?php $optionen = [];
    foreach (['fahrzeuge' => 'Autos und LKW', 'anhaenger' => 'Anhänger', 'geraete' => 'Geräte'] as $key => $titel) {
        foreach ($bloecke[$key] ?? [] as $v) {
            $andere = $belegt[(int)$v['id']] ?? [];
            $optionen[] = ['id' => (int)$v['id'], 'text' => (string)$v['bezeichnung'], 'sub' => implode(' · ', array_filter([(string)($v['funkrufname'] ?? ''), function_exists('vehicle_kapazitaet_text') ? vehicle_kapazitaet_text($v) : ''])), 'gruppe' => $titel,
                           'warn' => $andere ? 'schon im Termin ' . implode(' und ', array_map(static fn($t) => '„' . $t['titel'] . '" (' . kalender_wann($t) . ')', $andere)) : ''];
        }
    } ?>
    <?= render_partial('partials/wahl_liste', ['name' => 'vehicles', 'feld' => 'fahrzeuge_feld', 'label' => 'Fahrzeuge', 'optionen' => $optionen, 'gewaehlt' => $gewaehlt, 'leer' => '– Fahrzeug wählen –']) ?>
    <?= render_partial('partials/wahl_liste', ['name' => 'auftraege', 'feld' => 'auftraege_feld', 'label' => 'Instandsetzungsaufträge (Werkstatttermin)', 'optionen' => $auftragOptionen ?? [], 'gewaehlt' => $auftraegeGewaehlt ?? [],
        'leer' => '– Auftrag wählen –', 'hinweis' => 'Offene Aufträge der Fahrzeuge. Der Auftrag zeigt diesen Termin dann unter „Werkstatttermine" – etwa Abgabe, Abholung oder Nachbesserung.']) ?>
  </section>
  <?php endif; ?>

  <?php $kontaktOptionen ??= []; $kontakte ??= []; $kontakteGewaehlt ??= []; if ($kontaktOptionen && can('view_contacts')): ?>
  <section class="card" id="kontakte">
    <h2>Kontakte</h2>
    <p class="small muted">Ansprechpartner, Referenten oder Gäste aus dem Kontaktmodul – zum schnellen Anrufen oder Anschreiben vom Termin aus.</p>
    <?php if ($kontakte): ?>
      <ul class="wahl__liste" style="margin-bottom:.6rem">
        <?php foreach ($kontakte as $c): ?>
          <li class="wahl__eintrag" style="background:transparent">
            <span class="wahl__text"><a href="<?= e(url('contact_edit', ['id' => $c['id']])) ?>"><?= e(contact_name($c)) ?></a>
              <span class="muted small"><?= e(implode(' · ', array_filter([(string)($c['organisation'] ?? ''), (string)($c['position'] ?? '')]))) ?></span>
              <?php if (!empty($c['telefon']) || !empty($c['mobil']) || !empty($c['email'])): ?>
                <span class="small"><?php foreach (array_filter([$c['mobil'] ?? '', $c['telefon'] ?? '']) as $nr): ?> · <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string)$nr)) ?>"><?= e((string)$nr) ?></a><?php endforeach; ?><?= !empty($c['email']) ? ' · <a href="mailto:' . e((string)$c['email']) . '">' . e((string)$c['email']) . '</a>' : '' ?></span>
              <?php endif; ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?= render_partial('partials/wahl_liste', ['name' => 'kontakte', 'feld' => 'kontakte_feld', 'label' => 'Kontakte am Termin', 'optionen' => $kontaktOptionen, 'gewaehlt' => $kontakteGewaehlt, 'leer' => '– Kontakt wählen –',
        'hinweis' => 'Tippen zum Suchen, dann „+ Hinzufügen". Die Kontakte sehen nur Personen, die das Kontaktmodul sehen dürfen.']) ?>
  </section>
  <?php endif; ?>

  <?php $dateien ??= []; ?>
  <section class="card" id="dateien">
    <div class="card__head">
      <h2>Dateien</h2>
      <span class="muted small"><?= count($dateien) ?></span>
    </div>
    <?php if ($dateien): ?>
      <ul class="wahl__liste">
        <?php foreach ($dateien as $f): ?>
          <li class="wahl__eintrag">
            <?php if (($f['art'] ?? '') === 'bild' && !empty($f['thumb_name'])): ?>
              <a href="<?= e(url('kalender_file', ['id' => $f['id']])) ?>" target="_blank" rel="noopener"><img src="<?= e(url('kalender_file', ['id' => $f['id'], 'vorschau' => 1])) ?>" alt="" loading="lazy" style="width:2.6rem;height:2.6rem;object-fit:cover;border-radius:6px"></a>
            <?php endif; ?>
            <span class="wahl__text"><a href="<?= e(url('kalender_file', ['id' => $f['id']])) ?>"><?= e((string)($f['titel'] ?: $f['orig_name'])) ?></a>
              <span class="muted small"><?= e((string)$f['orig_name']) ?> · <?= e(bytes_human((int)$f['size_bytes'])) ?> · <?= e(de_datetime((string)$f['created_at'])) ?><?= !empty($f['hochgeladen_von']) ? ' · ' . e((string)$f['hochgeladen_von']) : '' ?></span></span>
            <?php if (!$nurLesen): ?>
              <button type="submit" class="wahl__weg" name="datei_weg" value="<?= (int)$f['id'] ?>" title="Datei entfernen" formnovalidate data-confirm="Diese Datei entfernen?">🗑</button>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php elseif ($nurLesen): ?>
      <p class="muted">Keine Dateien an diesem Termin.</p>
    <?php endif; ?>
    <?php if (!$nurLesen): ?>
      <div class="grid2">
        <div class="field">
          <label for="dateien-upload">Dateien anhängen</label>
          <input type="file" id="dateien-upload" name="dateien[]" multiple>
          <small>Einladung, Programm, Anfahrtsskizze – PDF, Bilder und die erlaubten Dokumenttypen, bis <?= function_exists('upload_max_bytes') ? (int)round(upload_max_bytes() / 1048576) : 8 ?> MB je Datei.
            Sie werden beim <?= $isNew ? 'Eintragen' : 'Speichern' ?> mit abgelegt.</small>
        </div>
        <div class="field">
          <label for="datei-titel">Bezeichnung der neuen Dateien <span class="muted">(freiwillig)</span></label>
          <input type="text" id="datei-titel" name="datei_titel" maxlength="200" placeholder="z. B. Einladung">
        </div>
      </div>
    <?php endif; ?>
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
