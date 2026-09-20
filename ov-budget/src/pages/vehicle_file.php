<?php
declare(strict_types=1);

/**
 * Bild oder Dokument eines Fahrzeugs ausliefern.
 * ?id=…            die Datei selbst
 * ?id=…&vorschau=1 das kleine Vorschaubild (falls vorhanden)
 */
if (!can('view_vehicles')) {
    http_response_code(403);
    exit('Kein Zugriff.');
}

$datei = vfile_find((int)get_int('id', 0));
$pfad = $datei ? vfile_path($datei, get_str('vorschau') === '1') : null;

if (!$datei) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Datei gibt es nicht (mehr).']);
    return;
}

if ($pfad === null) {
    // Der Eintrag steht in der Datenbank, die Datei liegt aber nicht auf der Platte
    http_response_code(404);
    $hinweis = 'Der Eintrag „' . (string)($datei['titel'] ?: $datei['orig_name'])
        . '" ist vorhanden, die Datei selbst fehlt aber in der Ablage.';
    if (can('manage_vehicles')) {
        $st = vfile_storage_status(false);
        $hinweis .= sprintf(' Ordner: %s (%s, %s). Näheres steht in der Verwaltung unter „Dateiablage".',
            $st['pfad'],
            $st['vorhanden'] ? 'vorhanden' : 'fehlt',
            $st['beschreibbar'] ? 'beschreibbar' : 'nicht beschreibbar');
    } else {
        $hinweis .= ' Bitte in der Verwaltung nachsehen lassen.';
    }
    render('error', ['title' => 'Datei fehlt', 'message' => $hinweis]);
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
