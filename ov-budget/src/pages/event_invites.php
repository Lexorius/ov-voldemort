<?php
declare(strict_types=1);

/**
 * Einladungsliste zum Drucken: Anschrift, Briefanrede, Einladungslink und
 * QR-Code je Person – die Vorlage für den Serienbrief.
 */

if (!can('manage_events')) {
    http_response_code(403);
    exit('Kein Zugriff.');
}

$event = event_find(get_int('id', 0));
if (!$event) {
    http_response_code(404);
    exit('Veranstaltung nicht gefunden.');
}

$wen = in_array(get_str('wen'), ['alle', 'offen', 'post'], true) ? get_str('wen') : 'alle';

$alle = event_guests((int)$event['id']);
$liste = match ($wen) {
    // Wer noch nicht geantwortet hat – für die Erinnerung
    'offen' => array_values(array_filter($alle, static fn($g) => (string)$g['status'] === 'offen')),
    // Nur, wer eine Anschrift hat – für den Serienbrief
    'post'  => array_values(array_filter($alle, 'event_guest_postfaehig')),
    default => $alle,
};

$connector = connector_find((int)($event['connector_id'] ?? 0));

echo render_partial('event_invites', [
    'event'     => $event,
    'gaeste'    => $liste,
    'connector' => $connector,
    'ohneAnschrift' => count(array_filter($alle, static fn($g) => !event_guest_postfaehig($g))),
    'wen'       => $wen,
    'mitQr'     => get_str('qr') !== '0',
]);
