<?php
/** @var array $karte @var array $errors @var array $fahrzeugOptionen @var array $fahrzeugeGewaehlt @var array $personen @var ?int $vehicleId */
$isNew = empty($karte['id']);
$art = tankkarte_art((string)($karte['art'] ?? 'kraftstoff'));
$zurueck = $vehicleId ? url('vehicle', ['id' => $vehicleId]) . '#tankkarten' : url('tankkarten');
?>
<div class="pagehead">
  <div><h1><?= $isNew ? 'Tankkarte anlegen' : 'Tankkarte bearbeiten' ?></h1></div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e($zurueck) ?>">Abbrechen</a>
  </div>
</div>

<?php foreach ($errors as $f): ?>
  <div class="alert alert--error"><?= e($f) ?></div>
<?php endforeach; ?>

<form method="post" class="form">
  <?= csrf_field() ?>

  <section class="card">
    <h2>Karte</h2>
    <div class="grid2">
      <div class="field">
        <label for="anbieter_id">Anbieter</label>
        <select id="anbieter_id" name="anbieter_id"><?= list_options('tankkarte_anbieter', (int)($karte['anbieter_id'] ?? 0)) ?></select>
        <small class="muted">DKV, Shell, Aral, ein Ladenetz … – weitere unter Verwaltung → Auswahllisten.</small>
      </div>
      <div class="field">
        <label for="nummer">Kartennummer</label>
        <input type="text" id="nummer" name="nummer" required maxlength="60" class="mono" value="<?= e((string)($karte['nummer'] ?? '')) ?>" placeholder="steht auf der Karte">
      </div>
      <div class="field">
        <label for="art">Schaltet frei</label>
        <select id="art" name="art">
          <?php foreach (TANKKARTE_ARTEN as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $key === $art ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="muted">Ladekarten für E-Fahrzeuge werden hier genauso geführt wie Tankkarten.</small>
      </div>
      <div class="field">
        <label for="status_id">Status</label>
        <select id="status_id" name="status_id"><?= list_options('tankkarte_status', (int)($karte['status_id'] ?? 0)) ?></select>
      </div>
      <div class="field">
        <label for="kundennummer">Kundennummer <span class="muted">(freiwillig)</span></label>
        <input type="text" id="kundennummer" name="kundennummer" maxlength="60" class="mono" value="<?= e((string)($karte['kundennummer'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="gueltig_bis">Gültig bis</label>
        <input type="date" id="gueltig_bis" name="gueltig_bis" value="<?= e((string)($karte['gueltig_bis'] ?? '')) ?>">
        <small class="muted">Leer heißt unbefristet.</small>
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Zuordnung</h2>
    <?= render_partial('partials/wahl_liste', ['name' => 'fahrzeuge', 'feld' => 'fahrzeuge_feld', 'label' => 'Fahrzeuge', 'optionen' => $fahrzeugOptionen,
        'gewaehlt' => $fahrzeugeGewaehlt, 'leer' => '– Fahrzeug wählen –',
        'hinweis' => 'Eine Karte für mehrere Fahrzeuge oder mehrere Karten für eines – beides geht. Ohne Fahrzeug liegt die Karte im Schrank.']) ?>
    <?php if (!$fahrzeugOptionen): ?><input type="hidden" name="fahrzeuge_feld" value="1"><p class="small muted">Noch keine Fahrzeuge angelegt.</p><?php endif; ?>
    <div class="grid2">
      <div class="field">
        <label for="person_id">Liegt bei <span class="muted">(Person)</span></label>
        <select id="person_id" name="person_id">
          <option value="">– niemandem fest –</option>
          <?php foreach ($personen as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === (int)($karte['person_id'] ?? 0) ? 'selected' : '' ?>><?= e((string)$u['display_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="aufbewahrung">Aufbewahrung <span class="muted">(Freitext)</span></label>
        <input type="text" id="aufbewahrung" name="aufbewahrung" maxlength="150" value="<?= e((string)($karte['aufbewahrung'] ?? '')) ?>" placeholder="z. B. Handschuhfach GKW 1, Schlüsselkasten Büro">
      </div>
      <div class="field">
        <label for="ausgegeben_am">Ausgegeben am</label>
        <input type="date" id="ausgegeben_am" name="ausgegeben_am" value="<?= e((string)($karte['ausgegeben_am'] ?? '')) ?>">
      </div>
    </div>
  </section>

  <section class="card">
    <h2>PIN</h2>
    <div class="field field--check">
      <input type="checkbox" id="hat_pin" name="hat_pin" value="1" data-schalter="pin" <?= tankkarte_hat_pin($karte) ? 'checked' : '' ?>>
      <label for="hat_pin">PIN hier hinterlegen</label>
    </div>
    <p class="small">Sie steht dann nur der Leitung offen und wird in der Liste verdeckt angezeigt. Ohne Haken bleibt das Feld
      leer – und was schon darin stand, wird beim Speichern entfernt.</p>
    <div class="grid2" data-schalter-block="pin"<?= tankkarte_hat_pin($karte) ? '' : ' hidden' ?>>
      <div class="field">
        <label for="pin">PIN</label>
        <input type="text" id="pin" name="pin" maxlength="20" autocomplete="off" value="<?= e((string)($karte['pin'] ?? '')) ?>">
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Sonstiges</h2>
    <div class="field">
      <label for="notiz">Notiz</label>
      <textarea id="notiz" name="notiz" rows="2"><?= e((string)($karte['notiz'] ?? '')) ?></textarea>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="is_active" name="is_active" value="1" <?= (int)($karte['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
      <label for="is_active">Im Bestand</label>
    </div>
    <small class="muted" style="margin-top:-.6rem">Aus heißt: gesperrt, zurückgegeben oder abgelaufen – die Karte bleibt zum Nachschlagen erhalten.</small>
    <div class="btnrow">
      <button class="btn" type="submit">Speichern</button>
      <a class="btn btn--sec" href="<?= e($zurueck) ?>">Abbrechen</a>
    </div>
  </section>
</form>

<?php if (!$isNew): ?>
  <section class="card">
    <h2>Tankkarte löschen</h2>
    <p class="small">Nur, wenn die Karte nie existiert hat. Sonst besser aus dem Bestand nehmen – dann bleibt nachvollziehbar,
      dass es sie gab. Tankungen, die mit der Karte erfasst wurden, bleiben erhalten und verlieren nur den Verweis.</p>
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="loeschen">
      <button class="btn btn--danger" type="submit" data-confirm="Diese Tankkarte endgültig löschen?" data-confirm2="Bist du wirklich sicher?">Löschen</button>
    </form>
  </section>
<?php endif; ?>
