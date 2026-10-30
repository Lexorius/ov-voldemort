<?php
/** @var array $errors @var ?array $datei @var array $sitzung @var ?array $teile @var array $plan @var array $zaehler
 *  @var ?array $ergebnis @var string $dateiname */
$optionen = $sitzung['optionen'] ?? [];
$ziele = tankkarte_import_ziele();
$aktionen = [
    'neu'            => ['neu anlegen', '#15803d'],
    'ueberschreiben' => ['überschreiben', '#b45309'],
    'ueberspringen'  => ['überspringen', '#64748b'],
    'fehler'         => ['unbrauchbar', '#b91c1c'],
];
$ergebnis ??= null;
?>
<?php if ($ergebnis !== null): ?>
<div class="pagehead">
  <div>
    <h1>Import abgeschlossen</h1>
    <p class="muted small"><?= e($dateiname) ?></p>
  </div>
  <div class="btnrow">
    <a class="btn" href="<?= e(url('tankkarten', ['sort' => 'neu'])) ?>">Zu den Tankkarten</a>
    <a class="btn btn--sec" href="<?= e(url('tankkarten_import')) ?>">Weitere Datei</a>
  </div>
</div>
<div class="alert alert--<?= $ergebnis['fehler'] > 0 ? 'warn' : 'success' ?>">
  <?= (int)($ergebnis['neu'] + $ergebnis['ueberschrieben']) ?> Tankkarte(n) gespeichert<?= $ergebnis['anbieter_neu'] > 0 ? ', ' . (int)$ergebnis['anbieter_neu'] . ' Anbieter neu in der Auswahlliste' : '' ?>.
</div>
<div class="stats">
  <div class="stat"><div class="stat__label">Neu angelegt</div><div class="stat__value" style="color:var(--ok)"><?= (int)$ergebnis['neu'] ?></div></div>
  <div class="stat"><div class="stat__label">Überschrieben</div><div class="stat__value"><?= (int)$ergebnis['ueberschrieben'] ?></div></div>
  <div class="stat"><div class="stat__label">Übersprungen</div><div class="stat__value"><?= (int)$ergebnis['uebersprungen'] ?></div></div>
  <div class="stat"><div class="stat__label">Unbrauchbar</div><div class="stat__value" style="<?= $ergebnis['fehler'] > 0 ? 'color:var(--bad)' : '' ?>"><?= (int)$ergebnis['fehler'] ?></div></div>
</div>
<?php if ($ergebnis['hinweise']): ?>
  <section class="card">
    <h2>Hinweise</h2>
    <ul class="small" style="margin:0;padding-left:1.1rem">
      <?php foreach ($ergebnis['hinweise'] as $h): ?><li><?= e($h) ?></li><?php endforeach; ?>
    </ul>
    <?php if (count($ergebnis['hinweise']) >= 200): ?><p class="small muted">Es werden höchstens 200 Hinweise angezeigt.</p><?php endif; ?>
  </section>
<?php endif; ?>
<?php return; endif; ?>

<div class="pagehead">
  <div>
    <h1>Tankkarten importieren</h1>
    <p>Aus der Kartenübersicht des Anbieters oder der bisherigen Excel-Liste. Vor dem Speichern zeigt eine Vorschau,
       was mit jeder Zeile passiert.</p>
  </div>
  <a class="btn btn--sec" href="<?= e(url('tankkarten')) ?>">Zu den Tankkarten</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if (!$datei): ?>

  <section class="card">
    <h2>1. Datei hochladen</h2>
    <form method="post" enctype="multipart/form-data" class="form" action="<?= e(url('tankkarten_import')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload">
      <div class="field">
        <label for="datei">CSV-Datei</label>
        <input type="file" id="datei" name="datei" accept=".csv,.txt,text/csv" required data-max-mb="<?= setting_int('upload_max_mb', 10) ?>">
        <small>Höchstens <?= TANKKARTE_IMPORT_MAX ?> Zeilen und <?= setting_int('upload_max_mb', 10) ?> MB je Datei. Semikolon oder Komma,
          Windows-Zeichensatz oder UTF-8 – beides wird erkannt.</small>
      </div>
      <div><button class="btn" type="submit">Hochladen und prüfen</button></div>
    </form>
  </section>

  <section class="card">
    <h2>Was die Datei enthalten kann</h2>
    <p class="small">Pflicht ist nur die <strong>Kartennummer</strong>; der Anbieter kommt aus der Datei oder als Vorgabe.
      Dazu nach Wunsch: Schaltet frei (Kraftstoff, Laden, beides), Status, Kundennummer, Gültig bis, Ausgegeben am,
      <strong>Fahrzeuge</strong> (Kennzeichen oder Bezeichnung, mehrere mit Komma), Liegt bei (Name des Mitglieds),
      Aufbewahrung, PIN, Verbleib mit Datum und Grund, Notiz. Die Spaltennamen werden erkannt, der Rest lässt sich im
      nächsten Schritt zuordnen. <a href="<?= e(url('tankkarten_import', ['vorlage' => '1'])) ?>">Vorlage herunterladen</a></p>
  </section>

<?php else: ?>

  <section class="card">
    <div class="card__head">
      <div>
        <h2 style="margin:0">2. Zuordnung prüfen</h2>
        <div class="muted small"><?= e((string)$datei['name']) ?> · <?= count($teile['roh']) ?> Zeilen, <?= count($teile['header']) ?> Spalten</div>
      </div>
      <form method="post" class="inline-form" action="<?= e(url('tankkarten_import')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel">
        <button class="btn btn--sec btn--sm" type="submit">Andere Datei</button>
      </form>
    </div>

    <form method="post" class="form" action="<?= e(url('tankkarten_import')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="preview">
      <p class="small muted mb0">Die Zuordnung wurde anhand der Spaltennamen geschätzt. Spalten ohne Ziel werden nicht übernommen.</p>
      <div class="tablewrap">
        <table class="data">
          <thead><tr><th>Spalte in der Datei</th><th>Beispielwerte</th><th>wird zu</th></tr></thead>
          <tbody>
          <?php foreach ($teile['header'] as $i => $spalte):
              $beispiele = [];
              foreach (array_slice($teile['roh'], 0, 12) as $roh) {
                  if (trim((string)($roh[$i] ?? '')) !== '' && count($beispiele) < 3) {
                      $beispiele[] = mb_substr((string)$roh[$i], 0, 40);
                  }
              }
              $gewaehlt = (string)($sitzung['mapping'][$i] ?? '');
          ?>
            <tr>
              <td><strong><?= e($spalte !== '' ? $spalte : '(ohne Namen)') ?></strong></td>
              <td class="small muted"><?= $beispiele ? e(implode(' · ', $beispiele)) : '– leer –' ?></td>
              <td>
                <select name="map[<?= (int)$i ?>]" style="min-height:36px;padding:.3rem" aria-label="Ziel für <?= e($spalte) ?>">
                  <option value="">– nicht übernehmen –</option>
                  <?php foreach ($ziele as $k => $lbl): ?>
                    <option value="<?= e($k) ?>"<?= $gewaehlt === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <fieldset>
        <legend>Optionen</legend>
        <div class="grid2">
          <div class="field">
            <label for="strategie">Wenn eine Karte schon vorhanden ist</label>
            <select id="strategie" name="strategie">
              <?php foreach (TANKKARTE_IMPORT_STRATEGIEN as $k => $lbl): ?>
                <option value="<?= e($k) ?>"<?= ($optionen['strategie'] ?? '') === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select>
            <small>Vorhanden heißt: gleicher Anbieter und gleiche Kartennummer. Überschreiben setzt auch die Fahrzeuge neu.</small>
          </div>
          <div class="field">
            <label for="anbieter_id">Anbieter, wenn die Datei keinen nennt</label>
            <select id="anbieter_id" name="anbieter_id"><?= list_options('tankkarte_anbieter', $optionen['anbieter_id'] ?? null, '– keiner –', false) ?></select>
          </div>
          <div class="field field--check">
            <input type="checkbox" id="anbieter_anlegen" name="anbieter_anlegen" value="1" <?= !empty($optionen['anbieter_anlegen']) ? 'checked' : '' ?>>
            <label for="anbieter_anlegen">Unbekannte Anbieter aus der Datei in der Auswahlliste anlegen</label>
          </div>
        </div>
      </fieldset>

      <div class="btnrow">
        <button class="btn" type="submit"><?= empty($sitzung['vorschau']) ? 'Vorschau anzeigen' : 'Vorschau aktualisieren' ?></button>
      </div>
    </form>
  </section>

  <?php if (!empty($sitzung['vorschau'])): ?>
    <section class="card" id="vorschau">
      <h2>3. Vorschau</h2>
      <div class="stats">
        <?php foreach ($aktionen as $k => [$lbl, $farbe]): ?>
          <div class="stat">
            <div class="stat__label"><?= e($lbl) ?></div>
            <div class="stat__value" style="color:<?= e($farbe) ?>"><?= (int)($zaehler[$k] ?? 0) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="tablewrap" style="max-height:32rem;overflow-y:auto">
        <table class="data">
          <thead><tr><th class="num">Zeile</th><th>Ergebnis</th><th>Anbieter</th><th>Nummer</th><th>Schaltet frei</th><th>Fahrzeuge</th><th>Verbleib</th><th>Hinweise</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($plan, 0, 300) as $e):
              $k = $e['karte'];
              [$lbl, $farbe] = $aktionen[$e['aktion']];
          ?>
            <tr>
              <td class="num small"><?= (int)$e['zeile'] ?></td>
              <td><span class="badge" style="background:<?= e($farbe) ?>"><?= e($lbl) ?></span>
                <?php if ($e['grund'] !== ''): ?><div class="small muted"><?= e($e['grund']) ?></div><?php endif; ?></td>
              <td class="small"><?= $k['anbieter_id'] ? e(list_label((int)$k['anbieter_id'])) : ($e['anbieter_neu'] ? e($e['anbieter_text']) . ' <span class="badge badge--outline">wird angelegt</span>' : '<span class="muted">–</span>') ?></td>
              <td class="mono small"><?= e($k['nummer'] ?: '–') ?></td>
              <td class="small"><?= e(TANKKARTE_ARTEN[$k['art']] ?? $k['art']) ?></td>
              <td class="small"><?= count($e['fahrzeug_ids']) > 0 ? count($e['fahrzeug_ids']) . ' zugeordnet' : '<span class="muted">keines</span>' ?><?= $e['fahrzeuge_unbekannt'] ? '<div class="small" style="color:var(--warn)">unbekannt: ' . e(implode(', ', $e['fahrzeuge_unbekannt'])) . '</div>' : '' ?></td>
              <td class="small"><?= $k['verbleib'] !== '' ? e(TANKKARTE_VERBLEIB[$k['verbleib']]) . ($k['sperrgrund'] !== '' ? ' – ' . e($k['sperrgrund']) : '') : '<span class="muted">im Bestand</span>' ?></td>
              <td class="small muted"><?= e(implode('; ', $e['hinweise'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (count($plan) > 300): ?><p class="small muted">Gezeigt werden die ersten 300 von <?= count($plan) ?> Zeilen – importiert werden alle.</p><?php endif; ?>

      <form method="post" class="btnrow mt" action="<?= e(url('tankkarten_import')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import">
        <?php $speichern = ($zaehler['neu'] ?? 0) + ($zaehler['ueberschreiben'] ?? 0); ?>
        <button class="btn btn--ok" type="submit" <?= $speichern === 0 ? 'disabled' : '' ?>
                data-confirm="<?= e(sprintf('%d Tankkarten jetzt speichern?', $speichern)) ?>">Jetzt <?= (int)$speichern ?> Tankkarten importieren</button>
        <small class="muted" style="align-self:center">Geänderte Zuordnungen bitte zuerst mit „Vorschau aktualisieren" übernehmen.</small>
      </form>
    </section>
  <?php endif; ?>

<?php endif; ?>
