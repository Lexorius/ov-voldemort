<?php
declare(strict_types=1);

/** Gästeliste als CSV – für Excel, Serienbrief oder das Telefonieren hinterher */

// Die Datei enthält Rufnummern und die Einladungscodes – das bleibt bei der Leitung
if (!can('manage_events')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff',
        'message' => 'Die Gästeliste als Datei gibt es nur für die Leitung.']);
    return;
}

$event = event_find(get_int('id', 0));
if (!$event) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Veranstaltung gibt es nicht (mehr).']);
    return;
}

$wen = in_array(get_str('wen'), ['alle', 'zusagen', 'offen'], true) ? get_str('wen') : 'alle';
$gaeste = event_guests((int)$event['id']);
$gaeste = match ($wen) {
    'zusagen' => array_values(array_filter($gaeste,
        static fn($g) => in_array((string)$g['status'], ['zusage', 'vertretung'], true))),
    'offen'   => array_values(array_filter($gaeste, static fn($g) => (string)$g['status'] === 'offen')),
    default   => $gaeste,
};

$connector = connector_find((int)($event['connector_id'] ?? 0));

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="gaesteliste_'
    . slugify((string)$event['titel']) . '_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF");   // BOM, damit Excel die Umlaute richtig anzeigt

// Spaltennamen passend für einen Serienbrief – Anschrift zuerst
fputcsv($out, [
    'Anrede', 'Titel', 'Vorname', 'Nachname', 'Name', 'Organisation', 'Position',
    'Strasse', 'PLZ', 'Ort', 'Land', 'Briefanrede',
    'E-Mail', 'Telefon', 'Mobil',
    'Rueckmeldung', 'Begleiter', 'Personen', 'Vertretung', 'Nachricht',
    'Geantwortet am', 'Weg', 'Einladungscode', 'Einladungslink',
], ';');

foreach ($gaeste as $g) {
    $status = (string)$g['status'];
    $personen = in_array($status, ['zusage', 'vertretung'], true) ? 1 + (int)$g['begleiter'] : 0;
    fputcsv($out, [
        (string)($g['anrede'] ?? ''),
        (string)($g['kontakt_titel'] ?? ''),
        (string)($g['vorname'] ?? ''),
        (string)($g['nachname'] ?? ''),
        event_guest_name($g),
        (string)($g['organisation'] ?? ''),
        (string)($g['position'] ?? ''),
        (string)($g['strasse'] ?? ''),
        (string)($g['plz'] ?? ''),
        (string)($g['ort'] ?? ''),
        (string)($g['land'] ?? ''),
        event_guest_salutation($g),
        (string)($g['email'] ?? ''),
        (string)($g['telefon'] ?? ''),
        (string)($g['mobil'] ?? ''),
        event_antwort_label($status),
        (int)$g['begleiter'],
        $personen,
        (string)$g['vertretung'],
        preg_replace('/\s+/', ' ', (string)($g['kommentar'] ?? '')),
        $g['geantwortet_am'] ? de_datetime((string)$g['geantwortet_am']) : '',
        match ((string)$g['quelle']) {
            'einladung' => 'über die Einladung',
            'mensch'    => 'von Hand',
            default     => '',
        },
        (string)$g['code'],
        $connector !== null ? event_invite_url($connector, (string)$g['code']) : '',
    ], ';');
}

fclose($out);
audit('veranstaltung.export', 'event', (int)$event['id'], count($gaeste) . ' Zeilen');
exit;
