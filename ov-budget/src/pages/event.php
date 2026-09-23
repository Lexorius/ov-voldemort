<?php
declare(strict_types=1);

if (!can('view_events')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Veranstaltungen sind nur für die Leitung sichtbar.']);
    return;
}

$event = event_find(get_int('id', 0));
if (!$event) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Veranstaltung gibt es nicht (mehr).']);
    return;
}

$id = (int)$event['id'];
$gaeste = event_guests($id);
$suche = get_str('kontakt_suche');

// Kontakte, die noch nicht auf der Gästeliste stehen
$kandidaten = [];
if (can('manage_events') && can('view_contacts')) {
    $schon = array_filter(array_map(static fn($g) => (int)($g['contact_id'] ?? 0), $gaeste));
    foreach (contact_query(['q' => $suche, 'limit' => 50]) as $k) {
        if (!in_array((int)$k['id'], $schon, true)) {
            $kandidaten[] = $k;
        }
    }
}

render('event', [
    'title'     => (string)$event['titel'],
    'event'     => $event,
    'gaeste'    => $gaeste,
    'stats'     => event_stats($gaeste),
    'dateien'   => efile_list($id),
    'buchungen' => can('view_expenses') ? event_expenses($id) : [],
    'kosten'    => event_kosten($id),
    'connector' => connector_find((int)($event['connector_id'] ?? 0)),
    'kandidaten' => $kandidaten,
    'kontaktSuche' => $suche,
    'verteiler' => can('manage_events') && can('view_contacts')
        ? db_all('SELECT id, name FROM contact_groups WHERE is_active = 1 ORDER BY name') : [],
]);
