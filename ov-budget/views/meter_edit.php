<?php
/** @var array $meter @var array $errors @var array $entitaeten @var string $haHinweis */
$isNew = empty($meter['id']);
$quelle = (string)($meter['quelle'] ?? 'manuell');
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Zähler anlegen' : 'Zähler bearbeiten' ?></h1>
    <p>Ein Zähler wird entweder aus Home Assistant gelesen oder von Hand abgelesen –
       am Bildschirm oder per QR-Code am Zähler.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e($isNew ? url('verbrauch') : url('meter', ['id' => $meter['id']])) ?>">Abbrechen</a>
  </div>
</div>

<?php foreach ($errors as $f): ?>
  <div class="alert alert--error"><?= e($f) ?></div>
<?php endforeach; ?>

<form method="post" class="form">
  <?= csrf_field() ?>

  <section class="card">
    <h2>Zähler</h2>
    <div class="grid2">
      <div class="field">
        <label for="art">Art</label>
        <select id="art" name="art">
          <?php foreach (METER_ARTEN as $key => $a): ?>
            <option value="<?= e($key) ?>"<?= (string)$meter['art'] === $key ? ' selected' : '' ?>><?= e($a['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required maxlength="150" value="<?= e((string)$meter['name']) ?>"
               placeholder="z. B. Strom Unterkunft">
      </div>
      <div class="field">
        <label for="rolle">Was misst der Zähler?</label>
        <select id="rolle" name="rolle" data-rolle>
          <?php foreach (METER_ROLLEN as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= (string)($meter['rolle'] ?? 'bezug') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <small>Unterzähler und Solarzählungen tragen keine eigenen Kosten und zählen nicht doppelt.
          Erzeugung und Einspeisung gibt es nur bei Strom.</small>
      </div>
      <div class="field" data-rolle-block="unter"<?= (string)($meter['rolle'] ?? 'bezug') === 'unter' ? '' : ' hidden' ?>>
        <label for="parent_id">Hauptzähler</label>
        <select id="parent_id" name="parent_id">
          <option value="">– bitte wählen –</option>
          <?php foreach ($hauptzaehler as $h): ?>
            <option value="<?= (int)$h['id'] ?>" data-art="<?= e((string)$h['art']) ?>"<?= (int)$h['id'] === (int)($meter['parent_id'] ?? 0) ? ' selected' : '' ?>>
              <?= e((string)$h['name']) ?> (<?= e(METER_ARTEN[(string)$h['art']]['label']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <small>Der Zähler, dessen Teil hier gemessen wird – gleiche Art.</small>
      </div>
      <div class="field">
        <label for="zaehlernummer">Zählernummer</label>
        <input type="text" id="zaehlernummer" name="zaehlernummer" maxlength="80" value="<?= e((string)$meter['zaehlernummer']) ?>">
      </div>
      <?php $standorte ??= []; $aktive = array_values(array_filter($standorte, static fn($x) => (int)$x['is_active'] === 1 || in_array((int)$x['id'], [(int)($meter['standort_id'] ?? 0), (int)($meter['bereich_id'] ?? 0)], true))); ?>
      <div class="field">
        <label for="bereich_id">Zuständig für</label>
        <?php if ($aktive): ?>
          <select id="bereich_id" name="bereich_id"><?= standort_optionen($aktive, (int)($meter['bereich_id'] ?? 0), '– kein Bereich –') ?></select>
          <small>Der Bereich, dessen Verbrauch der Zähler misst: das ganze Haus, ein Stockwerk, ein Raum. Gilt auch für alles darunter.</small>
        <?php else: ?>
          <select id="bereich_id" name="bereich_id" disabled><option>– noch keine Plätze angelegt –</option></select>
          <small>Plätze legt die Leitung unter Verwaltung → Stell- und Lagerplätze an.</small>
        <?php endif; ?>
      </div>
      <div class="field">
        <label for="standort_id">Zählerplatz (eingebaut in)</label>
        <?php if ($aktive): ?>
          <select id="standort_id" name="standort_id"><?= standort_optionen($aktive, (int)($meter['standort_id'] ?? 0), '– kein Platz –') ?></select>
          <small>Wo der Zähler hängt, z. B. der Hausanschlussraum im Keller.</small>
        <?php else: ?>
          <select id="standort_id" name="standort_id" disabled><option>– noch keine Plätze angelegt –</option></select>
        <?php endif; ?>
      </div>
      <div class="field">
        <label for="standort">Standort (frei)</label>
        <input type="text" id="standort" name="standort" maxlength="150" value="<?= e((string)$meter['standort']) ?>"
               placeholder="z. B. hinter der Tür links">
      </div>
      <div class="field">
        <label for="einheit">Einheit des Zählers</label>
        <input type="text" id="einheit" name="einheit" maxlength="10" value="<?= e((string)$meter['einheit']) ?>" placeholder="kWh oder m³">
        <small>Strom meist kWh, Gas und Wasser m³.</small>
      </div>
      <div class="field">
        <label for="umrechnung">Umrechnung für den Tarif</label>
        <input type="text" inputmode="decimal" id="umrechnung" name="umrechnung" value="<?= e(num_input($meter['umrechnung'] ?? 1, true)) ?>">
        <small>Nur wenn der Tarif in einer anderen Einheit rechnet als der Zähler: Bei Gas
          m³ → kWh ist das Brennwert × Zustandszahl, meist rund 10. Sonst 1.</small>
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Quelle</h2>
    <div class="field">
      <label class="wahl"><input type="radio" name="quelle" value="manuell" data-schalter-radio="quelle"
             <?= $quelle === 'manuell' ? 'checked' : '' ?>> Von Hand ablesen – am Bildschirm oder per QR-Code am Zähler</label>
      <label class="wahl"><input type="radio" name="quelle" value="ha" data-schalter-radio="quelle"
             <?= $quelle === 'ha' ? 'checked' : '' ?>> Aus Home Assistant lesen</label>
    </div>
    <div data-quelle-block="ha"<?= $quelle === 'ha' ? '' : ' hidden' ?>>
      <div class="field">
        <label for="ha_entity">Entität</label>
        <?php if ($entitaeten): ?>
          <select id="ha_entity_wahl" data-ziel="ha_entity">
            <option value="">– aus Home Assistant wählen –</option>
            <?php foreach ($entitaeten as $eid => $en): ?>
              <option value="<?= e($eid) ?>"<?= (string)$meter['ha_entity'] === $eid ? ' selected' : '' ?>>
                <?= e($en['name']) ?> (<?= e($eid) ?><?= $en['einheit'] !== '' ? ', ' . e($en['einheit']) : '' ?>)</option>
            <?php endforeach; ?>
          </select>
          <small><?= count($entitaeten) ?> passende Sensoren gefunden –
            <a href="<?= e(url('meter_edit', array_filter(['id' => $meter['id'], 'frisch' => 1]))) ?>">neu laden</a>.
            Fehlt einer, die Kennung unten von Hand eintragen.</small>
        <?php elseif ($haHinweis !== ''): ?>
          <small style="color:var(--warn)">Keine Liste aus Home Assistant: <?= e($haHinweis) ?> – die Kennung bitte von Hand eintragen.</small>
        <?php else: ?>
          <small>Kein passender Sensor gefunden (Energie, Gas, Wasser). Die Kennung lässt sich von Hand eintragen.</small>
        <?php endif; ?>
        <input type="text" id="ha_entity" name="ha_entity" class="mono mt" maxlength="200"
               value="<?= e((string)$meter['ha_entity']) ?>" placeholder="sensor.strom_gesamt">
      </div>
      <div class="field">
        <label for="ha_faktor">Faktor</label>
        <input type="text" inputmode="decimal" id="ha_faktor" name="ha_faktor" value="<?= e(num_input($meter['ha_faktor'] ?? 1, true)) ?>">
        <small>Wert der Entität × Faktor = Zählerstand. Liefert der Sensor Wh statt kWh: 0,001. Sonst 1.</small>
      </div>
      <p class="small muted">Gelesen wird alle <?= (int)max(5, setting_int('verbrauch_ha_intervall_minuten', 60)) ?> Minuten
        mit dem Abruf, einstellbar unter Verwaltung → Einstellungen → Verbrauch. Der Sensor muss einen
        Zählerstand liefern (steigend), keinen Momentanwert.</p>
    </div>
  </section>

  <section class="card">
    <h2>Sonstiges</h2>
    <div class="field">
      <label for="notiz">Notiz</label>
      <textarea id="notiz" name="notiz" rows="2"><?= e((string)($meter['notiz'] ?? '')) ?></textarea>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="is_active" name="is_active" value="1" <?= (int)($meter['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
      <label for="is_active">In Betrieb</label>
    </div>
    <div class="btnrow">
      <button class="btn" type="submit">Speichern</button>
      <a class="btn btn--sec" href="<?= e($isNew ? url('verbrauch') : url('meter', ['id' => $meter['id']])) ?>">Abbrechen</a>
    </div>
  </section>
</form>

<?php if (!$isNew): ?>
  <section class="card">
    <h2>Zähler löschen</h2>
    <p class="small">Löscht den Zähler mit allen Ständen. Ein stillgelegter Zähler behält seine Geschichte –
      dafür oben „In Betrieb" abwählen.</p>
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="loeschen">
      <button class="btn btn--danger" type="submit" data-confirm="Diesen Zähler mit allen Ständen löschen?"
              data-confirm2="Bist du wirklich sicher?">Löschen</button>
    </form>
  </section>
<?php endif; ?>
