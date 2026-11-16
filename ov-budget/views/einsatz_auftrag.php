<?php
/** @var array $ziel @var array $daten @var ?array $vorlage @var array $konfig
 *  $ziel: wozu der Auftrag gehört – name, url, zurueck, wo („am Einsatz" / „an der Veranstaltung"), art */
$u = (array)($daten['unterpunkte'] ?? []);
$feld = static fn(string $name, string $label, string $platz = '', int $max = 200): string =>
    '<div class="field"><label for="ea_' . $name . '">' . e($label) . '</label>'
    . '<input type="text" id="ea_' . $name . '" data-ea="' . $name . '" maxlength="' . $max . '" value="' . e((string)($daten[$name] ?? '')) . '" placeholder="' . e($platz) . '"></div>';
$punkt = static fn(string $kennung, string $bedeutung): string =>
    '<div class="auftrag__punkt"><span>' . e($kennung) . '</span><div class="field" style="margin:0">'
    . '<textarea data-ea-punkt="' . e($kennung) . '" rows="2" placeholder="' . e($bedeutung) . '" aria-label="' . e($kennung . ' ' . $bedeutung) . '">'
    . e((string)($u[$kennung] ?? '')) . '</textarea></div></div>';
$json = (string)json_encode($konfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$vorlagen ??= [];
$selbst ??= '';
$dies = (string)$ziel['art'] === 'Veranstaltung' ? 'diese Veranstaltung' : 'diesen Einsatz';
?>
<div class="pagehead">
  <div>
    <h1>Einsatzauftrag <span class="muted">Fb Fü 3</span></h1>
    <p>zu <?= e((string)$ziel['art']) ?> <a href="<?= e((string)$ziel['url']) ?>"><?= e((string)$ziel['name']) ?></a>
      <?php if ($vorlage): ?> · Texte übernommen aus „<?= e((string)($vorlage['titel'] ?: $vorlage['orig_name'])) ?>"<?php endif; ?></p>
  </div>
  <a class="btn btn--sec" href="<?= e((string)$ziel['zurueck']) ?>">Abbrechen</a>
</div>

<noscript><div class="alert alert--error">Der Einsatzauftrag wird im Browser als PDF gesetzt – dafür braucht die Seite JavaScript.</div></noscript>
<p class="alert alert--error" data-ea-meldung role="status" aria-live="polite" hidden></p>

<?php if ($vorlagen): ?>
<section class="card" id="vorlagen">
  <h2>Vorlagen</h2>
  <p class="small muted">„Übernehmen" beginnt den Auftrag mit den Texten der Vorlage – was unten schon eingetragen ist, wird dabei ersetzt.</p>
  <div class="tablewrap">
    <table class="data">
      <tbody>
      <?php foreach ($vorlagen as $v): ?>
        <tr>
          <td><strong><?= e((string)$v['name']) ?></strong>
            <?= (int)$v['global'] === 1 ? '<span class="badge badge--outline">global</span>' : '<span class="badge badge--outline">nur ' . e($dies) . '</span>' ?>
            <?php if (!empty($v['von'])): ?><div class="small muted">von <?= e((string)$v['von']) ?>, <?= e(de_date(substr((string)($v['updated_at'] ?? $v['created_at'] ?? ''), 0, 10))) ?></div><?php endif; ?></td>
          <td class="nowrap" style="text-align:right">
            <a class="btn btn--sec btn--sm" href="<?= e($selbst . '&muster=' . (int)$v['id']) ?>">Übernehmen</a>
            <form method="post" action="<?= e($selbst) ?>" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="vorlage_weg">
              <input type="hidden" name="vorlage_id" value="<?= (int)$v['id'] ?>">
              <button class="btn btn--sec btn--sm" type="submit" data-confirm="Vorlage „<?= e((string)$v['name']) ?>“ löschen?<?= (int)$v['global'] === 1 ? ' Sie steht dann bei keinem Einsatz mehr zur Auswahl.' : '' ?>" title="Vorlage löschen" aria-label="Vorlage <?= e((string)$v['name']) ?> löschen">🗑</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

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
        <button class="btn" type="button" data-ea-speichern title="Legt das PDF <?= e((string)$ziel['wo']) ?> ab – auf dem Server, in der Dateiablage">Als PDF <?= e((string)$ziel['wo']) ?> speichern</button>
        <button class="btn btn--sec" type="button" data-ea-laden title="Nur herunterladen, nichts ablegen">Nur herunterladen</button>
        <a class="btn btn--sec" href="<?= e((string)$ziel['zurueck']) ?>">Abbrechen</a>
      </div>
      <div class="btnrow">
        <button class="btn btn--sec" type="button" data-ea-vorlage="ziel" title="Merkt die Texte unter einem Namen – zur Auswahl bei jedem weiteren Auftrag für <?= e($dies) ?>">Als Vorlage für <?= e($dies) ?> speichern</button>
        <button class="btn btn--sec" type="button" data-ea-vorlage="global" title="Merkt die Texte unter einem Namen – zur Auswahl bei jedem Einsatz und jeder Veranstaltung">Als globale Vorlage speichern</button>
      </div>
      <p class="small muted">Eine Vorlage merkt sich die Texte, kein PDF – ohne laufende Nummer, Ort und Datum und Absendevermerk; eine globale auch ohne Einsatz und Unterschrift.
        Vorlagen stehen oben auf dieser Seite zur Auswahl.</p>
      <p class="small muted" style="margin-bottom:0">Ein gespeicherter Einsatzauftrag bleibt, wie er ist. Für den nächsten dient er <?= e((string)$ziel['wo']) ?> als Vorlage.
        Den Vordruck setzt die Bibliothek <em>einsatzauftrag</em> von Johannes Rudolph (EUPL-1.2) mit <em>jsPDF</em> (MIT) –
        siehe <a href="<?= e(url('lizenzen')) ?>">Bibliotheken und Lizenzen</a>.</p>
    </section>
  </form>

  <aside class="auftrag__vorschau card">
    <div class="card__head"><h2>Vorschau</h2><span class="small muted" data-ea-stand></span></div>
    <iframe data-ea-vorschau title="Vorschau des Einsatzauftrags"></iframe>
  </aside>
</div>
