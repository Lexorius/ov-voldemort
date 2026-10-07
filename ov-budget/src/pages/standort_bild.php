<?php
declare(strict_types=1);

/** Bild eines Stell- oder Lagerplatzes: ?id=… die Datei, ?id=…&vorschau=1 das kleine Vorschaubild */
$bild = standort_bild_find((int)get_int('id', 0));
$pfad = $bild ? standort_bild_pfad($bild, get_str('vorschau') === '1') : null;
if (!$bild || $pfad === null) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Dieses Bild gibt es nicht (mehr).']);
    return;
}
header('Content-Type: ' . ((string)$bild['mime'] ?: 'application/octet-stream'));
header('Content-Length: ' . (string)filesize($pfad));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($pfad);
exit;
