<?php
/**
 * @var array $n @var array $errors @var array $connectoren @var array $kontaktOptionen @var array $kontakteGewaehlt
 * @var array $verteilerOptionen @var array $verteilerGewaehlt @var int $maxMb
 */
$isNew = empty($n['id']);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Newsletter anlegen' : 'Newsletter bearbeiten' ?></h1>
    <p>Das fertige PDF hochladen, Betreff und Begleittext schreiben, die Empfänger wählen. Verschickt wird auf der Seite des
       Newsletters – sofort oder zu einem Zeitpunkt.</p>
  </div>
  <div class="btnrow"><a class="btn btn--sec" href="<?= e($isNew ? url('newsletter') : url('newsletter_detail', ['id' => $n['id']])) ?>">Abbrechen</a></div>
</div>

<?php foreach ($errors as $f): ?><div class="alert alert--error"><?= e($f) ?></div><?php endforeach; ?>
<?php if (!$isNew && (string)$n['status'] === 'geplant'): ?>
  <div class="alert alert--info">Dieser Newsletter ist eingeplant. Speichern setzt die Planung zurück – danach bitte neu einplanen.</div>
<?php endif; ?>

<form method="post" class="form" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <section class="card">
    <div class="card__head"><h2>Newsletter</h2></div>
    <div class="grid2">
      <div class="field" style="grid-column:1/-1">
        <label for="titel">Titel <span class="muted">(intern, auch Vorgabe für den Betreff)</span></label>
        <input type="text" id="titel" name="titel" required maxlength="200" value="<?= e((string)$n['titel']) ?>" placeholder="z. B. OV-Nachrichten Oktober 2026">
      </div>
      <div class="field" style="grid-column:1/-1">
        <label for="pdf"><?= !empty($n['stored_name']) ? 'PDF ersetzen <span class="muted">(leer lassen = ' . e((string)$n['orig_name']) . ' behalten)</span>' : 'PDF <span class="muted">(bis ' . $maxMb . ' MB)</span>' ?></label>
        <input type="file" id="pdf" name="pdf" accept="application/pdf,.pdf" <?= !empty($n['stored_name']) ? '' : 'required' ?>>
      </div>
      <div class="field">
        <label for="connector_id">Connector <span class="muted">(für den Abmeldelink)</span></label>
        <select id="connector_id" name="connector_id">
          <option value="">– keiner –</option>
          <?php foreach ($connectoren as $c): ?>
            <option value="<?= (int)$c['id'] ?>"<?= (int)$c['id'] === (int)($n['connector_id'] ?? 0) ? ' selected' : '' ?>><?= e((string)$c['name']) ?><?= connector_gekoppelt($c) ? '' : ' (nicht gekoppelt)' ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!$connectoren): ?><small class="muted">Noch kein Connector mit der Verwendung „Newsletter" – ohne Abmeldelink wird nicht verschickt.</small><?php endif; ?>
      </div>
      <div class="field">
        <label for="senden_am">Versand am <span class="muted">(Vormerkung, eingeplant wird auf der Seite des Newsletters)</span></label>
        <input type="datetime-local" id="senden_am" name="senden_am" value="<?= e(!empty($n['senden_am']) ? date('Y-m-d\TH:i', (int)strtotime(str_replace('T', ' ', (string)$n['senden_am']))) : '') ?>">
      </div>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h2>E-Mail</h2><span class="small muted">bis zum Versand änderbar</span></div>
    <div class="field">
      <label for="betreff">Betreff <span class="muted">(leer = Titel)</span></label>
      <input type="text" id="betreff" name="betreff" maxlength="200" value="<?= e((string)($n['betreff'] ?? '')) ?>">
    </div>
    <div class="field">
      <label for="text">Begleittext <span class="muted">(leer = kurze Vorlage; das PDF hängt an, die Fußzeile mit Abmeldelink kommt automatisch)</span></label>
      <textarea id="text" name="text" rows="7"><?= e((string)($n['text'] ?? '')) ?></textarea>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h2>Empfänger</h2><span class="small muted">jede Adresse einmal, Gesperrte nie</span></div>
    <?php if ($kontaktOptionen): ?>
      <?= render_partial('partials/wahl_liste', ['name' => 'kontakte', 'feld' => 'kontakte_feld', 'label' => 'Kontakte', 'optionen' => $kontaktOptionen, 'gewaehlt' => $kontakteGewaehlt, 'leer' => '– Kontakt wählen –',
          'hinweis' => 'Nur Kontakte mit E-Mail-Adresse.']) ?>
    <?php endif; ?>
    <?php if ($verteilerOptionen): ?>
      <?= render_partial('partials/wahl_liste', ['name' => 'verteiler', 'feld' => 'verteiler_feld', 'label' => 'Verteiler', 'optionen' => $verteilerOptionen, 'gewaehlt' => $verteilerGewaehlt, 'leer' => '– Verteiler wählen –',
          'hinweis' => 'Alle Mitglieder mit E-Mail-Adresse – so, wie der Verteiler beim Einplanen aussieht.']) ?>
    <?php endif; ?>
    <div class="field">
      <label for="adressen">Freie Adressen <span class="muted">(eine je Zeile, auch „Name &lt;adresse&gt;")</span></label>
      <textarea id="adressen" name="adressen" rows="5" class="mono" placeholder="info@example.de&#10;Max Muster <max@example.de>"><?= e((string)($n['adressen'] ?? '')) ?></textarea>
    </div>
  </section>

  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Anlegen' : 'Speichern' ?></button>
    <a class="btn btn--sec" href="<?= e($isNew ? url('newsletter') : url('newsletter_detail', ['id' => $n['id']])) ?>">Abbrechen</a>
  </div>
</form>
