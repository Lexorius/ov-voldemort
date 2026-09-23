<?php
declare(strict_types=1);

/** Gästeliste zum Drucken – zum Abhaken am Eingang */

if (!can('view_events')) {
    http_response_code(403);
    exit('Kein Zugriff.');
}

$event = event_find(get_int('id', 0));
if (!$event) {
    http_response_code(404);
    exit('Veranstaltung nicht gefunden.');
}

// Wer soll auf die Liste: alle Eingeladenen oder nur, wer zugesagt hat
$wen = in_array(get_str('wen'), ['alle', 'zusagen', 'offen'], true) ? get_str('wen') : 'alle';

$alle = event_guests((int)$event['id']);
$liste = match ($wen) {
    'zusagen' => array_values(array_filter($alle,
        static fn($g) => in_array((string)$g['status'], ['zusage', 'vertretung'], true))),
    'offen'   => array_values(array_filter($alle, static fn($g) => (string)$g['status'] === 'offen')),
    default   => $alle,
};

echo render_partial('event_print', [
    'event'   => $event,
    'gaeste'  => $liste,
    'stats'   => event_stats($alle),
    'gezeigt' => event_stats($liste),
    'wen'     => $wen,
    'mitKommentaren' => get_str('kommentare') !== '0',
]);
