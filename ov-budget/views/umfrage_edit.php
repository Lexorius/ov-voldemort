<?php
/** @var array $umfrage @var array $errors @var array $connectoren @var int $antwortenAnzahl */
$isNew = empty($umfrage['id']);
$lokal = static fn(?string $v): string => trim((string)$v) === '' ? '' : date('Y-m-d\TH:i', (int)strtotime((string)$v));
$seitenJson = is_string($umfrage['seiten'] ?? null) ? (string)$umfrage['seiten'] : json_encode($umfrage['seiten'] ?? [], JSON_UNESCAPED_UNICODE);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Abstimmung anlegen' : 'Abstimmung bearbeiten' ?></h1>
    <p>Erst die Eckdaten, dann die Seiten mit den Fragen. Freigegeben wird auf der Seite der Abstimmung –
       dann steht sie beim Connector und nimmt im Zeitfenster Antworten an.</p>
  </div>
  <div class="btnrow"><a class="btn btn--sec" href="<?= e($isNew ? url('umfragen') : url('umfrage', ['id' => $umfrage['id']])) ?>">Abbrechen</a></div>
</div>

<?php foreach ($errors as $f): ?>
  <div class="alert alert--error"><?= e($f) ?></div>
<?php endforeach; ?>
<?php if (!$isNew && $antwortenAnzahl > 0): ?>
  <div class="alert alert--warn">Es liegen schon <?= $antwortenAnzahl ?> Antwort(en) vor. Wer Fragen ändert oder entfernt,
    macht die Auswertung der alten Antworten schief – besser eine neue Abstimmung anlegen.</div>
<?php endif; ?>
<?php if (!$connectoren): ?>
  <div class="alert alert--info">Noch kein Connector für Abstimmungen – gestalten geht trotzdem, freigeben erst mit einem
    Connector mit der Verwendung „Abstimmungen" (<a href="<?= e(url('admin_connectors')) ?>">Verwaltung</a>).</div>
<?php endif; ?>

<form method="post" class="form" id="umfrage-formular">
  <?= csrf_field() ?>
  <section class="card">
    <div class="card__head"><h2>Eckdaten</h2></div>
    <div class="grid2">
      <div class="field" style="grid-column:1/-1">
        <label for="titel">Titel</label>
        <input type="text" id="titel" name="titel" required maxlength="200" value="<?= e((string)$umfrage['titel']) ?>"
               placeholder="z. B. Sommerfest 2027 – Termin und Verpflegung">
      </div>
      <div class="field" style="grid-column:1/-1">
        <label for="beschreibung">Beschreibung <span class="muted">(steht über der Umfrage)</span></label>
        <textarea id="beschreibung" name="beschreibung" rows="3" maxlength="2000"><?= e((string)$umfrage['beschreibung']) ?></textarea>
      </div>
      <div class="field">
        <label for="beginn">Beginn <span class="muted">(leer = sofort nach Freigabe)</span></label>
        <input type="datetime-local" id="beginn" name="beginn" value="<?= e($lokal($umfrage['beginn'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="ende">Ende <span class="muted">(Pflicht – danach nimmt die Seite nichts mehr an)</span></label>
        <input type="datetime-local" id="ende" name="ende" required value="<?= e($lokal($umfrage['ende'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="connector_id">Connector</label>
        <select id="connector_id" name="connector_id">
          <option value="">– keiner –</option>
          <?php foreach ($connectoren as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)($umfrage['connector_id'] ?? 0) ? 'selected' : '' ?>>
              <?= e((string)$c['name']) ?><?= connector_gekoppelt($c) ? '' : ' (nicht gekoppelt)' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="dank">Dankestext nach dem Absenden</label>
        <input type="text" id="dank" name="dank" maxlength="500" value="<?= e((string)$umfrage['dank']) ?>" placeholder="Danke, deine Antwort ist angekommen.">
      </div>
      <div class="field field--check" style="grid-column:1/-1">
        <input type="checkbox" id="name_feld" name="name_feld" value="1" <?= (int)($umfrage['name_feld'] ?? 0) === 1 ? 'checked' : '' ?>>
        <label for="name_feld">Am Ende freiwillig nach dem Namen fragen <span class="muted">(aus = die Abstimmung ist anonym)</span></label>
      </div>
    </div>
  </section>

  <section class="card">
    <div class="card__head">
      <h2>Ergebnis per E-Mail</h2>
      <span class="small muted">Zwischenstand oder Ergebnis an einen Kontakt, automatisch.</span>
    </div>
    <?php if (!$mailAktiv): ?>
      <div class="alert alert--info">Der E-Mail-Versand ist nicht eingerichtet – Berichte bleiben liegen, bis er in der
        <a href="<?= e(url('admin_mail')) ?>">Verwaltung</a> eingeschaltet ist. Einstellen lässt er sich schon jetzt.</div>
    <?php endif; ?>
    <div class="grid2">
      <div class="field">
        <label for="bericht_rhythmus">Bericht</label>
        <select id="bericht_rhythmus" name="bericht_rhythmus">
          <?php foreach (UMFRAGE_BERICHT as $wert => $text): ?>
            <option value="<?= e($wert) ?>"<?= (string)($umfrage['bericht_rhythmus'] ?? '') === $wert ? ' selected' : '' ?>><?= e($text) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="muted">Täglich und wöchentlich ab der Uhrzeit aus den Einstellungen; zum Abschluss immer, sobald die letzten
          Antworten abgeholt sind.</small>
      </div>
      <?php if (!$kontakte): ?>
        <div class="field">
          <label>Empfänger</label>
          <small class="muted">Noch kein Kontakt mit E-Mail-Adresse – im Modul Kontakte anlegen.</small>
        </div>
      <?php else: ?>
        <?= render_partial('partials/wahl_liste', [
            'name'     => 'bericht_kontakte',
            'feld'     => 'bericht_kontakte_feld',
            'label'    => 'Empfänger',
            'optionen' => array_map(static fn(array $k) => ['id' => (int)$k['id'], 'text' => contact_name($k), 'sub' => (string)$k['email'],
                              'gruppe' => (string)($k['kategorie_label'] ?? '')], $kontakte),
            'gewaehlt' => array_map('intval', (array)($umfrage['bericht_kontakte'] ?? [])),
            'leer'     => '– Kontakt wählen –',
            'hinweis'  => 'Jeder Empfänger bekommt eine eigene Nachricht.',
        ]) ?>
      <?php endif; ?>
      <div class="field field--check" style="grid-column:1/-1">
        <input type="checkbox" id="bericht_ki" name="bericht_ki" value="1" <?= (int)($umfrage['bericht_ki'] ?? 0) === 1 ? 'checked' : '' ?>>
        <label for="bericht_ki">KI fasst die Ergebnisse zusammen
          <span class="muted"><?= $kiAktiv ? '(Ollama im eigenen Netz, ohne Gewähr – fällt die KI aus, geht der Bericht ohne Zusammenfassung)' : '(die KI-Auswertung ist gerade ausgeschaltet; die Zusammenfassung kommt, sobald sie läuft)' ?></span></label>
      </div>
    </div>
  </section>

  <section class="card">
    <div class="card__head">
      <h2>Seiten und Fragen</h2>
      <span class="small muted">Jede Seite wird auf dem Handy einzeln gezeigt, mit „Weiter" und „Zurück".</span>
    </div>
    <div data-umfrage-editor data-typen="<?= e(json_encode(UMFRAGE_FELDTYPEN, JSON_UNESCAPED_UNICODE)) ?>"></div>
    <noscript><p class="small">Ohne JavaScript lassen sich die Seiten als JSON im Feld darunter bearbeiten.</p></noscript>
    <div class="field">
      <label for="seiten_json" class="small muted">Seiten (JSON – wird vom Gestalter gefüllt)</label>
      <textarea id="seiten_json" name="seiten_json" rows="6" class="mono small"><?= e($seitenJson) ?></textarea>
    </div>
  </section>

  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Anlegen' : 'Speichern' ?></button>
    <a class="btn btn--sec" href="<?= e($isNew ? url('umfragen') : url('umfrage', ['id' => $umfrage['id']])) ?>">Abbrechen</a>
  </div>
</form>
