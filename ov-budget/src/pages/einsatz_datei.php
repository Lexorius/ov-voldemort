<?php
declare(strict_types=1);

/**
 * Dokument eines Einsatzes ausliefern – für alle, die die Einsätze sehen dürfen.
 * ?id=…            die Datei selbst
 * ?id=…&vorschau=1 das kleine Vorschaubild (falls vorhanden)
 */
if (!can('view_einsaetze')) {
    http_response_code(403);
    exit('Kein Zugriff.');
}
$datei = einsatz_datei_find(get_int('id', 0));
if (!$datei) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Dieses Dokument gibt es nicht (mehr).']);
    return;
}
$pfad = einsatz_datei_pfad($datei, get_str('vorschau') === '1');
if ($pfad === null) {
    http_response_code(404);
    render('error', ['title' => 'Datei fehlt', 'message' => 'Der Eintrag „' . (string)($datei['titel'] ?: $datei['orig_name'])
        . '" ist vorhanden, die Datei selbst fehlt aber in der Ablage.']);
    return;
}

$mime = $datei['mime'] ?: 'application/octet-stream';
// Nur harmlose Typen im Browser anzeigen, alles andere herunterladen
$inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($pfad));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
    . '; filename="' . str_replace(['"', "\r", "\n"], '', (string)$datei['orig_name']) . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; object-src \'none\'');
header('Cache-Control: private, max-age=3600');

readfile($pfad);
exit;
