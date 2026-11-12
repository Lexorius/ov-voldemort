<?php
declare(strict_types=1);

/**
 * Ein Symbol der Karten ausliefern: ?id=…
 *
 * SVG kann Skript enthalten. Sie wird beim Hochladen geprüft (karten_svg_pruefen) –
 * und hier zusätzlich mit einer Regel ausgeliefert, die dem Browser jedes Skript und
 * jedes Nachladen verbietet, falls doch einmal etwas durchrutscht.
 */
if (!can('view_karten')) {
    http_response_code(403);
    exit('Kein Zugriff.');
}
$symbol = karten_symbol_find(get_int('id', 0));
$pfad = $symbol ? karten_symbol_pfad($symbol) : null;
if (!$symbol || $pfad === null) {
    http_response_code(404);
    exit('Dieses Symbol gibt es nicht (mehr).');
}

$mime = (string)$symbol['mime'];
if (!in_array($mime, ['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) {
    $mime = 'application/octet-stream';
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($pfad));
header('Content-Disposition: ' . ($mime === 'application/octet-stream' ? 'attachment' : 'inline')
    . '; filename="' . str_replace(['"', "\r", "\n"], '', (string)$symbol['orig_name']) . '"');
header('X-Content-Type-Options: nosniff');
// sandbox: selbst direkt geöffnet läuft in der Datei nichts; style-src für <style> in der SVG
header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; img-src data:; sandbox');
// Ein Symbol ändert sich unter seiner Kennung nie
header('Cache-Control: private, max-age=86400');

readfile($pfad);
exit;
