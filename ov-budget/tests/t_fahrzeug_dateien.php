<?php
declare(strict_types=1);
/*
 * Bilder und Dokumente an Fahrzeugen und Aufträgen.
 * Aufruf mit GD, EXIF und fileinfo (siehe Befehl im Aufrufer).
 */
session_start();
define('OVB_TEST_UPLOADS', true);

$tmp = sys_get_temp_dir() . '/ovb-dateien-test';
@mkdir($tmp, 0777, true);
putenv('OVB_UPLOAD_DIR=' . $tmp);
$_ENV['OVB_UPLOAD_DIR'] = $tmp;

$GLOBALS['settings'] = [
    'waehrung' => 'EUR', 'upload_max_mb' => '10',
    // absichtlich mit gefährlichen Endungen – die dürfen trotzdem nie durch
    'fahrzeug_dokument_typen' => 'pdf, docx ,.xlsx,php,html,svg,txt',
];
require __DIR__ . '/stub_db.php';

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/uploads.php';
require $app . '/src/lib/vehicles.php';
require $app . '/src/lib/vehicle_files.php';
require $app . '/src/lib/view.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

if (!vfile_gd_available()) { echo "ohne GD: Bildverarbeitung uebersprungen
"; }

/* ---------- Erlaubte Typen ---------- */
$typen = vfile_document_types();
$check('eingestellte Typen gelesen', $typen === ['pdf', 'docx', 'xlsx', 'txt']);
$check('php nie erlaubt', !in_array('php', $typen, true));
$check('html nie erlaubt', !in_array('html', $typen, true));
$check('svg nie erlaubt', !in_array('svg', $typen, true));

/* ---------- Prüfung einer Datei ---------- */
$mb = 1024 * 1024;
$erlaubt = array_merge($typen, VFILE_BILD_TYPEN);
$check('PDF passt', vfile_check('Schein.pdf', 5000, 'application/pdf', 'dokument', $erlaubt, 10 * $mb) === null);
$check('Foto passt', vfile_check('GKW.JPG', 5000, 'image/jpeg', 'bild', VFILE_BILD_TYPEN, 10 * $mb) === null);
$check('leer', str_contains((string)vfile_check('a.pdf', 0, 'application/pdf', 'dokument', $erlaubt, $mb), 'leer'));
$check('zu groß', str_contains((string)vfile_check('a.pdf', 2 * $mb, 'application/pdf', 'dokument', $erlaubt, $mb), 'zu groß'));
$check('falsche Endung', str_contains((string)vfile_check('a.exe', 10, 'application/x-msdownload', 'dokument', $erlaubt, $mb), 'nicht erlaubt'));
$check('ohne Endung', vfile_check('README', 10, 'text/plain', 'dokument', $erlaubt, $mb) !== null);
$check('PDF in der Galerie abgelehnt', vfile_check('a.pdf', 10, 'application/pdf', 'bild', ['pdf', 'jpg'], $mb) !== null);
$check('HTML als .jpg getarnt', str_contains(
    (string)vfile_check('bild.jpg', 10, 'text/html', 'dokument', $erlaubt, $mb), 'kein'));
$check('SVG als Inhalt abgelehnt', vfile_check('x.png', 10, 'image/svg+xml', 'bild', VFILE_BILD_TYPEN, $mb) !== null);
$check('HTML als .txt abgelehnt', vfile_check('x.txt', 10, 'text/html', 'dokument', $erlaubt, $mb) !== null);

/* ---------- Bildverarbeitung ---------- */
if (vfile_gd_available()) {
$gross = $tmp . '/gross.jpg';
$img = imagecreatetruecolor(3000, 2000);
imagefilledrectangle($img, 0, 0, 3000, 2000, imagecolorallocate($img, 0, 51, 153));
imagejpeg($img, $gross, 90);


// Ein EXIF-Block mit "Aufnahmeort" hineinschmuggeln, wie ihn Handys schreiben
$roh = (string)file_get_contents($gross);
$exif = "Exif\0\0" . 'GPS-GEHEIM-53.0793N-8.8017E';
$app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;
file_put_contents($gross, substr($roh, 0, 2) . $app1 . substr($roh, 2));
$check('Testbild trägt den Ort', str_contains((string)file_get_contents($gross), 'GPS-GEHEIM'));

$ziel = $tmp . '/klein.jpg';
$masse = vfile_process_image($gross, $ziel, 'image/jpeg', VFILE_BILD_MAX);
$check('verkleinert auf 1600 Breite', $masse === [1600, 1067]);
[$b, $h] = getimagesize($ziel);
$check('Datei hat die neuen Maße', $b === 1600 && $h === 1067);
$check('Ort ist weg', !str_contains((string)file_get_contents($ziel), 'GPS-GEHEIM'));
$check('deutlich kleiner', filesize($ziel) < filesize($gross));

$vorschau = $tmp . '/vorschau.jpg';
$check('Vorschau 480', vfile_process_image($ziel, $vorschau, 'image/jpeg', VFILE_VORSCHAU_MAX) === [480, 320]);

$hoch = $tmp . '/hoch.png';
$img = imagecreatetruecolor(600, 1200);
imagesavealpha($img, true);
imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
imagepng($img, $hoch);

$check('Hochformat: Höhe begrenzt', vfile_process_image($hoch, $tmp . '/hoch2.png', 'image/png', 480) === [240, 480]);
$transparent = imagecreatefrompng($tmp . '/hoch2.png');
$farbe = imagecolorsforindex($transparent, imagecolorat($transparent, 5, 5));
$check('Transparenz bleibt', $farbe['alpha'] === 127);

$klein = $tmp . '/klein.png';
$img = imagecreatetruecolor(200, 100);
imagepng($img, $klein);
$check('kleines Bild wird nicht vergrößert', vfile_process_image($klein, $tmp . '/klein2.png', 'image/png', 1600) === [200, 100]);

file_put_contents($tmp . '/kaputt.jpg', 'kein Bild');
$check('kaputte Datei: kein Absturz', vfile_process_image($tmp . '/kaputt.jpg', $tmp . '/x.jpg', 'image/jpeg', 1600) === null);

/* ---------- Hochladen von Anfang bis Ende ---------- */
foreach (glob(vfile_dir() . '/*') ?: [] as $alt) {
    @unlink($alt);
}
$hochgeladen = $tmp . '/upload_' . bin2hex(random_bytes(4));
copy($gross, $hochgeladen);
$_FILES['dateien'] = [
    'name' => ['GKW vorne.jpg', 'Trojaner.php', ''],
    'type' => ['image/jpeg', 'text/plain', ''],
    'tmp_name' => [$hochgeladen, $tmp . '/gibtsnicht', ''],
    'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
    'size' => [filesize($hochgeladen), 100, 0],
];
file_put_contents($tmp . '/gibtsnicht', '<?php echo 1;');
$GLOBALS['inserts'] = [];
[$n, $fehler] = vfile_store_uploads(7, null, 'dateien', 'bild', '', ['id' => 1, 'display_name' => 'Tester']);
$check('ein Bild gespeichert', $n === 1);
$check('PHP-Datei abgelehnt', count($fehler) === 1 && str_contains($fehler[0], 'Trojaner.php'));

$datensatz = null;
foreach ($GLOBALS['inserts'] as [$tabelle, $zeile]) {
    if ($tabelle === 'vehicle_files') {
        $datensatz = $zeile;
    }
}
$check('Datensatz angelegt', $datensatz !== null && $datensatz['vehicle_id'] === 7 && $datensatz['art'] === 'bild');
$check('Titel aus dem Dateinamen', $datensatz['titel'] === 'GKW vorne');
$check('Vorschau angelegt', $datensatz['thumb_name'] !== null
    && is_file(vfile_dir() . '/' . $datensatz['thumb_name']));
$check('gespeicherter Name ist zufällig', !str_contains($datensatz['stored_name'], 'GKW'));
$check('gespeichertes Bild ohne Ort', !str_contains(
    (string)file_get_contents(vfile_dir() . '/' . $datensatz['stored_name']), 'GPS-GEHEIM'));
$check('Journaleintrag geschrieben', in_array('vehicle_journal', array_column($GLOBALS['inserts'], 0), true));
$journal = array_values(array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === 'vehicle_journal'))[0][1];
$check('Journal nennt das Bild', $journal['titel'] === 'Bild hinzugefügt: GKW vorne.jpg');

// Ein Foto als Dokument (etwa der Fahrzeugschein) bleibt unverändert
$schein = $tmp . '/upload_schein';
copy($gross, $schein);
$_FILES['dateien'] = ['name' => ['Fahrzeugschein.jpg'], 'type' => ['image/jpeg'], 'tmp_name' => [$schein],
                      'error' => [UPLOAD_ERR_OK], 'size' => [filesize($schein)]];
$GLOBALS['inserts'] = [];
[$n] = vfile_store_uploads(7, null, 'dateien', 'dokument', 'Fahrzeugschein', ['id' => 1, 'display_name' => 'Tester']);
$doc = $GLOBALS['inserts'][0][1];
$check('Dokument gespeichert', $n === 1 && $doc['art'] === 'dokument' && $doc['thumb_name'] === null);
[$b] = getimagesize(vfile_dir() . '/' . $doc['stored_name']);
$check('Scan behält die volle Auflösung', $b === 3000);
$check('auch am Dokument ist der Ort weg', !str_contains(
    (string)file_get_contents(vfile_dir() . '/' . $doc['stored_name']), 'GPS-GEHEIM'));

// Ein PDF bleibt Byte für Byte unverändert
$pdfDatei = $tmp . '/upload_pdf';
$pdfInhalt = "%PDF-1.4
1 0 obj << /Type /Catalog >> endobj
trailer << /Root 1 0 R >>
%%EOF
";
file_put_contents($pdfDatei, $pdfInhalt);
$_FILES['dateien'] = ['name' => ['Pruefbericht.pdf'], 'type' => ['application/pdf'], 'tmp_name' => [$pdfDatei],
                      'error' => [UPLOAD_ERR_OK], 'size' => [strlen($pdfInhalt)]];
$GLOBALS['inserts'] = [];
[$n, $fehler] = vfile_store_uploads(7, null, 'dateien', 'dokument', '', ['id' => 1, 'display_name' => 'Tester']);
$pdfZeile = $GLOBALS['inserts'][0][1] ?? [];
$check('PDF gespeichert', $n === 1 && ($pdfZeile['mime'] ?? '') === 'application/pdf');
$check('PDF unverändert', (string)file_get_contents(vfile_dir() . '/' . $pdfZeile['stored_name']) === $pdfInhalt);

// Am Auftrag: Bild wird erkannt
$foto = $tmp . '/upload_schaden';
copy($gross, $foto);
$_FILES['dateien'] = ['name' => ['Schaden.jpg'], 'type' => ['image/jpeg'], 'tmp_name' => [$foto],
                      'error' => [UPLOAD_ERR_OK], 'size' => [filesize($foto)]];
$GLOBALS['inserts'] = [];
[$n] = vfile_store_uploads(7, 42, 'dateien', 'auto', '', ['id' => 1, 'display_name' => 'Tester']);
$z = $GLOBALS['inserts'][0][1];
$check('am Auftrag als Bild erkannt', $z['art'] === 'bild' && $z['order_id'] === 42);
$j = array_values(array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === 'vehicle_journal'))[0][1];
$check('Journal verweist auf den Auftrag', $j['ref_typ'] === 'auftrag' && $j['ref_id'] === 42);

$_FILES = [];
[$n, $fehler] = vfile_store_uploads(7, null, 'dateien', 'bild', '', ['id' => 1]);
$check('ohne Datei eine Meldung', $n === 0 && $fehler !== []);

/* ---------- Ablage prüfen ---------- */
$status = vfile_storage_status(false);
$check('Ordner vorhanden und beschreibbar', $status['vorhanden'] && $status['beschreibbar']
    && str_ends_with($status['pfad'], 'fahrzeuge'));
$check('bei heiler Ablage kein Hinweis', vfile_storage_problem($status) === null);
$fehlt = vfile_storage_problem(['pfad' => '/data/uploads/fahrzeuge', 'vorhanden' => false,
    'beschreibbar' => false, 'benutzer' => 'nginx']);
$check('fehlender Ordner benannt', str_contains((string)$fehlt, '/data/uploads/fahrzeuge')
    && str_contains((string)$fehlt, 'fehlt') && str_contains((string)$fehlt, 'nginx'));
$gesperrt = vfile_storage_problem(['pfad' => '/data/uploads/fahrzeuge', 'vorhanden' => true,
    'beschreibbar' => false, 'benutzer' => '']);
$check('gesperrter Ordner benannt', str_contains((string)$gesperrt, 'nicht beschreibbar')
    && str_contains((string)$gesperrt, 'unbekannt'));

$_FILES = [];
[$n, $fehler] = vfile_store_uploads(7, null, 'dateien', 'bild', '', ['id' => 1]);
$check('ohne Feld ein Hinweis auf große Dateien', $n === 0 && str_contains($fehler[0], 'keine Datei an'));

/* ---------- Pfade ---------- */
$check('Pfad ohne Ausbruch', vfile_path(['stored_name' => '../../etc/passwd', 'thumb_name' => null]) === null);
$check('Pfad zur Vorschau', vfile_path(['stored_name' => $datensatz['stored_name'],
    'thumb_name' => $datensatz['thumb_name']], true) === vfile_dir() . DIRECTORY_SEPARATOR . $datensatz['thumb_name']);

/* ---------- Darstellung ---------- */
$_SERVER['REQUEST_URI'] = '/?p=vehicle&id=7';
$bild = ['id' => 11, 'vehicle_id' => 7, 'order_id' => null, 'art' => 'bild', 'titel' => 'GKW vorne',
         'orig_name' => 'GKW vorne.jpg', 'is_cover' => 1, 'created_at' => '2026-09-18 12:00:00',
         'hochgeladen_von' => 'Tester', 'size_bytes' => 120000, 'auftrag_nummer' => null];
$pdf = ['id' => 12, 'vehicle_id' => 7, 'order_id' => 42, 'art' => 'dokument', 'titel' => 'Kostenvoranschlag',
        'orig_name' => 'KV.pdf', 'is_cover' => 0, 'created_at' => '2026-09-18 12:05:00',
        'hochgeladen_von' => 'Tester', 'size_bytes' => 80000, 'auftrag_nummer' => '2026-0005'];
$html = render_partial('partials/vehicle_files', ['vehicle' => ['id' => 7], 'order' => null,
    'bilder' => [$bild], 'dokumente' => [$pdf], 'modus' => 'bilder']);
$check('Galerie mit Vorschau', str_contains($html, 'vorschau=1') && str_contains($html, 'GKW vorne'));
$check('Titelbild gekennzeichnet', str_contains($html, 'is-cover') && str_contains($html, 'Titelbild'));
$check('Upload-Formular für Dateien', str_contains($html, 'name="dateien[]"')
    && str_contains($html, 'enctype="multipart/form-data"'));
$check('kein accept-Filter (Handy-Auswahl)', !str_contains($html, 'accept='));
$html = render_partial('partials/vehicle_files', ['vehicle' => ['id' => 7], 'order' => null,
    'bilder' => [], 'dokumente' => [$pdf], 'modus' => 'dokumente']);
$check('Dokument mit Auftragsbezug', str_contains($html, 'Kostenvoranschlag') && str_contains($html, 'Auftrag 2026-0005'));
$check('Dateityp als Symbol', str_contains($html, '>PDF<'));
// Hinter dem Ingress von Home Assistant fehlt in einem neuen Tab die Anmeldung
$check('Datei öffnet im selben Tab', !str_contains($html, 'target="_blank"'));
$html = render_partial('partials/vehicle_files', ['vehicle' => ['id' => 7], 'order' => ['id' => 42],
    'bilder' => [], 'dokumente' => [$pdf], 'modus' => 'auftrag']);
$check('am Auftrag: Auftrags-id im Formular', str_contains($html, 'name="order_id" value="42"'));
$check('am Auftrag: kein doppelter Auftragsverweis', !str_contains($html, 'Auftrag 2026-0005'));

// Fotos am Auftrag: eigene Galerie, eigener Upload, kein Titelbild-Knopf
$auftragsfoto = ['id' => 13, 'vehicle_id' => 7, 'order_id' => 42, 'art' => 'bild', 'titel' => 'Bremse hinten',
    'orig_name' => 'bremse.jpg', 'is_cover' => 0, 'created_at' => '2026-09-19 08:00:00',
    'hochgeladen_von' => 'Tester', 'size_bytes' => 90000, 'auftrag_nummer' => '2026-0005'];
$html = render_partial('partials/vehicle_files', ['vehicle' => ['id' => 7], 'order' => ['id' => 42],
    'bilder' => [$auftragsfoto], 'dokumente' => [$pdf], 'modus' => 'auftrag']);
$check('Auftrag: Fotogalerie', str_contains($html, 'Fotos zum Auftrag') && str_contains($html, 'class="galerie"')
    && str_contains($html, 'Bremse hinten'));
$check('Auftrag: Vorschaubild', str_contains($html, 'vorschau=1'));
$check('Auftrag: Dokumente getrennt', str_contains($html, 'Dokumente zum Auftrag') && str_contains($html, 'Kostenvoranschlag'));
$check('Auftrag: Foto-Upload', str_contains($html, 'value="bild"') && str_contains($html, 'name="dateien[]"'));
$check('Auftrag: Größe wird geprüft', str_contains($html, 'data-max-mb='));
$check('Auftrag: Dokument-Upload', str_contains($html, 'value="dokument"'));
$check('Auftrag: kein Titelbild-Knopf', !str_contains($html, 'Als Titelbild'));
$check('Auftrag: Foto lässt sich entfernen', str_contains($html, 'value="file_delete"'));

$html = render_partial('partials/vehicle_files', ['vehicle' => ['id' => 7], 'order' => ['id' => 42],
    'bilder' => [], 'dokumente' => [], 'modus' => 'auftrag']);
$check('Auftrag: Hinweis ohne Fotos', str_contains($html, 'Noch kein Foto'));

}

echo "$ok bestanden, $fail fehlgeschlagen\n";
