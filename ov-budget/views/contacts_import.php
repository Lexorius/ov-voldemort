<?php
/** @var array $errors @var ?array $datei @var array $sitzung @var ?array $teile @var array $plan
 *  @var array $zaehler @var array $verteiler @var array $kategorien */
$optionen = $sitzung['optionen'] ?? [];
$ziele = import_targets();
$extraFelder = contact_extra_fields();

$aktionen = [
    'neu'            => ['neu anlegen', '#15803d'],
    'ergaenzen'      => ['ergänzen', '#0369a1'],
    'ueberschreiben' => ['überschreiben', '#b45309'],
    'ueberspringen'  => ['überspringen', '#64748b'],
    'fehler'         => ['unbrauchbar', '#b91c1c'],
];
?>
<div class="pagehead">
  <div>
    <h1>Kontakte importieren</h1>
    <p>Aus einer Excel-Liste, aus Outlook, Google Kontakte oder vom Handy. Vor dem Speichern
       zeigt eine Vorschau, was mit jedem Eintrag passiert.</p>
  </div>
  <a class="btn btn--sec" href="<?= e(url('contacts')) ?>">Zu den Kontakten</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if (!$datei): ?>

  <section class="card">
    <h2>1. Datei hochladen</h2>
    <form method="post" enctype="multipart/form-data" class="form" action="<?= e(url('contacts_import')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload">
      <div class="field">
        <label for="datei">CSV- oder vCard-Datei</label>
        <input type="file" id="datei" name="datei" accept=".csv,.txt,.vcf,.vcard,text/csv,text/vcard" required
               data-max-mb="<?= setting_int('upload_max_mb', 10) ?>">
        <small>Höchstens <?= KONTAKT_IMPORT_MAX ?> Einträge und <?= setting_int('upload_max_mb', 10) ?> MB je Datei.</small>
      </div>
      <div><button class="btn" type="submit">Hochladen und prüfen</button></div>
    </form>
  </section>

  <section class="card">
    <h2>Woher die Datei kommen kann</h2>
    <dl class="dl">
      <div class="dl__item">
        <div class="dl__label">Excel oder LibreOffice</div>
        <div class="dl__value small">Als „CSV (Trennzeichen-getrennt)" speichern. Semikolon oder Komma,
          Windows-Zeichensatz oder UTF-8 – beides wird erkannt.
          <a href="<?= e(url('contacts_import', ['vorlage' => '1'])) ?>">Vorlage herunterladen</a></div>
      </div>
      <div class="dl__item">
        <div class="dl__label">Outlook</div>
        <div class="dl__value small">Datei → Öffnen und Exportieren → Importieren/Exportieren →
          „In Datei exportieren" → „Kommagetrennte Werte". Deutsche und englische Spaltennamen werden erkannt.</div>
      </div>
      <div class="dl__item">
        <div class="dl__label">Google Kontakte</div>
        <div class="dl__value small">contacts.google.com → Exportieren → „Google CSV" oder „vCard".</div>
      </div>
      <div class="dl__item">
        <div class="dl__label">Handy, iCloud, Thunderbird</div>
        <div class="dl__value small">Als vCard (.vcf) teilen oder exportieren. Mehrere Kontakte in einer
          Datei sind in Ordnung.</div>
      </div>
    </dl>
  </section>

<?php else: ?>

  <section class="card">
    <div class="card__head">
      <div>
        <h2 style="margin:0">2. Zuordnung prüfen</h2>
        <div class="muted small">
          <?= e((string)$datei['name']) ?> ·
          <?= $teile['typ'] === 'vcard'
              ? count($teile['zeilen']) . ' vCards'
              : count($teile['roh']) . ' Zeilen, ' . count($teile['header']) . ' Spalten' ?>
        </div>
      </div>
      <form method="post" class="inline-form" action="<?= e(url('contacts_import')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel">
        <button class="btn btn--sec btn--sm" type="submit">Andere Datei</button>
      </form>
    </div>

    <form method="post" class="form" action="<?= e(url('contacts_import')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="preview">

      <?php if ($teile['typ'] === 'csv'): ?>
        <p class="small muted mb0">Die Zuordnung wurde anhand der Spaltennamen geschätzt.
          Spalten ohne Ziel werden nicht übernommen.</p>
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
                  <select name="map[<?= (int)$i ?>]" style="min-height:36px;padding:.3rem"
                          aria-label="Ziel für <?= e($spalte) ?>">
                    <option value="">– nicht übernehmen –</option>
                    <?php foreach ($ziele as $k => $lbl): ?>
                      <option value="<?= e($k) ?>"<?= $gewaehlt === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                    <?php if ($extraFelder): ?>
                      <optgroup label="Zusatzfelder">
                        <?php foreach ($extraFelder as $k => $def): ?>
                          <option value="extra:<?= e($k) ?>"<?= $gewaehlt === 'extra:' . $k ? ' selected' : '' ?>>
                            <?= e($def['label']) ?></option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endif; ?>
                  </select>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="small">vCards haben feste Felder – Name, Organisation, Funktion, E-Mail, Telefon, Mobil,
          Anschrift, Notiz und Kategorie werden direkt übernommen. Bei mehreren Adressen oder Nummern
          gewinnt die dienstliche.</p>
      <?php endif; ?>

      <fieldset>
        <legend>Optionen</legend>
        <div class="grid2">
          <div class="field">
            <label for="strategie">Wenn ein Kontakt schon vorhanden ist</label>
            <select id="strategie" name="strategie">
              <?php foreach (IMPORT_STRATEGIEN as $k => $lbl): ?>
                <option value="<?= e($k) ?>"<?= ($optionen['strategie'] ?? '') === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select>
            <small>Vorhanden heißt: gleiche E-Mail-Adresse, oder ohne E-Mail gleicher Name samt Organisation.</small>
          </div>
          <div class="field">
            <label for="kategorie_id">Kategorie, wenn die Datei keine liefert</label>
            <select id="kategorie_id" name="kategorie_id">
              <?= list_options('kontakt_kategorie', $optionen['kategorie_id'] ?? null, '– keine –') ?>
            </select>
          </div>
          <div class="field">
            <label for="group_id">Importierte Kontakte auf einen Verteiler setzen</label>
            <select id="group_id" name="group_id">
              <option value="">– nein –</option>
              <?php foreach ($verteiler as $v): ?>
                <option value="<?= (int)$v['id'] ?>"<?= (int)($optionen['group_id'] ?? 0) === (int)$v['id'] ? ' selected' : '' ?>>
                  <?= e($v['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field field--check">
            <input type="checkbox" id="kategorien_anlegen" name="kategorien_anlegen" value="1"
                   <?= !empty($optionen['kategorien_anlegen']) ? 'checked' : '' ?>>
            <label for="kategorien_anlegen">Unbekannte Kategorien aus der Datei neu anlegen</label>
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
        <?php foreach ($aktionen as $k => [$lbl, $farbe]):
            if (($zaehler[$k] ?? 0) === 0 && in_array($k, ['ergaenzen', 'ueberschreiben'], true)) {
                continue;
            } ?>
          <div class="stat">
            <div class="stat__label"><?= e($lbl) ?></div>
            <div class="stat__value" style="color:<?= e($farbe) ?>"><?= (int)($zaehler[$k] ?? 0) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="tablewrap" style="max-height:32rem;overflow-y:auto">
        <table class="data">
          <thead><tr><th class="num">Zeile</th><th>Ergebnis</th><th>Name</th><th>Organisation</th>
                     <th>E-Mail</th><th>Ort</th><th>Kategorie</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($plan, 0, 300) as $e):
              $c = $e['kontakt'];
              [$lbl, $farbe] = $aktionen[$e['aktion']];
              $katText = $c['kategorie_text'];
          ?>
            <tr>
              <td class="num small"><?= (int)$e['zeile'] ?></td>
              <td>
                <span class="badge" style="background:<?= e($farbe) ?>"><?= e($lbl) ?></span>
                <?php if ($e['grund'] !== ''): ?><div class="small muted"><?= e($e['grund']) ?></div><?php endif; ?>
              </td>
              <td><?= e(trim(($c['titel'] ? $c['titel'] . ' ' : '') . $c['vorname'] . ' ' . $c['nachname']) ?: '–') ?></td>
              <td class="small"><?= e($c['organisation'] ?: '–') ?></td>
              <td class="small"><?= e($c['email'] ?: '–') ?></td>
              <td class="small"><?= e(trim($c['plz'] . ' ' . $c['ort']) ?: '–') ?></td>
              <td class="small">
                <?php if ($katText === ''): ?>
                  <span class="muted"><?= e(list_label((int)($optionen['kategorie_id'] ?? 0), '–')) ?></span>
                <?php elseif ($kategorien[$katText] ?? null): ?>
                  <?= e((string)$kategorien[$katText]) ?>
                <?php elseif (!empty($optionen['kategorien_anlegen'])): ?>
                  <?= e($katText) ?> <span class="badge badge--outline">wird angelegt</span>
                <?php else: ?>
                  <span class="muted" title="„<?= e($katText) ?>" ist keine bekannte Kategorie">
                    <?= e(list_label((int)($optionen['kategorie_id'] ?? 0), '–')) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (count($plan) > 300): ?>
        <p class="small muted">Gezeigt werden die ersten 300 von <?= count($plan) ?> Einträgen – importiert werden alle.</p>
      <?php endif; ?>

      <form method="post" class="btnrow mt" action="<?= e(url('contacts_import')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import">
        <?php $speichern = ($zaehler['neu'] ?? 0) + ($zaehler['ergaenzen'] ?? 0) + ($zaehler['ueberschreiben'] ?? 0); ?>
        <button class="btn btn--ok" type="submit" <?= $speichern === 0 ? 'disabled' : '' ?>
                data-confirm="<?= e(sprintf('%d Kontakte jetzt speichern?', $speichern)) ?>">
          Jetzt <?= (int)$speichern ?> Kontakte importieren</button>
        <small class="muted" style="align-self:center">Geänderte Zuordnungen bitte zuerst mit
          „Vorschau aktualisieren" übernehmen.</small>
      </form>
    </section>
  <?php endif; ?>

<?php endif; ?>
