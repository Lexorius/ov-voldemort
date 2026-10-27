<?php
/** @var array $meeting @var array $errors */
$isNew = empty($meeting['id']);
$uhr = static fn($v) => $v ? substr((string)$v, 0, 5) : '';
?>
<div class="pagehead">
  <h1><?= $isNew ? 'Besprechung anlegen' : 'Besprechung bearbeiten' ?></h1>
  <a class="btn btn--sec" href="<?= $isNew ? e(url('meetings')) : e(url('meeting', ['id' => $meeting['id']])) ?>">Abbrechen</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card form" action="<?= e(url('meeting_edit', $isNew ? [] : ['id' => $meeting['id']])) ?>">
  <?= csrf_field() ?>
  <div class="grid2">
    <div class="field">
      <label for="titel">Titel *</label>
      <input type="text" id="titel" name="titel" required maxlength="200" value="<?= e((string)$meeting['titel']) ?>"
             placeholder="z.B. OV-Stab September">
    </div>
    <div class="field">
      <label for="typ_id">Art</label>
      <select id="typ_id" name="typ_id"><?= list_options('besprechung_typ', (int)($meeting['typ_id'] ?? 0)) ?></select>
    </div>
  </div>
  <div class="grid3">
    <div class="field">
      <label for="datum">Datum *</label>
      <input type="date" id="datum" name="datum" required value="<?= e((string)$meeting['datum']) ?>">
    </div>
    <div class="field">
      <label for="beginn">Beginn</label>
      <input type="time" id="beginn" name="beginn" value="<?= e($uhr($meeting['beginn'] ?? null)) ?>">
    </div>
    <div class="field">
      <label for="ende">Ende (geplant)</label>
      <input type="time" id="ende" name="ende" value="<?= e($uhr($meeting['ende'] ?? null)) ?>">
    </div>
    <div class="field">
      <label for="ende_datum">Ende am (bei mehreren Tagen)</label>
      <input type="date" id="ende_datum" name="ende_datum" value="<?= e((string)($meeting['ende_datum'] ?? '')) ?>">
      <small class="muted">Leer = gleicher Tag. Für Klausur, Lehrgang oder Wochenende.</small>
    </div>
    <div class="field">
      <label for="treffen">Wo</label>
      <?php $treffen = meeting_treffen((string)($meeting['treffen'] ?? 'inhaus')); ?>
      <select id="treffen" name="treffen">
        <?php foreach (MEETING_TREFFEN as $k => $t): ?>
          <option value="<?= e($k) ?>"<?= $treffen === $k ? ' selected' : '' ?>><?= e($t['icon'] . ' ' . $t['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="muted">Online zeigt die Besprechung einen Knopf „Jetzt beitreten", außer Haus einen Navi-Link zur Anschrift.</small>
    </div>
    <div class="field">
      <label for="ort">Ort <span class="muted small">– außer Haus: Anschrift mit Straße, PLZ und Ort (fürs Navi)</span></label>
      <input type="text" id="ort" name="ort" value="<?= e((string)$meeting['ort']) ?>" placeholder="z.B. Unterkunft, Besprechungsraum – oder Musterstraße 1, 74072 Heilbronn">
    </div>
    <div class="field">
      <label for="link">Link zur Online-Besprechung <span class="muted small">(Webex, Teams, Jitsi …)</span></label>
      <input type="url" id="link" name="link" maxlength="500" value="<?= e((string)($meeting['link'] ?? '')) ?>" placeholder="https://…">
    </div>
    <?php $fahrzeuge ??= []; $fahrzeugeGewaehlt ??= []; $belegt ??= []; if ($fahrzeuge): ?>
      <?php $fzOptionen = []; $bloecke = kalender_fahrzeuge_bloecke($fahrzeuge);
      foreach (['fahrzeuge' => 'Autos und LKW', 'anhaenger' => 'Anhänger', 'geraete' => 'Geräte'] as $key => $gruppe) {
          foreach ($bloecke[$key] ?? [] as $v) {
              $andere = $belegt[(int)$v['id']] ?? [];
              $fzOptionen[] = ['id' => (int)$v['id'], 'text' => (string)$v['bezeichnung'], 'sub' => implode(' · ', array_filter([(string)($v['funkrufname'] ?? ''), function_exists('vehicle_kapazitaet_text') ? vehicle_kapazitaet_text($v) : ''])), 'gruppe' => $gruppe,
                               'warn' => $andere ? 'schon verplant: ' . implode(' und ', array_map(static fn($t) => '„' . $t['titel'] . '" (' . kalender_wann($t) . ')', $andere)) : ''];
          }
      } ?>
      <?= render_partial('partials/wahl_liste', ['name' => 'vehicles', 'feld' => 'fahrzeuge_feld', 'label' => 'Fahrzeuge', 'optionen' => $fzOptionen, 'gewaehlt' => $fahrzeugeGewaehlt, 'leer' => '– Fahrzeug wählen –',
          'hinweis' => 'Welche Fahrzeuge die Besprechung braucht, etwa für die Anfahrt zu einer Klausur – Fahrzeugakte und Startseite zeigen sie dann als verplant. Ein ⚠ heißt: zu der Zeit schon anderswo eingeplant; hinzufügen geht trotzdem, dann warnt die Seite.']) ?>
    <?php endif; ?>
    <div class="field">
      <label for="leitung">Leitung</label>
      <input type="text" id="leitung" name="leitung" value="<?= e((string)$meeting['leitung']) ?>">
    </div>
    <div class="field">
      <label for="protokoll_von">Protokoll</label>
      <input type="text" id="protokoll_von" name="protokoll_von" value="<?= e((string)$meeting['protokoll_von']) ?>">
    </div>
  </div>
  <div class="field">
    <label for="teilnehmer">Eingeladen / Teilnehmende</label>
    <textarea id="teilnehmer" name="teilnehmer" rows="3"><?= e((string)$meeting['teilnehmer']) ?></textarea>
  </div>
  <?php if (!empty($mitKontakten)): ?>
    <?= render_partial('partials/wahl_liste', ['name' => 'kontakte', 'feld' => 'kontakte_feld', 'label' => 'Externe Teilnehmer (aus den Kontakten)', 'optionen' => $kontaktOptionen ?? [], 'gewaehlt' => $kontakteGewaehlt ?? [], 'leer' => '– Kontakt wählen –',
        'hinweis' => 'Tippen zum Suchen, dann „+". Die Gewählten stehen auf der Anwesenheitsliste der Besprechung – dort lassen sich auch Mitglieder, ganze Verteiler und freie Namen aufnehmen und Status setzen.']) ?>
  <?php endif; ?>
  <div class="field">
    <label for="beschreibung">Hinweise zur Besprechung</label>
    <textarea id="beschreibung" name="beschreibung"><?= e((string)$meeting['beschreibung']) ?></textarea>
  </div>
  <?php if (!empty($mitKontakten)): ?>
    <h3 style="margin:1rem 0 .3rem">Protokoll per E-Mail</h3>
    <p class="small muted" style="margin:0 0 .6rem">Wer das Protokoll als PDF bekommt – einzelne Kontakte mit E-Mail-Adresse oder ganze Verteiler aus dem
      Kontaktmodul. Verschickt wird auf der Seite der Besprechung, nach dem Abschließen.</p>
    <?= render_partial('partials/wahl_liste', ['name' => 'protokoll_kontakte', 'feld' => 'protokoll_kontakte_feld', 'label' => 'Empfänger (Kontakte)', 'optionen' => $protokollOptionen ?? [], 'gewaehlt' => $protokollKontakte ?? [], 'leer' => '– Kontakt wählen –',
        'hinweis' => 'Nur Kontakte mit E-Mail-Adresse.']) ?>
    <?php if (!empty($verteilerOptionen)): ?>
      <?= render_partial('partials/wahl_liste', ['name' => 'protokoll_verteiler', 'feld' => 'protokoll_verteiler_feld', 'label' => 'Empfänger (Verteiler)', 'optionen' => $verteilerOptionen, 'gewaehlt' => $protokollVerteiler ?? [], 'leer' => '– Verteiler wählen –',
          'hinweis' => 'Alle Mitglieder des Verteilers mit E-Mail-Adresse – auch wer später dazukommt.']) ?>
    <?php else: ?>
      <input type="hidden" name="protokoll_verteiler_feld" value="1">
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($isNew): ?>
    <p class="small muted mb0">Ort, Leitung und Uhrzeit werden von der letzten Besprechung dieser Art übernommen, sofern es eine gibt.</p>
  <?php endif; ?>
  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Anlegen' : 'Speichern' ?></button>
  </div>
</form>

<?php if (!$isNew && !empty($meeting['series_id'])): ?>
  <div class="card">
    <p class="small mb0">Dieser Termin gehört zur Serie
      <a href="<?= e(url('meeting_series_edit', ['id' => $meeting['series_id']])) ?>"><?= e((string)($meeting['serie_titel'] ?? 'Serie')) ?></a>.
      Datum und Uhrzeit lassen sich hier für diesen einen Termin ändern, ohne die Serie anzufassen.
      Fällt er aus, bitte in der Besprechung „Termin absagen" wählen.</p>
  </div>
<?php elseif (!$isNew): ?>
  <form method="post" class="card" action="<?= e(url('meeting_edit', ['id' => $meeting['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <p class="small muted">Die Themen der Besprechung gehen nicht verloren – sie wandern zurück in den Themenspeicher.</p>
    <button class="btn btn--danger" type="submit" data-confirm="Besprechung löschen?">Besprechung löschen</button>
  </form>
<?php endif; ?>
