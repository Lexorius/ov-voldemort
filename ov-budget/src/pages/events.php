<?php
declare(strict_types=1);

if (!can('view_events')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Veranstaltungen sind nur für die Leitung sichtbar.']);
    return;
}

$jahr = get_int('jahr');
$status = get_str('status');
$typ = get_int('typ_id');
$q = get_str('q');

$filter = ['jahr' => $jahr, 'status' => $status, 'typ_id' => $typ, 'q' => $q];

render('events', [
    'title'     => (string)setting('veranstaltung_modul_name', 'Veranstaltungen'),
    'kommend'   => event_query(array_merge($filter, ['zeit' => 'kommend', 'sort' => 'alt'])),
    'vergangen' => event_query(array_merge($filter, ['zeit' => 'vergangen', 'limit' => 50])),
    'jahre'     => event_years(),
    'jahr'      => $jahr,
    'status'    => $status,
    'typ'       => $typ,
    'q'         => $q,
]);
