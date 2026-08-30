<?php
declare(strict_types=1);

$att = db_row('SELECT * FROM wish_attachments WHERE id = ?', [get_int('id', 0)]);
if (!$att) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Die Datei existiert nicht (mehr).']);
    return;
}

$path = upload_dir() . DIRECTORY_SEPARATOR . basename((string)$att['stored_name']);
if (!is_file($path)) {
    http_response_code(404);
    render('error', ['title' => 'Datei fehlt', 'message' => 'Die Datei ist auf dem Server nicht mehr vorhanden.']);
    return;
}

$mime = $att['mime'] ?: 'application/octet-stream';
// Nur harmlose Typen inline anzeigen, alles andere herunterladen
$inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
    . '; filename="' . addslashes((string)$att['orig_name']) . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; object-src \'none\'');
header('Cache-Control: private, max-age=600');

readfile($path);
exit;
