<?php
declare(strict_types=1);

/** Eine STAN-Datei ausliefern – PDFs und Bilder im Browser, der Rest zum Speichern. ?id=… */
require_login();
$datei = stan_datei_find(get_int('id'));
if (!$datei) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Datei gibt es nicht (mehr).']);
    return;
}
$pfad = stan_datei_pfad($datei);
if ($pfad === null) {
    http_response_code(404);
    render('error', ['title' => 'Datei fehlt', 'message' => 'Der Eintrag „' . (string)$datei['name'] . '" ist vorhanden, die Datei selbst fehlt aber in der Ablage.']);
    return;
}
$mime = (string)$datei['mime'] ?: 'application/octet-stream';
$inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'text/plain'], true);
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($pfad));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', (string)$datei['name']) . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; object-src \'none\'');
header('Cache-Control: private, max-age=3600');
readfile($pfad);
exit;
