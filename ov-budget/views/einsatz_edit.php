<?php
/** @var array $einsatz @var array $tage @var array $errors @var array $fahrzeuge @var int $buchungen */
$isNew = empty($einsatz['id']);
$zurueck = $isNew ? url('einsaetze') : url('einsatz', ['id' => $einsatz['id']]);
$fahrzeuge ??= [];

/*
 * Eine Zeile: die Einsatzzeit einer Einheit an einem Tag. $k ist ihre Kennung im Formular –
 * unter ihr stehen die Fahrzeuge (tag_fz[k][]), deren Kilometer (tag_km[k][fahrzeug]) und
 * Betriebsstunden (tag_bh[k][fahrzeug]).
 * Die Stärke steht als Text („1/2/9"); aus der Datenbank kommen drei Zahlen.
 */
$zeile = static function (array $t, int $k) use ($fahrzeuge): string {
    $staerke = array_key_exists('staerke_text', $t)
        ? (string)$t['staerke_text']
        : (((int)($t['staerke_f'] ?? 0) + (int)($t['staerke_u'] ?? 0) + (int)($t['staerke_h'] ?? 0)) > 0
            ? (int)$t['staerke_f'] . '/' . (int)$t['staerke_u'] . '/' . (int)$t['staerke_h'] : '');
    $zeit = static fn($v): string => is_string($z = einsatz_zeit($v)) ? $z : trim((string)$v);
    $gewaehlt = array_map('intval', (array)($t['vehicles'] ?? []));
    $km = (array)($t['km'] ?? []);
    $bh = (array)($t['bh'] ?? []);
    $zahl = static fn(array $werte, int $id): string => isset($werte[$id]) && $werte[$id] !== null && $werte[$id] !== ''
        ? (is_numeric($werte[$id]) ? num_input((float)$werte[$id], true, 1) : (string)$werte[$id]) : '';
    $html = '<div class="ezeile" data-zeile>'
        . '<input type="hidden" name="tag_key[]" value="' . $k . '" data-zeile-key>'
        . '<div class="ezeile__kopf">'
        . '<div class="field"><label>Datum</label><input type="date" name="tag_datum[]" value="' . e((string)($t['datum'] ?? '')) . '" aria-label="Datum"></div>'
        . '<div class="field"><label>von</label><input type="time" name="tag_von[]" value="' . e($zeit($t['von'] ?? '')) . '" aria-label="von"></div>'
        . '<div class="field"><label>bis</label><input type="time" name="tag_bis[]" value="' . e($zeit($t['bis'] ?? '')) . '" aria-label="bis"></div>'
        . '<div class="field"><label>Fachgruppe</label><select name="tag_fachgruppe[]" aria-label="Fachgruppe">'
        . list_options('fachgruppe', (int)($t['fachgruppe_id'] ?? 0), '– keine –') . '</select></div>'
        . '<div class="field"><label>Stärke</label><input type="text" name="tag_staerke[]" value="' . e($staerke) . '" placeholder="1/2/9" inputmode="numeric" maxlength="24" aria-label="Stärke: Führer/Unterführer/Helfer"></div>'
        . '<button type="button" class="btn btn--sec btn--sm ezeile__weg" data-zeile-weg title="Zeile entfernen" aria-label="Zeile entfernen">🗑</button>'
        . '</div>'
        . '<div class="field"><label>Tätigkeit</label><input type="text" name="tag_taetigkeit[]" value="' . e((string)($t['taetigkeit'] ?? '')) . '" maxlength="500" placeholder="z. B. Sandsackverbau Deich Süd" aria-label="Tätigkeit"></div>';
    if ($fahrzeuge) {
        $html .= '<details class="ezeile__fz"' . ($gewaehlt ? ' open' : '') . '><summary>Fahrzeuge <span class="muted" data-zeile-fz-zahl>' . ($gewaehlt ? '(' . count($gewaehlt) . ')' : '') . '</span></summary>'
            . '<div class="ezeile__fzliste">';
        foreach ($fahrzeuge as $v) {
            $id = (int)$v['id'];
            $an = in_array($id, $gewaehlt, true);
            $html .= '<div class="ezeile__fahrzeug"><label class="wahl"><input type="checkbox" name="tag_fz[' . $k . '][]" value="' . $id . '"' . ($an ? ' checked' : '') . '> '
                . e((string)$v['bezeichnung']) . (trim((string)($v['funkrufname'] ?? '')) !== '' ? ' <span class="muted small">' . e((string)$v['funkrufname']) . '</span>' : '') . '</label>'
                . '<input type="text" name="tag_km[' . $k . '][' . $id . ']" value="' . e($zahl($km, $id)) . '" inputmode="decimal" maxlength="9" placeholder="km" title="gefahrene Kilometer" aria-label="gefahrene Kilometer ' . e((string)$v['bezeichnung']) . '">'
                . '<input type="text" name="tag_bh[' . $k . '][' . $id . ']" value="' . e($zahl($bh, $id)) . '" inputmode="decimal" maxlength="8" placeholder="Bh" title="Betriebsstunden" aria-label="Betriebsstunden ' . e((string)$v['bezeichnung']) . '"></div>';
        }
        $html .= '</div></details>';
    }
    return $html . '</div>';
};
$k = 0;

/* Eine Zeile fremder Kräfte: Einheit als Freitext, Zeit, Stärke – und wahlweise ihr Zeichen für die Karte */
$fremde ??= [];
$symbole ??= [];
// Die Bibliothek kann Hunderte Zeichen haben: die ganze Liste steht einmal auf der Seite (template#fremd-zeichen)
// und kommt erst beim Öffnen in die Auswahl (app.js, data-optionen). Je Zeile nur das gewählte Zeichen.
$symbolNamen = array_column($symbole, 'name', 'id');
$fremdZeile = static function (array $f, int $k) use ($symbole, $symbolNamen): string {
    $zeichen = (int)($f['symbol_id'] ?? 0);
    $staerke = array_key_exists('staerke_text', $f)
        ? (string)$f['staerke_text']
        : (((int)($f['staerke_f'] ?? 0) + (int)($f['staerke_u'] ?? 0) + (int)($f['staerke_h'] ?? 0)) > 0
            ? (int)$f['staerke_f'] . '/' . (int)$f['staerke_u'] . '/' . (int)$f['staerke_h'] : '');
    $zeit = static fn($v): string => is_string($z = einsatz_zeit($v)) ? $z : trim((string)$v);
    return '<div class="ezeile" data-zeile>'
        . '<input type="hidden" name="fremd_key[]" value="' . $k . '" data-zeile-key>'
        . '<div class="ezeile__kopf' . ($symbole ? ' ezeile__kopf--zeichen' : '') . '">'
        . '<div class="field"><label>Datum</label><input type="date" name="fremd_datum[]" value="' . e((string)($f['datum'] ?? '')) . '" aria-label="Datum"></div>'
        . '<div class="field"><label>von</label><input type="time" name="fremd_von[]" value="' . e($zeit($f['von'] ?? '')) . '" aria-label="von"></div>'
        . '<div class="field"><label>bis</label><input type="time" name="fremd_bis[]" value="' . e($zeit($f['bis'] ?? '')) . '" aria-label="bis"></div>'
        . '<div class="field"><label>Einheit</label><input type="text" name="fremd_einheit[]" value="' . e((string)($f['einheit'] ?? '')) . '" maxlength="150" placeholder="z. B. FF Musterstadt, LZ 2" aria-label="Einheit"></div>'
        . '<div class="field"><label>Stärke</label><input type="text" name="fremd_staerke[]" value="' . e($staerke) . '" placeholder="1/2/9" inputmode="numeric" maxlength="24" aria-label="Stärke: Führer/Unterführer/Helfer"></div>'
        . ($symbole ? '<div class="field"><label>Zeichen</label><select name="fremd_symbol[]" data-optionen="fremd-zeichen" aria-label="Taktisches Zeichen für die Karte">'
            . '<option value="">– kein Zeichen –</option>'
            . (isset($symbolNamen[$zeichen]) ? '<option value="' . $zeichen . '" selected>' . e((string)$symbolNamen[$zeichen]) . '</option>' : '')
            . '</select></div>' : '')
        . '<button type="button" class="btn btn--sec btn--sm ezeile__weg" data-zeile-weg title="Zeile entfernen" aria-label="Zeile entfernen">🗑</button>'
        . '</div></div>';
};
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Einsatz anlegen' : 'Einsatz bearbeiten' ?></h1>
    <p class="muted small">Ein Einsatz geht über einen oder mehrere Tage. Je Tag und Einheit eine Zeile mit Zeit, Fachgruppe, Fahrzeugen, Stärke und Tätigkeit;
      daraus rechnet die Seite Einsatztage, Einsatzstunden, Helferstunden, Kilometer und Betriebsstunden.</p>
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
        <label for="fachgruppe_id">Federführende Fachgruppe</label>
        <select id="fachgruppe_id" name="fachgruppe_id"><?= list_options('fachgruppe', (int)($einsatz['fachgruppe_id'] ?? 0), 'ortsverbandsweit') ?></select>
        <small class="muted">Wer wann im Einsatz war, steht unten je Zeile.</small>
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
    <h2>Tage, Fachgruppen und Fahrzeuge</h2>
    <p class="small muted">Eine Zeile ist die Einsatzzeit einer Einheit an einem Tag. <strong>Mehrere Fachgruppen am selben Tag sind mehrere Zeilen</strong> –
      jede mit eigenen Uhrzeiten, eigener Stärke und ihren Fahrzeugen; an jedem Fahrzeug lassen sich die gefahrenen Kilometer und die Betriebsstunden eintragen
      (eines von beiden genügt – ein Aggregat hat nur Stunden).
      Liegt das Ende nicht nach dem Beginn, endet die Schicht am Folgetag (22:00 bis 06:00). Ohne Uhrzeit zählt der Tag als Einsatztag,
      aber ohne Stunden; ohne Ende läuft die Schicht noch. Stärke wie gemeldet: Führer/Unterführer/Helfer, etwa „1/2/9" – eine einzelne Zahl zählt als Helfer.</p>
    <div data-zeilen-body>
      <?php foreach ($tage as $t): ?><?= $zeile($t, $k++) ?><?php endforeach; ?>
      <?= $zeile([], $k++) ?>
    </div>
    <div class="btnrow mt">
      <button type="button" class="btn btn--sec" data-zeile-folgetag title="Kopiert die letzte ausgefüllte Zeile auf den nächsten Tag – mit Fachgruppe und Fahrzeugen, ohne Kilometer und Betriebsstunden">+ Folgetag</button>
      <button type="button" class="btn btn--sec" data-zeile-kopie title="Kopiert die letzte ausgefüllte Zeile auf denselben Tag – für die nächste Fachgruppe">+ Weitere Fachgruppe am selben Tag</button>
      <button type="button" class="btn btn--sec" data-zeile-neu>+ Leere Zeile</button>
    </div>
    <small class="muted">Leere Zeilen werden beim Speichern übergangen. Ohne JavaScript: speichern und wieder öffnen bringt eine neue leere Zeile.</small>
  </section>

  <section class="card" id="fremde" data-zeilen>
    <h2>Fremde Kräfte</h2>
    <p class="small muted">Einheiten anderer Organisationen – Feuerwehr, Rettungsdienst, ein anderer Ortsverband. Je Tag und Einheit eine Zeile mit Zeit und Stärke.
      Sie zählen nicht in die Helferstunden<?= $symbole ? '; mit einem Zeichen steht die Einheit auf einer Karte dieses Einsatzes neben der Karte zur Auswahl und ist mit einem Klick gesetzt' : '' ?>.</p>
    <?php if ($symbole): ?>
      <template id="fremd-zeichen"><option value="">– kein Zeichen –</option><?php foreach (karten_symbole_gruppiert($symbole) as $gruppe => $liste): ?><optgroup label="<?= e((string)$gruppe !== '' ? (string)$gruppe : 'Ohne Gruppe') ?>"><?php foreach ($liste as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e((string)$s['name']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></template>
    <?php endif; ?>
    <div data-zeilen-body>
      <?php $fk = 0; foreach ($fremde as $f): ?><?= $fremdZeile($f, $fk++) ?><?php endforeach; ?>
      <?= $fremdZeile([], $fk++) ?>
    </div>
    <div class="btnrow mt">
      <button type="button" class="btn btn--sec" data-zeile-folgetag title="Kopiert die letzte ausgefüllte Zeile auf den nächsten Tag">+ Folgetag</button>
      <button type="button" class="btn btn--sec" data-zeile-neu>+ Leere Zeile</button>
    </div>
  </section>

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
    <?php if ($isNew): ?><p class="small muted" style="margin-bottom:0">Ausgaben, Einnahmen, Einsatzaufträge und Dokumente kommen nach dem Anlegen dazu – auf der Seite des Einsatzes.</p><?php endif; ?>
  </section>
</form>

<?php if (!$isNew && can('delete_einsaetze')): ?>
  <form method="post" class="card" action="<?= e(url('einsatz_edit', ['id' => $einsatz['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="loeschen">
    <button class="btn btn--danger" type="submit" data-confirm="Einsatz „<?= e(einsatz_bezeichnung($einsatz)) ?>“ mit allen Tagen und Dokumenten löschen?">Einsatz löschen</button>
    <span class="small muted" style="margin-left:.6rem">Tage, Fahrzeugzuordnung und Dokumente gehen mit.<?= $buchungen > 0
        ? ' ' . ($buchungen === 1 ? 'Die eine Buchung bleibt' : 'Die ' . (int)$buchungen . ' Buchungen bleiben') . ' im Budget stehen und verlieren nur den Bezug.'
        : '' ?></span>
  </form>
<?php endif; ?>
