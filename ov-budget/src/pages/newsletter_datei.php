<?php
declare(strict_types=1);

/** Das PDF eines Newsletters ausliefern – im Browser oder zum Speichern (?laden=1) */
require_login();
if (!can('view_newsletter')) {
    http_response_code(403);
    exit('Kein Zugriff.');
}
$n = newsletter_find(get_int('id'));
if ($n === null) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Newsletter gibt es nicht (mehr).']);
    return;
}
$pfad = newsletter_pfad($n);
if ($pfad === null) {
    http_response_code(404);
    render('error', ['title' => 'Datei fehlt', 'message' => 'Zu diesem Newsletter liegt kein PDF in der Ablage.']);
    return;
}
header('Content-Type: application/pdf');
header('Content-Length: ' . (string)filesize($pfad));
header('Content-Disposition: ' . (get_str('laden') === '1' ? 'attachment' : 'inline') . '; filename="' . str_replace(['"', "\r", "\n"], '', (string)($n['orig_name'] ?: 'newsletter.pdf')) . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; object-src \'none\'');
header('Cache-Control: private, max-age=3600');
readfile($pfad);
exit;
