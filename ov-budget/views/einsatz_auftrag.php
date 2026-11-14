<?php
/** @var array $einsatz @var array $daten @var ?array $vorlage @var array $konfig */
$id = (int)$einsatz['id'];
$u = (array)($daten['unterpunkte'] ?? []);
$feld = static fn(string $name, string $label, string $platz = '', int $max = 200): string =>
    '<div class="field"><label for="ea_' . $name . '">' . e($label) . '</label>'
    . '<input type="text" id="ea_' . $name . '" data-ea="' . $name . '" maxlength="' . $max . '" value="' . e((string)($daten[$name] ?? '')) . '" placeholder="' . e($platz) . '"></div>';
$punkt = static fn(string $kennung, string $bedeutung): string =>
    '<div class="auftrag__punkt"><span>' . e($kennung) . '</span><div class="field" style="margin:0">'
    . '<textarea data-ea-punkt="' . e($kennung) . '" rows="2" placeholder="' . e($bedeutung) . '" aria-label="' . e($kennung . ' ' . $bedeutung) . '">'
    . e((string)($u[$kennung] ?? '')) . '</textarea></div></div>';
$json = (string)json_encode($konfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<div class="pagehead">
  <div>
    <h1>Einsatzauftrag <span class="muted">Fb Fü 3</span></h1>
    <p>zu <a href="<?= e(url('einsatz', ['id' => $id])) ?>"><?= e(einsatz_bezeichnung($einsatz)) ?></a>
      <?php if ($vorlage): ?> · Texte übernommen aus „<?= e((string)($vorlage['titel'] ?: $vorlage['orig_name'])) ?>"<?php endif; ?></p>
  </div>
  <a class="btn btn--sec" href="<?= e(url('einsatz', ['id' => $id])) ?>#dateien">Abbrechen</a>
</div>

<noscript><div class="alert alert--error">Der Einsatzauftrag wird im Browser als PDF gesetzt – dafür braucht die Seite JavaScript.</div></noscript>
<p class="alert alert--error" data-ea-meldung role="status" aria-live="polite" hidden></p>

<div class="auftrag" data-einsatzauftrag="<?= e($json) ?>">
  <form class="form" data-ea-form>
    <section class="card">
      <div class="grid2">
        <?= $feld('absender', 'Absender', 'z. B. ZTr TZ OV Musterstadt') ?>
        <?= $feld('empfaenger', 'Empfänger', 'z. B. B-Gruppe 1. TZ') ?>
      </div>
      <div class="grid2">
        <?= $feld('einsatz', 'für den Einsatz') ?>
        <?= $feld('laufendeNummer', 'lfd. Nr.', '', 20) ?>
      </div>
      <div class="field field--check">
        <input type="checkbox" id="ea_uebung" data-ea-uebung value="1" <?= !empty($daten['uebung']) ? 'checked' : '' ?>>
        <label for="ea_uebung">Übung – jede Seite trägt das Wasserzeichen „ÜBUNG"</label>
      </div>
    </section>

    <section class="card">
      <h2>1 Lage</h2>
      <?php foreach (['1a', '1b', '1c'] as $kennung): ?><?= $punkt($kennung, EINSATZ_AUFTRAG_UNTERPUNKTE[$kennung]) ?><?php endforeach; ?>
      <h2 class="mt">2 Auftrag</h2>
      <div class="field"><textarea data-ea="auftrag" rows="3" placeholder="erhaltener Auftrag" aria-label="2 Auftrag"><?= e((string)($daten['auftrag'] ?? '')) ?></textarea></div>
      <h2 class="mt">3 Durchführung</h2>
      <?php foreach (['3a', '3b', '3c'] as $kennung): ?><?= $punkt($kennung, EINSATZ_AUFTRAG_UNTERPUNKTE[$kennung]) ?><?php endforeach; ?>
      <h2 class="mt">4 Logistik</h2>
      <?php foreach (['4a', '4b', '4c', '4d', '4e', '4f', '4g'] as $kennung): ?><?= $punkt($kennung, EINSATZ_AUFTRAG_UNTERPUNKTE[$kennung]) ?><?php endforeach; ?>
      <h2 class="mt">5 Führung und Verbindung</h2>
      <div class="field">
        <textarea data-ea="fuehrung" rows="3" placeholder="Telekommunikationsverbindungen und Meldewesen, Führungsstellen, Einsatzleitungen, Platz des Führers" aria-label="5 Führung und Verbindung"><?= e((string)($daten['fuehrung'] ?? '')) ?></textarea>
        <small class="muted">Eine Leerzeile beginnt eine neue Zeile des Vordrucks.</small>
      </div>
    </section>

    <section class="card">
      <div class="grid2">
        <?= $feld('ortDatum', 'Ort, Datum', '', 120) ?>
        <?= $feld('unterschrift', 'Unterschrift (Name)', '', 120) ?>
      </div>
      <div class="grid2">
        <?= $feld('dienststelle', 'Dienststellung', 'z. B. ZFü, SB AF', 120) ?>
        <?= $feld('abgesandtVon', 'abgesandt von', '', 120) ?>
      </div>
      <div class="grid2">
        <?= $feld('abgesandtAm', 'abgesandt am', 'TT.MM.JJJJ', 40) ?>
        <?= $feld('abgesandtUm', 'abgesandt um', 'hh:mm', 40) ?>
      </div>
      <p class="small muted">In den Textfeldern gilt: <code>**fett**</code>, <code>*kursiv*</code>; Zeilen, die mit <code>|</code> beginnen, werden zur Tabelle.
        Die Schrift wählt der Satz selbst (10 bis 8 pt), damit der Auftrag möglichst auf eine Seite passt – was nicht passt, läuft auf eine Fortsetzungsseite.</p>
      <div class="btnrow">
        <button class="btn" type="button" data-ea-speichern title="Legt das PDF am Einsatz ab – auf dem Server, in der Dateiablage">Als PDF am Einsatz speichern</button>
        <button class="btn btn--sec" type="button" data-ea-laden title="Nur herunterladen, nichts ablegen">Nur herunterladen</button>
        <a class="btn btn--sec" href="<?= e(url('einsatz', ['id' => $id])) ?>#dateien">Abbrechen</a>
      </div>
      <p class="small muted" style="margin-bottom:0">Ein gespeicherter Einsatzauftrag bleibt, wie er ist. Für den nächsten dient er am Einsatz als Vorlage.
        Den Vordruck setzt die Bibliothek <em>einsatzauftrag</em> von Johannes Rudolph (EUPL-1.2) mit <em>jsPDF</em> (MIT) –
        siehe <a href="<?= e(url('lizenzen')) ?>">Bibliotheken und Lizenzen</a>.</p>
    </section>
  </form>

  <aside class="auftrag__vorschau card">
    <div class="card__head"><h2>Vorschau</h2><span class="small muted" data-ea-stand></span></div>
    <iframe data-ea-vorschau title="Vorschau des Einsatzauftrags"></iframe>
  </aside>
</div>
