<?php
/**
 * Dateien eines Fahrzeugs oder Auftrags.
 * @var array  $vehicle
 * @var ?array $order     gesetzt = Dateien eines Auftrags
 * @var array  $bilder
 * @var array  $dokumente
 * @var string $modus     'bilder', 'dokumente' oder 'auftrag'
 */
$order ??= null;
$verwalten = can('manage_vehicles');
$hochladen = can('report_vehicle');
$aktion = url('vehicle_action');
$typen = implode(', ', array_map(static fn($t) => '.' . $t, vfile_document_types()));

/** Löschen- und Titelbild-Knöpfe */
$knoepfe = static function (array $f, bool $mitTitelbild) use ($aktion, $verwalten): string {
    if (!$verwalten) {
        return '';
    }
    $html = '';
    if ($mitTitelbild && !(int)$f['is_cover']) {
        $html .= '<form method="post" action="' . e($aktion) . '" class="inline-form">' . csrf_field()
            . '<input type="hidden" name="action" value="file_cover">'
            . '<input type="hidden" name="file_id" value="' . (int)$f['id'] . '">'
            . '<button class="btn btn--sec btn--sm" type="submit">Als Titelbild</button></form> ';
    }
    $html .= '<form method="post" action="' . e($aktion) . '" class="inline-form">' . csrf_field()
        . '<input type="hidden" name="action" value="file_delete">'
        . '<input type="hidden" name="file_id" value="' . (int)$f['id'] . '">'
        . '<button class="btn btn--sec btn--sm" type="submit" data-confirm="'
        . e('„' . $f['titel'] . '" entfernen? Im Journal bleibt vermerkt, dass es die Datei gab.')
        . '">Entfernen</button></form>';
    return $html;
};

/** Upload-Formular */
$formular = static function (string $art, string $knopf, string $hinweis) use ($vehicle, $order, $aktion): string {
    // image/* – so bietet das Handy direkt die Kamera an
    $accept = $art === 'bild' ? 'image/*' : '';
    return '<form method="post" action="' . e($aktion) . '" enctype="multipart/form-data" class="form upload">'
        . csrf_field()
        . '<input type="hidden" name="action" value="file_upload">'
        . '<input type="hidden" name="art" value="' . e($art) . '">'
        . '<input type="hidden" name="vehicle_id" value="' . (int)$vehicle['id'] . '">'
        . ($order ? '<input type="hidden" name="order_id" value="' . (int)$order['id'] . '">' : '')
        . '<div class="grid2">'
        . '<div class="field"><label>Datei(en)</label>'
        . '<input type="file" name="dateien[]" multiple required data-max-mb="' . (int)setting_int('upload_max_mb', 10) . '"'
        . ($accept !== '' ? ' accept="' . $accept . '"' : '') . '></div>'
        . '<div class="field"><label>Titel (optional)</label>'
        . '<input type="text" name="titel" maxlength="200" placeholder="sonst der Dateiname"></div>'
        . '</div>'
        . '<small class="muted">' . e($hinweis) . '</small>'
        . '<div class="btnrow" style="margin-top:.5rem"><button class="btn btn--sm" type="submit">' . e($knopf) . '</button></div>'
        . '</form>';
};

$dateiZeile = static function (array $f) use ($knoepfe, $order): string {
    $link = e(url('vehicle_file', ['id' => $f['id']]));
    $vorschau = $f['art'] === 'bild'
        ? '<img src="' . e(url('vehicle_file', ['id' => $f['id'], 'vorschau' => 1])) . '" alt="" loading="lazy" class="doc__thumb">'
        : '<span class="doc__icon" aria-hidden="true">' . e(strtoupper(pathinfo((string)$f['orig_name'], PATHINFO_EXTENSION) ?: '?')) . '</span>';
    $zusatz = array_filter([
        de_datetime($f['created_at']),
        $f['hochgeladen_von'] ? 'von ' . $f['hochgeladen_von'] : '',
        bytes_human((int)$f['size_bytes']),
        !$order && $f['auftrag_nummer'] ? 'Auftrag ' . $f['auftrag_nummer'] : '',
    ]);
    return '<div class="doc">' . $vorschau
        . '<div class="doc__text"><a href="' . $link . '" target="_blank" rel="noopener">' . e($f['titel'] ?: $f['orig_name']) . '</a>'
        . '<div class="small muted">' . e(implode(' · ', $zusatz)) . '</div></div>'
        . '<div class="doc__aktion">' . $knoepfe($f, false) . '</div></div>';
};
?>
<?php
/** Galerie – am Fahrzeug mit Titelbild, am Auftrag ohne */
$galerie = static function (array $liste, bool $mitTitelbild) use ($knoepfe): string {
    $html = '<div class="galerie">';
    foreach ($liste as $f) {
        $html .= '<figure class="galerie__bild' . ((int)$f['is_cover'] ? ' is-cover' : '') . '">'
            . '<a href="' . e(url('vehicle_file', ['id' => $f['id']])) . '" target="_blank" rel="noopener">'
            . '<img src="' . e(url('vehicle_file', ['id' => $f['id'], 'vorschau' => 1])) . '"'
            . ' alt="' . e($f['titel']) . '" loading="lazy"></a>'
            . '<figcaption>' . e($f['titel'])
            . ((int)$f['is_cover'] ? ' <span class="badge badge--outline">Titelbild</span>' : '')
            . '<div class="small muted">' . e(de_datetime($f['created_at'])
                . ($f['hochgeladen_von'] ? ' · ' . $f['hochgeladen_von'] : '')) . '</div>'
            . '<div class="btnrow">' . $knoepfe($f, $mitTitelbild) . '</div>'
            . '</figcaption></figure>';
    }
    return $html . '</div>';
};
?>
<?php if ($modus === 'bilder'): ?>
  <section class="card" id="bilder">
    <div class="card__head">
      <h2>Bilder</h2>
      <span class="small muted"><?= count($bilder) ?></span>
    </div>
    <?php if (!$bilder): ?>
      <div class="empty">Noch kein Bild.</div>
    <?php else: ?>
      <?= $galerie($bilder, true) ?>
    <?php endif; ?>
    <?php if ($hochladen): ?>
      <details class="mt"<?= $bilder ? '' : ' open' ?>>
        <summary>Bilder hochladen</summary>
        <?= $formular('bild', 'Hochladen', 'JPG, PNG, WebP oder GIF. Große Fotos werden verkleinert; dabei fallen auch die Metadaten wie der Aufnahmeort weg. Das erste Bild wird Titelbild.') ?>
      </details>
    <?php endif; ?>
  </section>

<?php elseif ($modus === 'dokumente'): ?>
  <section class="card" id="dokumente">
    <div class="card__head">
      <h2>Dokumente</h2>
      <span class="small muted"><?= count($dokumente) ?></span>
    </div>
    <?php if (!$dokumente): ?>
      <div class="empty">Noch kein Dokument – etwa Fahrzeugschein, Prüfberichte oder Bedienungsanleitungen.</div>
    <?php else: ?>
      <div class="doclist">
        <?php foreach ($dokumente as $f): ?><?= $dateiZeile($f) ?><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($hochladen): ?>
      <details class="mt"<?= $dokumente ? '' : ' open' ?>>
        <summary>Dokument anhängen</summary>
        <?= $formular('dokument', 'Anhängen', 'Erlaubt: ' . $typen . '. Dokumente werden unverändert gespeichert.') ?>
      </details>
    <?php endif; ?>
  </section>

<?php else: ?>
  <section class="card" id="fotos">
    <div class="card__head">
      <h2>Fotos zum Auftrag</h2>
      <span class="small muted"><?= count($bilder) ?></span>
    </div>
    <?php if (!$bilder): ?>
      <div class="empty">Noch kein Foto – etwa vom Schaden, vom Ersatzteil oder von der Reparatur.</div>
    <?php else: ?>
      <?= $galerie($bilder, false) ?>
    <?php endif; ?>
    <?php if ($hochladen): ?>
      <details class="mt"<?= $bilder ? '' : ' open' ?>>
        <summary>Fotos hinzufügen</summary>
        <?= $formular('bild', 'Hochladen', 'JPG, PNG, WebP oder GIF – am Handy auch direkt aus der Kamera. Große Fotos werden verkleinert; dabei fallen die Metadaten wie der Aufnahmeort weg.') ?>
      </details>
    <?php endif; ?>
  </section>

  <section class="card" id="dateien">
    <div class="card__head">
      <h2>Dokumente zum Auftrag</h2>
      <span class="small muted"><?= count($dokumente) ?></span>
    </div>
    <?php if (!$dokumente): ?>
      <div class="empty">Noch kein Dokument – etwa Kostenvoranschlag, Werkstattbericht oder Rechnung.</div>
    <?php else: ?>
      <div class="doclist">
        <?php foreach ($dokumente as $f): ?><?= $dateiZeile($f) ?><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($hochladen): ?>
      <details class="mt"<?= $dokumente ? '' : ' open' ?>>
        <summary>Dokument anhängen</summary>
        <?= $formular('dokument', 'Anhängen', 'Erlaubt: ' . $typen . '. Fotos gehören oben in die Galerie.') ?>
      </details>
    <?php endif; ?>
  </section>
<?php endif; ?>
