<?php
declare(strict_types=1);

/** Datei eines Termins ausliefern – nur, wer den Termin sehen darf. ?id=… die Datei, &vorschau=1 das Vorschaubild */
$user = current_user();
$datei = kfile_find(get_int('id', 0));
$termin = $datei ? kalender_find((int)$datei['entry_id']) : null;
if (!$datei || !$termin) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Datei gibt es nicht (mehr).']);
    return;
}
if (!$user || !kalender_sichtbar($termin, $user)) {
    http_response_code(403);
    exit('Kein Zugriff.');
}
$pfad = kfile_path($datei, get_str('vorschau') === '1');
if ($pfad === null) {
    http_response_code(404);
    render('error', ['title' => 'Datei fehlt', 'message' => 'Der Eintrag „' . (string)($datei['titel'] ?: $datei['orig_name']) . '" ist vorhanden, die Datei selbst fehlt aber in der Ablage.']);
    return;
}
$mime = $datei['mime'] ?: 'application/octet-stream';
$inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($pfad));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', (string)$datei['orig_name']) . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; object-src \'none\'');
header('Cache-Control: private, max-age=3600');
readfile($pfad);
exit;
