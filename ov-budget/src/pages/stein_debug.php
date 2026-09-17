<?php
declare(strict_types=1);

/**
 * Mitschnitt einer Antwort der Stein.APP herunterladen.
 * Nur für die Administration – die Dateien können Angaben zu Fahrzeugen
 * enthalten. Der API-Schlüssel steht nicht darin (siehe stein_redact).
 */
require_role('admin');

$pfad = stein_debug_path(get_str('datei'));
if ($pfad === null) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Mitschnitt gibt es nicht (mehr).']);
    return;
}

header('Content-Type: application/json; charset=UTF-8');
header('Content-Length: ' . (string)filesize($pfad));
header('Content-Disposition: attachment; filename="' . basename($pfad) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($pfad);
exit;
