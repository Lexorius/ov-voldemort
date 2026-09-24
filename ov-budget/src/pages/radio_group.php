<?php
declare(strict_types=1);

if (!can('view_radios')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Funkgeräte sind nur für die Leitung sichtbar.']);
    return;
}

$gruppe = radio_group_find(get_int('id', 0));
if (!$gruppe) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Gruppe gibt es nicht (mehr).']);
    return;
}

render('radio_group', [
    'title'     => (string)$gruppe['name'],
    'gruppe'    => $gruppe,
    'geraete'   => radio_group_members((int)$gruppe['id']),
    'connector' => connector_find((int)($gruppe['qr_connector_id'] ?? 0)),
    'bestandConnectoren' => can('manage_radios') ? connector_liste('bestand') : [],
    'warn'      => setting_int('funk_pruefung_warnung_tage', 30),
]);
