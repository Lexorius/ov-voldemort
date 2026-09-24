<?php
/** @var array $event @var array $errors @var array $budgets @var array $fachgruppen
 *  @var array $connectoren @var array $ohneListe @var int $jahr */
$isNew = empty($event['id']);
$datum = substr((string)($event['beginn'] ?? ''), 0, 10);
$beginnZeit = substr((string)($event['beginn'] ?? ''), 11, 5);
$endeDatum = substr((string)($event['ende'] ?? ''), 0, 10);
$endeZeit = substr((string)($event['ende'] ?? ''), 11, 5);
?>
<div class="pagehead">
  <div><h1><?= $isNew ? 'Veranstaltung anlegen' : 'Veranstaltung bearbeiten' ?></h1></div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e($isNew ? url('events') : url('event', ['id' => $event['id']])) ?>">Abbrechen</a>
  </div>
</div>

<?php foreach ($errors as $f): ?>
  <div class="alert alert--error"><?= e($f) ?></div>
<?php endforeach; ?>

<form method="post" class="form">
  <?= csrf_field() ?>

  <section class="card">
    <h2>Termin</h2>
    <div class="field">
      <label for="titel">Titel</label>
      <input type="text" id="titel" name="titel" required maxlength="200"
             value="<?= e((string)($event['titel'] ?? '')) ?>" placeholder="z. B. Jahresabschlussfeier">
    </div>
    <div class="grid2">
      <div class="field">
        <label for="datum">Datum</label>
        <input type="date" id="datum" name="datum" required value="<?= e($datum) ?>">
      </div>
      <div class="field">
        <label for="beginn_zeit">Beginn</label>
        <input type="time" id="beginn_zeit" name="beginn_zeit" value="<?= e($beginnZeit) ?>">
      </div>
    </div>
    <div class="grid2">
      <div class="field">
        <label for="ende_datum">Ende (Datum)</label>
        <input type="date" id="ende_datum" name="ende_datum" value="<?= e($endeDatum) ?>">
        <small class="muted">Leer lassen, wenn die Veranstaltung am selben Tag endet.</small>
      </div>
      <div class="field">
        <label for="ende_zeit">Ende (Uhrzeit)</label>
        <input type="time" id="ende_zeit" name="ende_zeit" value="<?= e($endeZeit) ?>">
      </div>
    </div>
    <div class="field">
      <label for="ort">Ort</label>
      <input type="text" id="ort" name="ort" maxlength="200" value="<?= e((string)($event['ort'] ?? '')) ?>"
             placeholder="z. B. Unterkunft, Fahrzeughalle">
    </div>
    <div class="grid2">
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <?php foreach (EVENT_STATUS as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $key === (string)($event['status'] ?? 'geplant') ? 'selected' : '' ?>>
              <?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="typ_id">Art der Veranstaltung</label>
        <select id="typ_id" name="typ_id" data-neu="<?= $isNew ? '1' : '' ?>"
                data-ohne-liste="<?= e(implode(',', array_map(
            static fn($slug) => (string)(list_id_by_slug('veranstaltung_typ', $slug) ?? 0),
            $ohneListe))) ?>"><?= list_options('veranstaltung_typ', (int)($event['typ_id'] ?? 0)) ?></select>
      </div>
      <div class="field">
        <label for="fachgruppe_id">Fachgruppe</label>
        <select id="fachgruppe_id" name="fachgruppe_id">
          <option value="">– alle –</option>
          <?php foreach ($fachgruppen as $fg): ?>
            <option value="<?= (int)$fg['id'] ?>"
              <?= (int)$fg['id'] === (int)($event['fachgruppe_id'] ?? 0) ? 'selected' : '' ?>>
              <?= e((string)$fg['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="field">
      <label for="beschreibung">Beschreibung</label>
      <textarea id="beschreibung" name="beschreibung" rows="3"><?= e((string)($event['beschreibung'] ?? '')) ?></textarea>
    </div>
  </section>

  <section class="card">
    <h2>Geld</h2>
    <div class="grid2">
      <div class="field">
        <label for="budget_id">Budgettopf</label>
        <select id="budget_id" name="budget_id">
          <option value="">– keiner –</option>
          <?php foreach ($budgets as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= (int)$b['id'] === (int)($event['budget_id'] ?? 0) ? 'selected' : '' ?>>
              <?= e((string)$b['name']) ?> (<?= (int)$b['jahr'] ?>)</option>
          <?php endforeach; ?>
        </select>
        <small class="muted">Nur als Zuordnung. Gebucht wird weiterhin im Budgetmodul.</small>
      </div>
      <div class="field">
        <label for="kosten_geplant">Geplante Kosten</label>
        <input type="text" id="kosten_geplant" name="kosten_geplant" inputmode="decimal"
               value="<?= e(num_input((float)($event['kosten_geplant'] ?? 0))) ?>">
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Teilnehmer</h2>
    <div class="field field--check">
      <input type="checkbox" id="gaesteliste" name="gaesteliste" value="1"
             <?= (int)($event['gaesteliste'] ?? 1) === 1 ? 'checked' : '' ?>>
      <label for="gaesteliste">Gästeliste mit Einladungen führen</label>
    </div>
    <small class="muted" style="margin-top:-.6rem">Aus heißt: Es wird nur gezählt, wie viele
      gekommen sind – ohne Namen, ohne Einladungen. Für Ausbildungen und Übungen ist das meist
      genug. Welche Arten damit anfangen, steht in den Einstellungen.</small>
    <div class="grid2" id="zahlen-block">
      <div class="field">
        <label for="teilnehmer_geplant">Teilnehmer geplant</label>
        <input type="number" id="teilnehmer_geplant" name="teilnehmer_geplant" min="0" max="65000"
               value="<?= $event['teilnehmer_geplant'] !== null ? (int)$event['teilnehmer_geplant'] : '' ?>">
      </div>
      <div class="field">
        <label for="teilnehmer_ist">Teilnehmer tatsächlich</label>
        <input type="number" id="teilnehmer_ist" name="teilnehmer_ist" min="0" max="65000"
               value="<?= $event['teilnehmer_ist'] !== null ? (int)$event['teilnehmer_ist'] : '' ?>">
        <small class="muted">Nach der Veranstaltung nachtragen – geht auch direkt auf der
          Veranstaltungsseite.</small>
      </div>
    </div>
  </section>

  <section class="card" id="einladungen-block">
    <h2>Einladungen</h2>
    <p class="small">Jede eingeladene Person bekommt einen eigenen Code. Damit die Einladungsseite
      im Netz steht, braucht es einen Connector mit der Verwendung „Veranstaltungen".</p>
    <div class="grid2">
      <div class="field">
        <label for="connector_id">Connector</label>
        <select id="connector_id" name="connector_id">
          <option value="">– keiner, Rückmeldungen nur von Hand –</option>
          <?php foreach ($connectoren as $c): ?>
            <?php if ((int)$c['fuer_veranstaltungen'] !== 1) { continue; } ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)($event['connector_id'] ?? 0) ? 'selected' : '' ?>>
              <?= e((string)$c['name']) ?><?= connector_gekoppelt($c) ? '' : ' (nicht gekoppelt)' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="code_laenge">Länge des Einladungscodes</label>
        <input type="number" id="code_laenge" name="code_laenge" min="4" max="16"
               value="<?= (int)($event['code_laenge'] ?? 6) ?>">
        <small class="muted">Mehr Zeichen sind schwerer zu erraten, machen die Adresse aber länger.
          Codes bereits eingeladener Personen bleiben, wie sie sind.</small>
      </div>
    </div>
    <div class="grid2">
      <div class="field">
        <label for="begleiter_max">Mit wie vielen Begleitern darf jemand kommen</label>
        <input type="number" id="begleiter_max" name="begleiter_max" min="0" max="50"
               value="<?= (int)($event['begleiter_max'] ?? 0) ?>">
        <small class="muted">0 = niemand bringt jemanden mit. Auf der Einladungsseite steht dann
          „Mit wie vielen Begleitern kommen Sie?" nicht.</small>
      </div>
      <div class="field">
        <label for="rueckmeldung_bis">Rückmeldung bis</label>
        <input type="date" id="rueckmeldung_bis" name="rueckmeldung_bis"
               value="<?= e((string)($event['rueckmeldung_bis'] ?? '')) ?>">
        <small class="muted">Danach nimmt die Einladungsseite nichts mehr an.</small>
      </div>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="kommentare_erlaubt" name="kommentare_erlaubt" value="1"
             <?= (int)($event['kommentare_erlaubt'] ?? 1) === 1 ? 'checked' : '' ?>>
      <label for="kommentare_erlaubt">Gäste dürfen einen Kommentar mitschicken</label>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="vertretung_erlaubt" name="vertretung_erlaubt" value="1"
             <?= (int)($event['vertretung_erlaubt'] ?? 1) === 1 ? 'checked' : '' ?>>
      <label for="vertretung_erlaubt">Eine Vertretung darf genannt werden</label>
    </div>
    <div class="field">
      <label for="hinweis">Text auf der Einladungsseite</label>
      <textarea id="hinweis" name="hinweis" rows="3"
                placeholder="z. B. Beginn 18 Uhr, Einlass ab 17:30. Bitte bis zum 1. Dezember zurückmelden."><?= e((string)($event['hinweis'] ?? '')) ?></textarea>
      <small class="muted">Steht unter Titel, Datum und Ort – diese drei Angaben kennt der
        Connector ohnehin, weil die Seite sie zeigen muss. Namen der Eingeladenen stehen dort nie.</small>
    </div>
  </section>

  <section class="card">
    <h2>Sonstiges</h2>
    <div class="field">
      <label for="notiz">Interne Notiz</label>
      <textarea id="notiz" name="notiz" rows="2"><?= e((string)($event['notiz'] ?? '')) ?></textarea>
    </div>
    <div class="btnrow">
      <button class="btn" type="submit">Speichern</button>
      <a class="btn btn--sec" href="<?= e($isNew ? url('events') : url('event', ['id' => $event['id']])) ?>">Abbrechen</a>
    </div>
  </section>
</form>

<?php if (!$isNew): ?>
  <section class="card">
    <h2>Veranstaltung löschen</h2>
    <p class="small">Gästeliste, Rückmeldungen und Dateien verschwinden mit. Erfasste Buchungen
      bleiben im Budget stehen und verlieren nur den Bezug.</p>
    <form method="post" action="<?= e(url('event_action')) ?>" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="loeschen">
      <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
      <button class="btn btn--danger" type="submit"
              data-confirm="Diese Veranstaltung mit Gästeliste, Rückmeldungen und Dateien löschen?"
              data-confirm2="Bist du wirklich sicher?">Veranstaltung löschen</button>
    </form>
  </section>
<?php endif; ?>
