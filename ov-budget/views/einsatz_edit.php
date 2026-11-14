<?php
/** @var array $einsatz @var array $tage @var array $errors @var array $fahrzeuge @var array $fahrzeugeGewaehlt @var int $buchungen */
$isNew = empty($einsatz['id']);
$zurueck = $isNew ? url('einsaetze') : url('einsatz', ['id' => $einsatz['id']]);

// Eine Zeile der Tagestabelle. Die Stärke steht als Text („1/2/9") – aus der Datenbank kommen drei Zahlen.
$zeile = static function (array $t): string {
    $staerke = array_key_exists('staerke_text', $t)
        ? (string)$t['staerke_text']
        : (((int)($t['staerke_f'] ?? 0) + (int)($t['staerke_u'] ?? 0) + (int)($t['staerke_h'] ?? 0)) > 0
            ? (int)$t['staerke_f'] . '/' . (int)$t['staerke_u'] . '/' . (int)$t['staerke_h'] : '');
    $zeit = static fn($v): string => is_string($z = einsatz_zeit($v)) ? $z : trim((string)$v);
    return '<tr>'
        . '<td><input type="date" name="tag_datum[]" value="' . e((string)($t['datum'] ?? '')) . '" aria-label="Datum"></td>'
        . '<td><input type="time" name="tag_von[]" value="' . e($zeit($t['von'] ?? '')) . '" aria-label="von"></td>'
        . '<td><input type="time" name="tag_bis[]" value="' . e($zeit($t['bis'] ?? '')) . '" aria-label="bis"></td>'
        . '<td><input type="text" name="tag_staerke[]" value="' . e($staerke) . '" placeholder="1/2/9" inputmode="numeric" maxlength="24" style="max-width:7rem" aria-label="Stärke: Führer/Unterführer/Helfer"></td>'
        . '<td><input type="text" name="tag_taetigkeit[]" value="' . e((string)($t['taetigkeit'] ?? '')) . '" maxlength="500" placeholder="z. B. Sandsackverbau Deich Süd" aria-label="Tätigkeit"></td>'
        . '<td><button type="button" class="btn btn--sec btn--sm" data-zeile-weg title="Zeile entfernen" aria-label="Zeile entfernen">🗑</button></td>'
        . '</tr>';
};
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Einsatz anlegen' : 'Einsatz bearbeiten' ?></h1>
    <p class="muted small">Ein Einsatz geht über einen oder mehrere Tage. Je Tag – oder je Schicht – eine Zeile mit Zeit, Stärke und Tätigkeit;
      daraus rechnet die Seite Einsatztage, Einsatzstunden und Helferstunden.</p>
  </div>
  <a class="btn btn--sec" href="<?= e($zurueck) ?>">Abbrechen</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="form" action="<?= e($isNew ? url('einsatz_edit') : url('einsatz_edit', ['id' => $einsatz['id']])) ?>">
  <?= csrf_field() ?>
  <section class="card">
    <div class="grid2">
      <div class="field">
        <label for="titel">Titel / Einsatzstichwort *</label>
        <input type="text" id="titel" name="titel" required maxlength="200" value="<?= e((string)($einsatz['titel'] ?? '')) ?>"
               placeholder="z. B. Hochwasser Hunte – Deichsicherung" <?= $isNew ? 'autofocus' : '' ?>>
      </div>
      <div class="field">
        <label for="nummer">Einsatznummer</label>
        <input type="text" id="nummer" name="nummer" maxlength="40" value="<?= e((string)($einsatz['nummer'] ?? '')) ?>" placeholder="z. B. 2026-014">
        <small class="muted"><?= $isNew ? 'Vorgeschlagen ist die nächste freie Nummer des Jahres – überschreibbar, etwa mit der Nummer der Leitstelle.' : 'Steht bei Buchungen als Einsatz-/Auftragsnummer.' ?></small>
      </div>
    </div>
    <div class="grid3">
      <div class="field">
        <label for="art_id">Art</label>
        <select id="art_id" name="art_id"><?= list_options('einsatz_art', (int)($einsatz['art_id'] ?? 0)) ?></select>
      </div>
      <div class="field">
        <label for="status">Stand</label>
        <select id="status" name="status">
          <?php foreach (EINSATZ_STATUS as $key => $s): ?>
            <option value="<?= e($key) ?>" <?= $key === einsatz_status((string)($einsatz['status'] ?? 'laufend')) ? 'selected' : '' ?>><?= e($s['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="fachgruppe_id">Fachgruppe</label>
        <select id="fachgruppe_id" name="fachgruppe_id"><?= list_options('fachgruppe', (int)($einsatz['fachgruppe_id'] ?? 0), 'ortsverbandsweit') ?></select>
      </div>
    </div>
    <div class="grid2">
      <div class="field">
        <label for="anforderer">Anforderer / Kostenträger</label>
        <input type="text" id="anforderer" name="anforderer" maxlength="200" value="<?= e((string)($einsatz['anforderer'] ?? '')) ?>" placeholder="z. B. Landkreis, Feuerwehr, Polizei">
        <small class="muted">Wer angefordert hat – wird bei einer Einnahme zum Einsatz als Auftraggeber vorgeschlagen.</small>
      </div>
      <div class="field">
        <label for="ort">Einsatzort</label>
        <input type="text" id="ort" name="ort" maxlength="200" value="<?= e((string)($einsatz['ort'] ?? '')) ?>">
      </div>
    </div>
    <div class="field">
      <label for="beschreibung">Lage und Auftrag</label>
      <textarea id="beschreibung" name="beschreibung" rows="3"><?= e((string)($einsatz['beschreibung'] ?? '')) ?></textarea>
    </div>
  </section>

  <section class="card" id="tage" data-zeilen>
    <h2>Tage und Schichten</h2>
    <p class="small muted">Je Tag eine Zeile; zwei Schichten am selben Tag sind zwei Zeilen. Liegt das Ende nicht nach dem Beginn, endet die Schicht
      am Folgetag (22:00 bis 06:00). Ohne Uhrzeit zählt der Tag als Einsatztag, aber ohne Stunden; ohne Ende läuft die Schicht noch.
      Stärke wie gemeldet: Führer/Unterführer/Helfer, etwa „1/2/9" – eine einzelne Zahl zählt als Helfer.</p>
    <div class="tablewrap">
      <table class="data zeilen">
        <thead><tr><th>Datum</th><th>von</th><th>bis</th><th>Stärke</th><th style="width:45%">Tätigkeit</th><th></th></tr></thead>
        <tbody data-zeilen-body>
          <?php foreach ($tage as $t): ?><?= $zeile($t) ?><?php endforeach; ?>
          <?= $zeile([]) ?>
          <?= $zeile([]) ?>
        </tbody>
      </table>
    </div>
    <div class="btnrow mt">
      <button type="button" class="btn btn--sec" data-zeile-folgetag title="Kopiert die letzte ausgefüllte Zeile auf den nächsten Tag">+ Folgetag</button>
      <button type="button" class="btn btn--sec" data-zeile-neu>+ Leere Zeile</button>
    </div>
    <small class="muted">Leere Zeilen werden beim Speichern übergangen. Ohne JavaScript: speichern und wieder öffnen bringt zwei neue leere Zeilen.</small>
  </section>

  <?php if ($fahrzeuge): ?>
  <section class="card">
    <h2>Fahrzeuge</h2>
    <?php $fzOptionen = [];
    $bloecke = function_exists('kalender_fahrzeuge_bloecke') ? kalender_fahrzeuge_bloecke($fahrzeuge) : ['fahrzeuge' => $fahrzeuge];
    foreach (['fahrzeuge' => 'Autos und LKW', 'anhaenger' => 'Anhänger', 'geraete' => 'Geräte'] as $key => $gruppe) {
        foreach ($bloecke[$key] ?? [] as $v) {
            $fzOptionen[] = ['id' => (int)$v['id'], 'text' => (string)$v['bezeichnung'], 'sub' => (string)($v['funkrufname'] ?? ''), 'gruppe' => $gruppe, 'warn' => ''];
        }
    } ?>
    <?= render_partial('partials/wahl_liste', ['name' => 'vehicles', 'feld' => 'fahrzeuge_feld', 'label' => 'Eingesetzte Fahrzeuge', 'optionen' => $fzOptionen,
        'gewaehlt' => $fahrzeugeGewaehlt, 'leer' => '– Fahrzeug wählen –', 'hinweis' => 'Welche Fahrzeuge im Einsatz waren – über alle Tage.']) ?>
  </section>
  <?php endif; ?>

  <section class="card">
    <h2>Sonstiges</h2>
    <div class="field">
      <label for="notiz">Interne Notiz</label>
      <textarea id="notiz" name="notiz" rows="2"><?= e((string)($einsatz['notiz'] ?? '')) ?></textarea>
    </div>
    <div class="btnrow">
      <button class="btn" type="submit"><?= $isNew ? 'Einsatz anlegen' : 'Speichern' ?></button>
      <a class="btn btn--sec" href="<?= e($zurueck) ?>">Abbrechen</a>
    </div>
    <?php if ($isNew): ?><p class="small muted" style="margin-bottom:0">Ausgaben und Einnahmen kommen nach dem Anlegen dazu – auf der Seite des Einsatzes oder im Budget.</p><?php endif; ?>
  </section>
</form>

<?php if (!$isNew && can('delete_einsaetze')): ?>
  <form method="post" class="card" action="<?= e(url('einsatz_edit', ['id' => $einsatz['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="loeschen">
    <button class="btn btn--danger" type="submit" data-confirm="Einsatz „<?= e(einsatz_bezeichnung($einsatz)) ?>“ mit allen Tagen löschen?">Einsatz löschen</button>
    <span class="small muted" style="margin-left:.6rem"><?= $buchungen > 0
        ? ($buchungen === 1 ? 'Die eine Buchung bleibt' : 'Die ' . (int)$buchungen . ' Buchungen bleiben') . ' im Budget stehen und verlieren nur den Bezug.'
        : 'Tage und Fahrzeugzuordnung gehen mit.' ?></span>
  </form>
<?php endif; ?>
